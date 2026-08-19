<?php

namespace App\Services\Driver;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverDocument;
use App\Notifications\CustomDatabaseNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * فحص يومي لتواريخ انتهاء رخصة القيادة ووثيقة التأمين لدى السائقين:
 * - يُرسل تذكيرات عند نقاط محددة (15 / 7 / 3 / 0 يوم) قبل الانتهاء دون تكرار.
 * - عند الانتهاء الفعلي: يُعلّم المستند كـ "Expired"، يمنع قبول اشتراكات جديدة فقط
 *   (عبر DriverMatchingService)، ويُبقي الاشتراكات النشطة الحالية تعمل، مع تنبيه الإدارة.
 */
class DriverExpiryNotificationService
{
    /** نقاط التذكير مرتبة تصاعدياً — أول نقطة تُساوي أو تتجاوزها الأيام المتبقية هي "الجرعة" الحالية */
    private const MILESTONES_ASC = [0, 3, 7, 15];

    public function run(): array
    {
        $stats = [
            'license_reminders'   => 0,
            'license_expired'     => 0,
            'insurance_reminders' => 0,
            'insurance_expired'   => 0,
        ];

        $this->processLicenseExpiries($stats);
        $this->processInsuranceExpiries($stats);

        return $stats;
    }

    private function processLicenseExpiries(array &$stats): void
    {
        $today = Carbon::today();

        Driver::whereNotNull('license_expiry')
            ->with('user')
            ->chunkById(100, function ($drivers) use ($today, &$stats) {
                foreach ($drivers as $driver) {
                    if (!$driver->user) {
                        continue;
                    }

                    $daysRemaining = $this->daysRemaining($today, $driver->license_expiry);

                    if ($daysRemaining < 0) {
                        if ($this->markLicenseExpired($driver)) {
                            $stats['license_expired']++;
                        }
                        continue;
                    }

                    $milestone = $this->currentMilestone($daysRemaining);
                    if ($milestone !== null && $this->shouldNotify($milestone, $driver->license_expiry_notified_milestone)) {
                        $this->notifyDriverReminder($driver->user, 'رخصة القيادة', $daysRemaining, $driver->license_expiry, $driver->id);
                        $driver->update(['license_expiry_notified_milestone' => $milestone]);
                        $stats['license_reminders']++;
                    }
                }
            });
    }

    private function processInsuranceExpiries(array &$stats): void
    {
        $today = Carbon::today();

        DriverDocument::where('doc_type', 'INSURANCE')
            ->whereNotNull('insurance_expiry_date')
            ->with('driver.user')
            ->chunkById(100, function ($documents) use ($today, &$stats) {
                foreach ($documents as $document) {
                    $driver = $document->driver;
                    if (!$driver || !$driver->user) {
                        continue;
                    }

                    $daysRemaining = $this->daysRemaining($today, $document->insurance_expiry_date);

                    if ($daysRemaining < 0) {
                        if ($this->markInsuranceExpired($document, $driver)) {
                            $stats['insurance_expired']++;
                        }
                        continue;
                    }

                    $milestone = $this->currentMilestone($daysRemaining);
                    if ($milestone !== null && $this->shouldNotify($milestone, $document->expiry_notified_milestone)) {
                        $this->notifyDriverReminder($driver->user, 'وثيقة التأمين', $daysRemaining, $document->insurance_expiry_date, $driver->id);
                        $document->update(['expiry_notified_milestone' => $milestone]);
                        $stats['insurance_reminders']++;
                    }
                }
            });
    }

    private function daysRemaining(Carbon $today, string $expiryDate): int
    {
        return (int) $today->diffInDays(Carbon::parse($expiryDate)->startOfDay(), false);
    }

    /** أصغر نقطة تذكير تساوي أو تتجاوز الأيام المتبقية؛ null لو أكثر من أبعد نقطة (15 يوم) */
    private function currentMilestone(int $daysRemaining): ?int
    {
        foreach (self::MILESTONES_ASC as $milestone) {
            if ($daysRemaining <= $milestone) {
                return $milestone;
            }
        }
        return null;
    }

    /** نُرسل فقط لو لم تُرسل من قبل، أو لو النقطة الحالية أكثر إلحاحاً (رقم أصغر) من آخر نقطة أُرسلت */
    private function shouldNotify(int $currentMilestone, ?int $alreadyNotified): bool
    {
        return $alreadyNotified === null || $currentMilestone < $alreadyNotified;
    }

