<?php

namespace App\Services\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\FinancialLedger;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\RechargeRequest;
use App\Models\Shared\Trip;
use App\Models\Shared\TripDispute;
use App\Models\Shared\TripEscrowHold;
use App\Models\Shared\TripStudentAttendance;
use App\Models\Shared\WithdrawalRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    protected ?NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }

    // شروط ومحددات النظام المالي
    public const FIXED_SERVICE_FEE = 100; // 1 دينار (100 قرش) رسوم خدمة ثابتة
    public const MAX_PARENT_BALANCE = 500000; // 5,000 دينار (500,000 قرش) الحد الأعلى لرصيد محفظة ولي الأمر
    public const MIN_WITHDRAWAL_AMOUNT = 5000; // 50 دينار (5,000 قرش) الحد الأدنى لسحب السائق
    public const ESCROW_HOLD_HOURS = 24; // 24 ساعة فترة السماح للنزاع والتحويل للرصيد المتاح

    /**
     * ⚠️ لا تُثبّت نسبة العمولة كثابت هنا. المصدر الوحيد هو جدول pricing_settings عبر
     * PricingSetting::commissionRateFraction()، وإلا اختلفت العمولة بين هذه الخدمة
     * وبقية النظام (كانت 12٪ هنا و8٪ في كل مكان آخر) ولم تتطابق التقارير أبداً.
     */
    protected function commissionRate(): float
    {
        return \App\Models\Shared\PricingSetting::commissionRateFraction();
    }

    /**
     * 1️⃣ تسجيل حركة مالية غير قابلة للمسح في السجل المالي (Immutable Double-Entry Ledger)
     */
    public function recordLedgerEntry(
        string $sourceAccount,
        string $destinationAccount,
        int $amountCents,
        string $type,
        int $balanceBefore = 0,
        int $balanceAfter = 0,
        ?string $referenceNumber = null,
        ?array $metadata = null
    ): FinancialLedger {
        return FinancialLedger::create([
            'reference_number'    => $referenceNumber,
            'source_account'      => $sourceAccount,
            'destination_account' => $destinationAccount,
            'amount'              => $amountCents,
            'balance_before'      => $balanceBefore,
            'balance_after'       => $balanceAfter,
            'type'                => $type,
            'status'              => 'completed',
            'metadata'            => $metadata,
        ]);
    }

    /**
     * ب. معادلة السلامة المالية اليومية (Daily Solvency Check)
     */
    public function checkDailySolvency(): array
    {
        $vault = MasterEscrowVault::getVault();

        $parentsEscrow   = $vault->parents_escrow_pool;
        $driverPending   = $vault->driver_pending_pool;
        $driverAvailable = $vault->driver_available_pool;
        $platformRevenue = $vault->platform_revenue_pool;

        $calculatedVaultTotal = $parentsEscrow + $driverPending + $driverAvailable + $platformRevenue;

        // إجمالي المبالغ في أرصدة محافظ المستخدمين (أولياء الأمور والسائقين)
        $totalParentWallets = (int) ParentModel::all()->sum(fn($p) => $p->balance);
        $totalDriverWallets = (int) Driver::all()->sum(fn($d) => $d->balance);
        $actualSystemTotal  = $totalParentWallets + $totalDriverWallets + $platformRevenue;

        $isSolvent = ($calculatedVaultTotal === $actualSystemTotal);
        $discrepancy = $actualSystemTotal - $calculatedVaultTotal;

        if (!$isSolvent) {
            Log::emergency("🚨 خلل في السلامة المالية للنظام (Discrepancy Detect)! الفرق: {$discrepancy} قرش.");
        }

        return [
            'is_solvent'             => $isSolvent,
            'discrepancy_cents'      => $discrepancy,
            'parents_escrow_pool'    => round($parentsEscrow / 100, 2),
            'driver_pending_pool'    => round($driverPending / 100, 2),
            'driver_available_pool'  => round($driverAvailable / 100, 2),
            'platform_revenue_pool'  => round($platformRevenue / 100, 2),
            'total_calculated_dinar' => round($calculatedVaultTotal / 100, 2),
        ];
    }

    /**
     * 2️⃣ طلب رحلة يومية وتجميد المبلغ (Hold)
     */
    public function holdTripAmount(Trip $trip, int $parentUserId, float $amountDinar): TripEscrowHold
    {
        return DB::transaction(function () use ($trip, $parentUserId, $amountDinar) {
            $amountCents = (int) round($amountDinar * 100);
            $parent = ParentModel::where('user_id', $parentUserId)->first() ?? ParentModel::findOrFail($parentUserId);

            if (($parent->balance) < $amountCents) {
                throw ValidationException::withMessages([
                    'balance' => ['رصيد محفظتك غير كافٍ لحجز هذه الرحلة. يرجى شحن المحفظة.'],
                ]);
            }

            $balBefore = $parent->balance;
            $parent->withdraw($amountCents);
            $balAfter = $parent->balance;

            $vault = MasterEscrowVault::getVault();
            $vault->increment('parents_escrow_pool', $amountCents);

            $hold = TripEscrowHold::create([
                'trip_id'     => $trip->id,
                'parent_id'   => $parentUserId,
                'driver_id'   => $trip->driver_id,
                'amount'      => $amountCents,
                'hold_status' => 'held',
                'held_at'     => now(),
            ]);

            $this->recordLedgerEntry(
                "parent_wallet_{$parentUserId}",
                "parents_escrow_pool",
                $amountCents,
                'trip_hold',
                $balBefore,
                $balAfter,
                "TRIP-HOLD-{$trip->id}",
                ['trip_id' => $trip->id]
            );

            return $hold;
        });
    }

    /**
     * تنفيذ الرحلة وتحويل الرصيد للمعلق (Hold -> Capture -> Pending 24h)
     */
    public function captureTripOnCompletion(Trip $trip): TripEscrowHold
    {
        return DB::transaction(function () use ($trip) {
            $hold = TripEscrowHold::where('trip_id', $trip->id)
                ->where('hold_status', 'held')
                ->firstOrFail();

            $vault = MasterEscrowVault::getVault();
            $vault->decrement('parents_escrow_pool', $hold->amount);
            $vault->increment('driver_pending_pool', $hold->amount);

            $hold->update([
                'hold_status'  => 'captured_pending',
                'captured_at'  => now(),
                'available_at' => now()->addHours(self::ESCROW_HOLD_HOURS),
            ]);

            $this->recordLedgerEntry(
                "parents_escrow_pool",
                "driver_pending_pool",
                $hold->amount,
                'trip_capture',
                0,
                $hold->amount,
                "TRIP-CAPTURE-{$trip->id}",
                ['trip_id' => $trip->id]
            );

            return $hold;
        });
    }

    /**
     * تحويل الأرباح المعلقة إلى رصيد متاح للسائق تلقائياً بعد مرور 24 ساعة (Escrow Release)
     */
    public function releasePendingTripEscrows(): int
    {
        $eligibleHolds = TripEscrowHold::where('hold_status', 'captured_pending')
            ->where('available_at', '<=', now())
            ->get();

        $releasedCount = 0;

        foreach ($eligibleHolds as $hold) {
            DB::transaction(function () use ($hold, &$releasedCount) {
                $vault = MasterEscrowVault::getVault();

                $totalCents    = $hold->amount;
                $commission    = (int) round($totalCents * $this->commissionRate());
                $netDriverPay  = $totalCents - $commission;

                $vault->decrement('driver_pending_pool', $totalCents);
                $vault->increment('platform_revenue_pool', $commission);
                $vault->increment('driver_available_pool', $netDriverPay);

                $driver = Driver::find($hold->driver_id);
                if ($driver) {
                    $driver->deposit($netDriverPay);
                }

                $hold->update(['hold_status' => 'released_available']);

                $this->recordLedgerEntry(
                    "driver_pending_pool",
                    "driver_wallet_{$hold->driver_id}",
                    $netDriverPay,
                    'driver_payout',
                    0,
                    $netDriverPay,
                    "PAYOUT-HOLD-{$hold->id}",
                    ['commission_cents' => $commission]
                );

                $this->recordLedgerEntry(
                    "driver_pending_pool",
                    "platform_revenue_pool",
                    $commission,
                    'platform_commission',
                    0,
                    $commission,
                    "COMMISSION-HOLD-{$hold->id}"
                );

                $releasedCount++;
            });
        }

        return $releasedCount;
    }

    /**
     * جدول إلغاء الرحلات اليومية وسياسة الغرامات (Cancellation Matrix)
     */
    public function processTripCancellation(Trip $trip, string $cancelledBy, ?Carbon $tripScheduledAt = null): array
    {
        return DB::transaction(function () use ($trip, $cancelledBy, $tripScheduledAt) {
            $hold = TripEscrowHold::where('trip_id', $trip->id)
                ->where('hold_status', 'held')
                ->first();

            $amountCents = $hold?->amount ?? 0;
            $scheduledAt = $tripScheduledAt ?? Carbon::parse($trip->scheduled_start_time ?? $trip->scheduled_at ?? now());
            $minutesBefore = now()->diffInMinutes($scheduledAt, false);

            $parentRefundCents = 0;
            $driverPayCents    = 0;
            $platformFeeCents  = 0;
            $driverPenaltyCents= 0;

            if ($cancelledBy === 'parent') {
                if ($minutesBefore > 120) { // قبل وقت الرحلة بـ > 2 ساعة
                    $parentRefundCents = $amountCents;
                } elseif ($minutesBefore < 30) { // قبل وقت الرحلة بـ < 30 دقيقة
                    $parentRefundCents = (int) round($amountCents * 0.50); // خصم 50% كغرامة
                    $driverPayCents    = (int) round($amountCents * 0.40); // 40% للسائق
                    $platformFeeCents  = (int) round($amountCents * 0.10); // 10% منصة
                } else { // بين 30 دقيقة وساعتين
                    $parentRefundCents = (int) round($amountCents * 0.80);
                    $driverPayCents    = (int) round($amountCents * 0.20);
                }
            } elseif ($cancelledBy === 'no_show') { // عدم خروج الطفل عند وصول السائق
                $parentRefundCents = 0; // خصم 100%
                $platformFeeCents  = (int) round($amountCents * $this->commissionRate());
                $driverPayCents    = $amountCents - $platformFeeCents;
            } elseif ($cancelledBy === 'driver') { // إلغاء من السائق في أي وقت
                $parentRefundCents = $amountCents; // استرجاع 100% لولي الأمر
                $driverPenaltyCents= (int) round($amountCents * 0.20); // غرامة 20% على السائق
            }

            $vault = MasterEscrowVault::getVault();

            if ($amountCents > 0 && $hold) {
                $vault->decrement('parents_escrow_pool', $amountCents);

                if ($parentRefundCents > 0) {
                    $parent = ParentModel::where('user_id', $hold->parent_id)->first() ?? ParentModel::find($hold->parent_id);
                    if ($parent) {
                        $parent->deposit($parentRefundCents);
                    }
                }

                if ($driverPayCents > 0) {
                    $driver = Driver::find($hold->driver_id);
                    if ($driver) {
                        $driver->deposit($driverPayCents);
                    }
                    $vault->increment('driver_available_pool', $driverPayCents);
                }

                if ($platformFeeCents > 0) {
                    $vault->increment('platform_revenue_pool', $platformFeeCents);
                }

                if ($driverPenaltyCents > 0) {
                    $vault->increment('penalty_pool', $driverPenaltyCents);
                    $driver = Driver::find($hold->driver_id);
                    if ($driver && $driver->balance >= $driverPenaltyCents) {
                        $driver->withdraw($driverPenaltyCents);
                    }
                }

                $hold->update(['hold_status' => 'refunded']);
            }

            return [
                'cancelled_by'         => $cancelledBy,
                'parent_refund_dinar'  => round($parentRefundCents / 100, 2),
                'driver_pay_dinar'     => round($driverPayCents / 100, 2),
                'platform_fee_dinar'   => round($platformFeeCents / 100, 2),
                'driver_penalty_dinar' => round($driverPenaltyCents / 100, 2),
            ];
        });
    }

    /**
     * 3️⃣ تسوية العقد الشهري الإغلاق والمقاصة النهائية (Monthly Subscription Settlement)
     */
    /**
     * 3️⃣ تسوية الاشتراك الشهري الإغلاق والمقاصة النهائية (Monthly Subscription Settlement)
     */
    public function settleMonthlySubscription(SubscriptionRequest $subscription): array
    {
        return DB::transaction(function () use ($subscription) {
            $totalContractPrice = (float) ($subscription->total_amount_after_discount ?? $subscription->total_price ?? 0);
            $plannedTripsCount  = max((int) ($subscription->days_count ?? 20), 1);
            $perTripCost        = $totalContractPrice / $plannedTripsCount;

            $trips = Trip::whereHas('route', fn($q) => $q->where('subscription_request_id', $subscription->id))->get();

            $completedCount    = $trips->where('status', 'completed')->count();
            $parentAbsentCount = TripStudentAttendance::whereIn('trip_id', $trips->pluck('id'))
                ->where('attendance_status', 'absent')
                ->count();

            $driverAbsentCount = $trips->where('driver_attendance', false)->count();
            $holidaysCount     = $trips->where('status', 'holiday')->count();

            // احتساب عدد مرات تغيير الموقع المعتمدة ورسومها
            $activeSubIds = \App\Models\Shared\ActiveSubscription::where('subscription_request_id', $subscription->id)->pluck('id');
            $approvedChanges = \App\Models\Shared\LocationChangeRequest::whereIn('active_subscription_id', $activeSubIds)
                ->where('status', \App\Models\Shared\LocationChangeRequest::STATUS_APPROVED)
                ->get();
            $locationChangesCount = $approvedChanges->count();
            $locationChangesFees  = (float) $approvedChanges->sum('fee_amount');

            $billableTrips = $completedCount + $parentAbsentCount;
            $finalAmount   = round(($billableTrips * $perTripCost) + $locationChangesFees, 2);
            $refundAmount  = max(0, round($totalContractPrice - round($billableTrips * $perTripCost, 2), 2));

            \App\Models\Shared\LocationChangeRequest::whereIn('id', $approvedChanges->pluck('id'))->update(['is_settled' => true]);

            return [
                'contract_number'        => "REQ-{$subscription->id}",
                'total_contract_price'   => $totalContractPrice,
                'planned_trips'          => $plannedTripsCount,
                'per_trip_cost'          => round($perTripCost, 2),
                'completed_trips'        => $completedCount,
                'parent_absent_trips'    => $parentAbsentCount,
                'driver_absent_trips'    => $driverAbsentCount,
                'holidays_trips'         => $holidaysCount,
                'location_changes_count' => $locationChangesCount,
                'location_changes_fees'  => $locationChangesFees,
                'final_settled_amount'   => $finalAmount,
                'rollover_refund_credit' => $refundAmount,
            ];
        });
    }

    public function settleMonthlyContract($subscription): array
    {
        return $this->settleMonthlySubscription($subscription);
    }

    /**
     * الإلغاء المبكر للاشتراك في منتصف الشهر (Mid-Month Termination)
     */
    public function terminateSubscriptionMidMonth(SubscriptionRequest $subscription, string $terminatedBy, bool $isArbitraryByParent = false): array
    {
        return DB::transaction(function () use ($subscription, $terminatedBy, $isArbitraryByParent) {
            $totalPrice  = (float) ($subscription->total_amount_after_discount ?? $subscription->total_price ?? 0);
            $totalTrips  = max((int) ($subscription->days_count ?? 20), 1);
            $perTripCost = $totalPrice / $totalTrips;

            $tripsCompleted = Trip::whereHas('route', fn($q) => $q->where('subscription_request_id', $subscription->id))
                ->where('status', 'completed')
                ->count();

            // احتساب رسوم تغيير العنوان المعتمدة
            $activeSubIds = \App\Models\Shared\ActiveSubscription::where('subscription_request_id', $subscription->id)->pluck('id');
            $approvedChanges = \App\Models\Shared\LocationChangeRequest::whereIn('active_subscription_id', $activeSubIds)
                ->where('status', \App\Models\Shared\LocationChangeRequest::STATUS_APPROVED)
                ->get();
            $locationChangesCount = $approvedChanges->count();
            $locationChangesFees  = (float) $approvedChanges->sum('fee_amount');

            $executedCost = round(($tripsCompleted * $perTripCost) + $locationChangesFees, 2);
            $remaining    = max(0, round($totalPrice - $executedCost, 2));

            $cancellationFee = 0;
            if ($isArbitraryByParent && $remaining > 0) {
                $cancellationFee = round($remaining * 0.10, 2); // خصم 10% رسوم إلغاء مبكر
            }

            $refundToParent = max(0, round($remaining - $cancellationFee, 2));

            if ($refundToParent > 0 && $subscription->parent) {
                // (int) round(...) إلزامي: المحفظة تخزّن قروشاً صحيحة، وضرب float في 100
                // ينتج قيماً مثل 9154.999... تُقتطع لقرش ناقص أو ترفضها المحفظة.
                $subscription->parent->deposit((int) round($refundToParent * 100));
            }

            \App\Models\Shared\LocationChangeRequest::whereIn('id', $approvedChanges->pluck('id'))->update(['is_settled' => true]);

            $subscription->update(['status' => 'cancelled']);

            return [
                'contract_id'            => $subscription->id,
                'executed_cost'          => $executedCost,
                'remaining_balance'      => $remaining,
                'cancellation_fee'       => $cancellationFee,
                'refunded_to_parent'     => $refundToParent,
                'location_changes_count' => $locationChangesCount,
                'location_changes_fees'  => $locationChangesFees,
            ];
        });
    }

    public function terminateContractMidMonth($subscription, string $terminatedBy, bool $isArbitraryByParent = false): array
    {
        return $this->terminateSubscriptionMidMonth($subscription, $terminatedBy, $isArbitraryByParent);
    }

    /**
     * 4️⃣ فتح نزاع مالي وتجميد الحركة (24h Dispute Window)
     */
    public function openDispute(int $tripId, int $parentUserId, string $reason): TripDispute
    {
        $hold = TripEscrowHold::where('trip_id', $tripId)->firstOrFail();

        if ($hold->created_at->diffInHours(now()) > self::ESCROW_HOLD_HOURS) {
            throw ValidationException::withMessages([
                'dispute' => ['انتهت المهلة المحددة لتقديم النزاع (24 ساعة من الرحلة).'],
            ]);
        }

        $hold->update(['hold_status' => 'disputed', 'disputed_at' => now()]);

        return TripDispute::create([
            'trip_id'   => $tripId,
            'parent_id' => $parentUserId,
            'driver_id' => $hold->driver_id,
            'reason'    => $reason,
            'status'    => 'open',
        ]);
    }

    /**
     * حل النزاع المالي بواسطة الأدمن (Admin Resolution)
     */
    public function resolveDispute(int $disputeId, int $adminId, string $resolution, ?string $notes = null): TripDispute
    {
        return DB::transaction(function () use ($disputeId, $adminId, $resolution, $notes) {
            $dispute = TripDispute::findOrFail($disputeId);
            $hold = TripEscrowHold::where('trip_id', $dispute->trip_id)->firstOrFail();

            if ($resolution === 'resolve_parent_refunded') {
                $parent = ParentModel::where('user_id', $dispute->parent_id)->first() ?? ParentModel::find($dispute->parent_id);
                if ($parent) {
                    $parent->deposit($hold->amount);
                }
                $hold->update(['hold_status' => 'refunded']);
                $dispute->update([
                    'status'           => 'resolved_parent_refunded',
                    'resolution_notes' => $notes ?? 'تم إرجاع المبلغ لولي الأمر وتغريم السائق.',
                    'resolved_by'      => $adminId,
                    'resolved_at'      => now(),
                ]);
            } else {
                $vault = MasterEscrowVault::getVault();
                $commission   = (int) round($hold->amount * $this->commissionRate());
                $netDriverPay = $hold->amount - $commission;

                $vault->increment('platform_revenue_pool', $commission);
                $vault->increment('driver_available_pool', $netDriverPay);

                $driver = Driver::find($dispute->driver_id);
                if ($driver) {
                    $driver->deposit($netDriverPay);
                }

                $hold->update(['hold_status' => 'released_available']);
                $dispute->update([
                    'status'           => 'resolved_driver_paid',
                    'resolution_notes' => $notes ?? 'تم رفض الشكوى وتحويل الأرباح للسائق.',
                    'resolved_by'      => $adminId,
                    'resolved_at'      => now(),
                ]);
            }

            $freshDispute = $dispute->fresh(['parent.user', 'driver.user']);

            // 🔔 إرسال إشعار لولي الأمر والسائق بحل النزاع المالي
            try {
                $parentUser = $freshDispute->parent?->user ?? User::find($dispute->parent_id);
                $driverUser = $freshDispute->driver?->user ?? Driver::find($dispute->driver_id)?->user;

                if ($parentUser && $this->notificationService) {
                    $parentMsg = $resolution === 'resolve_parent_refunded'
                        ? 'تمت مراجعة النزاع المالي بقرار الإدارة وإعادة المبلغ إلى محفظتك بنجاح.'
                        : 'تمت مراجعة النزاع المالي من قبل الإدارة واعتماد مستحقات الرحلة.';
                    
                    $this->notificationService->sendToUser($parentUser, 'dispute_resolved', [
                        'title'       => '⚖️ نتيجة مراجعة النزاع المالي',
                        'message'     => $parentMsg,
                        'entity_type' => 'dispute',
                        'entity_id'   => (string) $dispute->id,
                        'screen'      => 'DISPUTE_DETAILS',
                    ]);
                }

                if ($driverUser && $this->notificationService) {
                    $driverMsg = $resolution === 'resolve_driver_paid'
                        ? 'تمت مراجعة النزاع المالي بقرار الإدارة وتحويل أرباح الرحلة إلى محفظتك.'
                        : 'تمت مراجعة النزاع المالي من قبل الإدارة وإعادة المبلغ لولي الأمر.';
                    
                    $this->notificationService->sendToUser($driverUser, 'dispute_resolved', [
                        'title'       => '⚖️ نتيجة مراجعة النزاع المالي',
                        'message'     => $driverMsg,
                        'entity_type' => 'dispute',
                        'entity_id'   => (string) $dispute->id,
                        'screen'      => 'DISPUTE_DETAILS',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("فشل إرسال إشعار حل النزاع المالي: " . $e->getMessage());
            }

            return $freshDispute;
        });
    }

    /**
     * معاينة حسابات الإلغاء المبكر للاشتراك دون تعديل البيانات
     */
    public function previewSubscriptionTermination(SubscriptionRequest $subscription, string $terminatedBy, bool $isArbitraryByParent = false): array
    {
        $totalPrice  = (float) ($subscription->total_amount_after_discount ?? $subscription->total_price ?? 0);
        $totalTrips  = max((int) ($subscription->days_count ?? 20), 1);
        $perTripCost = $totalPrice / $totalTrips;

        $tripsCompleted = Trip::whereHas('route', fn($q) => $q->where('subscription_request_id', $subscription->id))
            ->where('status', 'completed')
            ->count();

        $executedCost = round($tripsCompleted * $perTripCost, 2);
        $remaining    = max(0, round($totalPrice - $executedCost, 2));

        $cancellationFee = 0;
        if ($isArbitraryByParent && $remaining > 0) {
            $cancellationFee = round($remaining * 0.10, 2);
        }

        $refundToParent = max(0, round($remaining - $cancellationFee, 2));

        return [
            'contract_id'        => $subscription->id,
            'contract_number'    => "REQ-{$subscription->id}",
            'total_price'        => $totalPrice,
            'executed_cost'      => $executedCost,
            'remaining_balance'  => $remaining,
            'cancellation_fee'   => $cancellationFee,
            'refunded_to_parent' => $refundToParent,
        ];
    }

    public function previewContractTermination($subscription, string $terminatedBy, bool $isArbitraryByParent = false): array
    {
        return $this->previewSubscriptionTermination($subscription, $terminatedBy, $isArbitraryByParent);
    }

    /**
     * معاينة مصفوفة الغرامات لإلغاء رحلة دون تعديل البيانات
     */
    public function previewTripCancellation(Trip $trip, string $cancelledBy): array
    {
        $route = $trip->route;
        $subscription = $route?->subscriptionRequest;
        $tripPriceDinar = (float) ($subscription?->total_price ? ($subscription->total_price / max($subscription->days_count, 1)) : ($subscription?->trip_price ?? 25.00));

        $parentRefundDinar = 0;
        $driverPayDinar = 0;
        $platformFeeDinar = 0;
        $driverPenaltyDinar = 0;

        if ($cancelledBy === 'parent') {
            $parentRefundDinar = $tripPriceDinar;
        } elseif ($cancelledBy === 'no_show') {
            $platformFeeDinar = round($tripPriceDinar * $this->commissionRate(), 2);
            $driverPayDinar = round($tripPriceDinar - $platformFeeDinar, 2);
        } elseif ($cancelledBy === 'driver') {
            $parentRefundDinar = $tripPriceDinar;
            $driverPenaltyDinar = round($tripPriceDinar * 0.20, 2);
        }

        return [
            'trip_id'               => $trip->id,
            'cancelled_by'          => $cancelledBy,
            'trip_price_dinar'      => round($tripPriceDinar, 2),
            'parent_refund_dinar'   => round($parentRefundDinar, 2),
            'driver_pay_dinar'      => round($driverPayDinar, 2),
            'platform_amount_dinar' => round($platformFeeDinar, 2),
            'penalty_dinar'         => round($driverPenaltyDinar, 2),
        ];
    }
}
