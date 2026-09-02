<?php

namespace App\Jobs;

use App\Models\Admin\AdminAlert;
use App\Models\Driver\Driver;
use App\Models\Shared\DriverReview;
use App\Services\AiClassifierService;
use App\Services\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مهمة برمجية تأخذ بيانات وتقييمات السائق لآخر 14 يوماً من قاعدة البيانات،
 * ترسلها لـ FastAPI عبر makeDecision، وتطبق القرارات القادمة فوراً على قاعدة البيانات.
 */
class EvaluateDriverPolicyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public int $driverId) {}

    public function handle(
        AiClassifierService $aiService,
        NotificationService $notificationService
    ): void {
        try {
            // 1️⃣ جلب السائق وحسابه المرتبط من قاعدة البيانات
            $driver = Driver::with(['user'])->find($this->driverId);

            if (!$driver) {
                Log::warning("EvaluateDriverPolicyJob: السائق رقم {$this->driverId} غير موجود في قاعدة البيانات.");
                return;
            }

            // 2️⃣ جلب تقييمات السائق لآخر 14 يوماً فقط لتفادي العقاب المزدوج على الأخطاء القديمة
            $reviews = DriverReview::where('driver_id', $this->driverId)
                ->where('created_at', '>=', now()->subDays(14))
                ->get()
                ->map(fn($r) => [
                    'parent_id' => (string) ($r->parent_id ?? 'p_0'),
                    'text'      => (string) ($r->comment ?? $r->review_text ?? ''),
                    'date'      => $r->created_at ? $r->created_at->format('Y-m-d') : date('Y-m-d'),
                ])
                ->filter(fn($r) => !empty(trim($r['text'])))
                ->values()
                ->toArray();

            $currentRating = (float) ($driver->rating_avg ?? $driver->rating ?? 5.0);

            // 3️⃣ استدعاء FastAPI للحصول على القرار
            $decision = $aiService->makeDecision($driver->id, $currentRating, $reviews);

            if (!$decision || !is_array($decision)) {
                Log::warning("EvaluateDriverPolicyJob: لم يتم استلام استجابة صالحة من FastAPI للسائق #{$driver->id}.");
                return;
            }

            // 4️⃣ قراءة الأفعال بالتسمية الصحيحة من FastAPI
            $actions = $decision['actions'] ?? [];
            if (is_string($actions)) {
                $actions = [$actions];
            }

            // أ) إجراء الإيقاف والحظر من البحث
            if (in_array('BLOCK_FROM_SEARCH', $actions, true) || in_array('suspend_driver', $actions, true) || ($decision['action'] ?? '') === 'suspend_driver') {
                $driver->update([
                    'status'        => 'Suspended',
                    'is_searchable' => false,
                ]);
            }

            // ب) إجراء تعديل التقييم (rating_change)
            if (in_array('ADJUST_RATING', $actions, true) && isset($decision['rating_change'])) {
                $ratingChange = (float) $decision['rating_change'];
                $newRating = max(1.0, min(5.0, $currentRating + $ratingChange));
                $driver->update([
                    'rating_avg' => round($newRating, 2),
                ]);
            }

            // ج) إجراء إضافة تنبيه للإدارة (SEND_ADMIN_ALERT)
            if (in_array('SEND_ADMIN_ALERT', $actions, true) || in_array('BLOCK_FROM_SEARCH', $actions, true)) {
                $isBlock = in_array('BLOCK_FROM_SEARCH', $actions, true);
                AdminAlert::create([
                    'driver_id'         => $driver->id,
                    'risk_level'        => $decision['risk_level'] ?? ($isBlock ? 'CRITICAL' : 'HIGH'),
                    'actions_taken'     => $actions,
                    'admin_message'     => $decision['admin_alert_message'] ?? $decision['message_ar'] ?? '',
                    'reasoning'         => $decision['reasoning'] ?? $decision['message_ar'] ?? '',
                    'ai_metrics'        => $decision['metrics'] ?? [],
                    'evaluated_reviews' => $reviews,
                    'is_resolved'       => false,
                    'alert_type'        => $isBlock ? 'suspend_driver' : 'driver_alert',
                    'title'             => $isBlock ? '⛔ إيقاف وحظر تلقائي لسائق بالذكاء الاصطناعي' : '⚠️ تنبيه إداري لسائق بالذكاء الاصطناعي',
                    'message'           => $decision['message_ar'] ?? $decision['reason'] ?? 'تنبيه صادرة من محرك تقييم سياسات السائقين.',
                    'severity'          => $isBlock ? 3 : 2,
                    'action_required'   => $isBlock ? 'review' : 'notify',
                    'metadata'          => $decision,
                    'is_read'           => false,
                ]);
            }

            // د) إجراء إرسال إشعار فوري للسائق (SEND_DRIVER_NOTIFICATION)
            if ((in_array('SEND_DRIVER_NOTIFICATION', $actions, true) || in_array('BLOCK_FROM_SEARCH', $actions, true)) && $driver->user) {
                try {
                    $isBlock = in_array('BLOCK_FROM_SEARCH', $actions, true);
                    $notificationService->sendToUser($driver->user, $isBlock ? 'driver_suspended' : 'driver_ai_alert', [
                        'title'       => $isBlock ? 'تم إيقاف حسابك مؤقتاً ⛔' : 'تنبيه سلوكي ⚠️',
                        'message'     => $decision['message_ar'] ?? ($isBlock ? 'تم إيقاف حسابك وحظر ظهورك في نتائج البحث، يرجى التواصل مع الإدارة.' : 'تم رصد ملاحظة على أدائك، يرجى الانتباه لجودة الخدمة.'),
                        'entity_type' => 'driver',
                        'entity_id'   => (string) $driver->id,
                    ]);
                } catch (Throwable $e) {
                    Log::warning("EvaluateDriverPolicyJob: فشل إرسال إشعار للسائق #{$driver->id}: " . $e->getMessage());
                }
            }

        } catch (Throwable $e) {
            Log::error("EvaluateDriverPolicyJob: حدث خطأ أثناء تنفيذ تقييم السائق #{$this->driverId} - " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