    private function notifyDriverReminder($user, string $docLabel, int $daysRemaining, string $expiryDate, int $driverId): void
    {
        $expiryLabel = Carbon::parse($expiryDate)->format('Y-m-d');
        $whenLabel = $daysRemaining === 0
            ? 'اليوم'
            : "خلال {$daysRemaining} يوم";

        try {
            $user->notify(new CustomDatabaseNotification([
                'type'      => 'driver_document_expiring_soon',
                'title'     => '⏰ اقتراب انتهاء ' . $docLabel,
                'message'   => "تنبيه: {$docLabel} الخاصة بك ستنتهي {$whenLabel} (بتاريخ {$expiryLabel}). يرجى تجديدها ورفع النسخة الجديدة من خلال التطبيق قبل الانتهاء.",
                'screen'    => 'DRIVER_PROFILE',
                'entity_id' => (string) $driverId,
            ]));
        } catch (\Throwable $e) {
            Log::warning("DriverExpiryNotificationService: failed sending reminder to driver #{$driverId}: " . $e->getMessage());
        }
    }

    /** يُعلّم مستند الرخصة كمنتهي ويُنبّه السائق + الإدارة — مرة واحدة فقط بفضل فحص الحالة الحالية */
    private function markLicenseExpired(Driver $driver): bool
    {
        $document = DriverDocument::where('driver_id', $driver->id)->where('doc_type', 'LICENSE')->first();

        if (!$document || $document->status === 'Expired') {
            return false; // لا مستند مرتبط، أو أُعلن انتهاؤه مسبقاً
        }

        $document->update(['status' => 'Expired']);

        $this->notifyDriverExpired($driver->user, 'رخصة القيادة', $driver->license_expiry, $driver->id);
        $this->notifyAdminsExpired($driver, 'رخصة القيادة', $driver->license_expiry);

        return true;
    }

    private function markInsuranceExpired(DriverDocument $document, Driver $driver): bool
    {
        if ($document->status === 'Expired') {
            return false;
        }

        $document->update(['status' => 'Expired']);

        $this->notifyDriverExpired($driver->user, 'وثيقة التأمين', $document->insurance_expiry_date, $driver->id);
        $this->notifyAdminsExpired($driver, 'وثيقة التأمين', $document->insurance_expiry_date);

        return true;
    }

    private function notifyDriverExpired($user, string $docLabel, string $expiryDate, int $driverId): void
    {
        $expiryLabel = Carbon::parse($expiryDate)->format('Y-m-d');

        try {
            $user->notify(new CustomDatabaseNotification([
                'type'      => 'driver_document_expired',
                'title'     => '🚫 انتهت صلاحية ' . $docLabel,
                'message'   => "انتهت صلاحية {$docLabel} الخاصة بك بتاريخ {$expiryLabel}. تم إيقاف قبول اشتراكات جديدة مؤقتاً حتى تُحدّث الوثيقة من خلال التطبيق — اشتراكاتك النشطة الحالية تستمر بشكل طبيعي.",
                'screen'    => 'DRIVER_PROFILE',
                'entity_id' => (string) $driverId,
            ]));
        } catch (\Throwable $e) {
            Log::warning("DriverExpiryNotificationService: failed sending expiry alert to driver #{$driverId}: " . $e->getMessage());
        }
    }

    private function notifyAdminsExpired(Driver $driver, string $docLabel, string $expiryDate): void
    {
        $expiryLabel = Carbon::parse($expiryDate)->format('Y-m-d');
        $driverName = $driver->user->full_name ?? "#{$driver->id}";

        foreach (Admin::with('user')->get() as $admin) {
            if (!$admin->user) {
                continue;
            }

            try {
                $admin->user->notify(new CustomDatabaseNotification([
                    'type'      => 'driver_document_expired_admin_alert',
                    'title'     => '⚠️ وثيقة سائق منتهية الصلاحية',
                    'message'   => "انتهت صلاحية {$docLabel} للسائق {$driverName} (#{$driver->id}) بتاريخ {$expiryLabel}. تم منعه تلقائياً من قبول اشتراكات جديدة، واشتراكاته النشطة الحالية مستمرة — يرجى المراجعة والتصرف يدوياً إذا لزم.",
                    'screen'    => 'ADMIN_DRIVER_DETAILS',
                    'entity_id' => (string) $driver->id,
                ]));
            } catch (\Throwable $e) {
                Log::warning("DriverExpiryNotificationService: failed sending admin alert for driver #{$driver->id}: " . $e->getMessage());
            }
        }
    }
}
