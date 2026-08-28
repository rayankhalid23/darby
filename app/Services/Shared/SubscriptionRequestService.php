<?php

namespace App\Services\Shared;

use App\Models\Shared\SubscriptionRequest;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Shared\ActiveSubscription;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Database\QueryException;

use App\Models\Shared\PricingSetting;
use Throwable;

use Carbon\Carbon;
use Exception;

class SubscriptionRequestService
{
    protected \App\Services\Trip\MasterRouteStopSyncService $masterRouteStopSyncService;
    protected NotificationService $notificationService;

    public function __construct(
        \App\Services\Trip\MasterRouteStopSyncService $masterRouteStopSyncService,
        NotificationService $notificationService
    ) {
        $this->masterRouteStopSyncService = $masterRouteStopSyncService;
        $this->notificationService = $notificationService;
    }
    public function createRequest(array $data, $user): SubscriptionRequest
    {
        $subscriptionRequest = DB::transaction(function () use ($data, $user) {
            $parentId = null;

            if (is_object($user)) {
                if (method_exists($user, 'parent') && $user->parent) {
                    $parentId = $user->parent->id;
                } elseif (isset($user->user_id)) { 
                    $parentId = $user->id;
                } else {
                    $parentId = DB::table('parents')->where('user_id', $user->id)->value('id');
                }
            } elseif (is_numeric($user)) {
                $parentId = DB::table('parents')
                    ->where('id', $user)
                    ->orWhere('user_id', $user)
                    ->value('id');
            }

            if (!$parentId) {
                throw new \InvalidArgumentException("حساب ولي الأمر (Parent Profile) غير مكتمل أو غير موجود لهذا المستخدم.");
            }

            // 1. جلب إعدادات التسعير من قاعدة البيانات
            $pricingSetting = PricingSetting::first();
            $discountOne       = (float) ($pricingSetting->discount_one_child ?? 0.00);
            $discountTwo       = (float) ($pricingSetting->discount_two_children ?? 10.00);
            $discountThreePlus = (float) ($pricingSetting->discount_three_plus_children ?? 15.00);
            $commissionRate    = (float) ($pricingSetting->platform_commission_rate ?? 8.00);

            // تحديد نسبة الخصم بناءً على إجمالي عدد الأطفال بالطلب
            $childrenCount = count($data['children']);
            $discountPercent = match (true) {
                $childrenCount === 1 => 0.0, // لا يوجد تخفيض إذا كان الطلب لطفل واحد
                $childrenCount === 2 => $discountTwo,
                $childrenCount >= 3  => $discountThreePlus,
                default              => 0.0,
            };

            $totalOrderRawPrice = 0.0;
            $totalOrderDiscount = 0.0;
            $totalOrderAmountAfterDiscount = 0.0;
            $childrenPivotData = [];

            foreach ($data['children'] as $child) {
                $workingDays = $this->calculateWorkingDays(
                    $child['start_date'], 
                    $child['end_date'] ?? $child['start_date']
                );

                // إجمالي سعر الطفل قبل التخفيض وسعر الرحلة
                $childRawPrice = (float) ($child['price_per_child'] ?? $child['trip_price'] ?? 0);
                $tripPrice     = (float) ($child['trip_price'] ?? $childRawPrice);

                // حساب قيمة التخفيض للطفل
                $childDiscount = round(($childRawPrice * $discountPercent) / 100, 2);
                
                // السعر بعد التخفيض (أو السعر الأصلي كاملاً إن لم يكن هناك تخفيض)
                $childTotalAfterDiscount = max(0, round($childRawPrice - $childDiscount, 2));

                // حساب عمولة المنصة وصافي أرباح السائق للطفل الواحد
                $platformCommission = round(($childTotalAfterDiscount * $commissionRate) / 100, 2);
                $driverNetPrice     = max(0, round($childTotalAfterDiscount - $platformCommission, 2));

                $totalOrderRawPrice           += $childRawPrice;
                $totalOrderDiscount           += $childDiscount;
                $totalOrderAmountAfterDiscount += $childTotalAfterDiscount;

                $childrenPivotData[$child['child_id']] = [
                    'subscription_type'           => $child['subscription_type'],
                    'trip_direction'              => $child['trip_direction'] ?? $child['direction'] ?? 'both',
                    'timing'                      => $child['timing'] ?? 'BOTH',
                    'start_date'                  => $child['start_date'],
                    'end_date'                    => $child['end_date'] ?? $child['start_date'],
                    'working_days_count'          => $workingDays,
                    'distance_km'                 => $child['distance_km'] ?? 0,
                    'trip_price'                  => $tripPrice,
                    'price_per_child'             => $childRawPrice,
                    'discount_amount'             => $childDiscount,
                    'total_amount_after_discount' => $childTotalAfterDiscount,
                    'driver_net_price'            => $driverNetPrice,
                    'created_at'                  => now(),
                    'updated_at'                  => now(),
                ];
            }

            $parentModel = ParentModel::with('wallet')->find($parentId);
            $hasSingleDay = collect($data['children'])->contains('subscription_type', 'single_day');
            if ($hasSingleDay) {
                $this->validateAndDeductWalletBalance($parentModel, $totalOrderAmountAfterDiscount);
            }

            // 2. إنشاء الطلب الرئيسي
            $subscriptionRequest = SubscriptionRequest::create([
                'parent_id'                   => $parentId,
                'driver_id'                   => $data['driver_id'],
                'status'                      => defined(SubscriptionRequest::class . '::STATUS_PENDING') ? SubscriptionRequest::STATUS_PENDING : 'pending',
                'total_price'                 => $totalOrderRawPrice,
                'discount_amount'             => $totalOrderDiscount,
                'total_amount_after_discount' => $totalOrderAmountAfterDiscount,
                'notes'                       => $data['notes'] ?? null,
            ]);

            // 3. ربط الأطفال بجدول الـ Pivot
            $subscriptionRequest->children()->sync($childrenPivotData);

            return $subscriptionRequest->load(['children.school', 'parent.user', 'driver.user']);
        });

        // 🔔 إرسال إشعار لحظي للسائق بوجود طلب اشتراك جديد
        try {
            $driverUser = $subscriptionRequest->driver?->user;
            if ($driverUser) {
                $parentName = $subscriptionRequest->parent?->user?->full_name ?? 'ولي الأمر';
                $this->notifyUser(
                    $driverUser,
                    'طلب اشتراك جديد 🆕',
                    "لديك طلب اشتراك جديد رقم #{$subscriptionRequest->id} من [{$parentName}] بانتظار المراجعة.",
                    'new_subscription_request',
                    (string) $subscriptionRequest->id,
                    ['request_id' => (string) $subscriptionRequest->id]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار طلب الاشتراك الجديد للسائق: " . $e->getMessage());
        }

        return $subscriptionRequest;
    }
   

    /**
     * حساب عدد أيام العمل الفعلية (استثناء الجمعة والسبت)
     */
    public function calculateWorkingDays(string $startDateStr, string $endDateStr): int
    {
        $start = Carbon::parse($startDateStr)->startOfDay();
        $end   = Carbon::parse($endDateStr)->startOfDay();

        if ($start->gt($end)) {
            return 0;
        }

        $workingDays = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (!$current->isFriday() && !$current->isSaturday()) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    
    



    // ============================================================
    // تحقق الفلتر 1: تطابق الفترة/الاتجاه مع تفضيلات السائق
    // ============================================================

    private function validateDriverShiftCompatibility(Driver $driver, string $timing, string $direction): void
    {
        // تحديد الـ slots المطلوبة حسب الطلب
        $requiredSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
        $slotLabels    = \App\Models\Driver\DriverSeatSlot::slotLabels();

        foreach ($requiredSlots as $slot) {
            if (!$driver->$slot) {
                throw new Exception(
                    "السائق لا يعمل في فترة [{$slotLabels[$slot]}]. يرجى اختيار سائق يغطي هذه الفترة."
                );
            }
        }
    }

    // ============================================================
    // تحقق الفلتر 2: توفر المقاعد لكل slot مطلوبة
    // ============================================================

    private function validateSeatAvailability(Driver $driver, string $timing, string $direction, int $childrenCount): void
    {
        $requiredSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
        $slotLabels    = \App\Models\Driver\DriverSeatSlot::slotLabels();

        $driver->loadMissing('seatSlots');

        foreach ($requiredSlots as $slot) {
            $seatSlot  = $driver->seatSlots->firstWhere('slot', $slot);
            $available = $seatSlot ? $seatSlot->available_seats : 0;

            if ($available < $childrenCount) {
                throw new Exception(
                    "لا توجد مقاعد كافية في فترة [{$slotLabels[$slot]}]. المتاح: {$available} مقعد، المطلوب: {$childrenCount}."
                );
            }
        }
    }

    // ============================================================
    // تحقق المقاعد مع الوعي الزمني (يحل: التعارض المستقبلي + الأيام الجزئية + اللا-تداخل)
    // ============================================================

    /**
     * يتحقق من أن عدد المقاعد المتاحة يكفي في كل يوم عمل ضمن فترة الاشتراك.
     * يُستخدم عند القبول والفحص الدوري — أدق من الفحص اللحظي.
     */
    private function validateSeatAvailabilityForPeriod(
        Driver $driver,
        string $timing,
        string $direction,
        int    $childrenCount,
        string $startDate,
        string $endDate
    ): void {
        $requiredSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
        $slotLabels    = \App\Models\Driver\DriverSeatSlot::slotLabels();
        $driver->loadMissing('seatSlots');

        foreach ($requiredSlots as $slot) {
            $seatSlot         = $driver->seatSlots->firstWhere('slot', $slot);
            $slotCapacity     = $seatSlot?->total_seats ?? ($driver->vehicle?->capacity_manual ?? 0);
            $slotAvailableNow = $seatSlot ? $seatSlot->available_seats : $slotCapacity;

            $peak            = $this->computeSlotPeakConcurrency($driver->id, $slot, $startDate, $endDate);
            $availableByPeak = max(0, $slotCapacity - $peak);

            $available = min($slotAvailableNow, $availableByPeak);

            if ($available < $childrenCount) {
                $label = $slotLabels[$slot] ?? $slot;
                throw new Exception(
                    "لا توجد مقاعد كافية في فترة [{$label}] خلال مدة الاشتراك المطلوبة. المتاح: {$available}، المطلوب: {$childrenCount}."
                );
            }
        }
    }

    /**
     * يحسب أعلى عدد اشتراكات نشطة متزامنة لنفس الـ slot في أي يوم عمل واحد
     * ضمن الفترة المطلوبة — يكشف التعارض في الأيام الجزئية والاشتراكات المستقبلية.
     */
    private function computeSlotPeakConcurrency(int $driverId, string $slot, string $startDate, string $endDate): int
    {
        $overlapping = ActiveSubscription::where('driver_id', $driverId)
            ->where('status', 'active')
            ->whereHas('subscriptionRequest.children', function ($q) use ($startDate, $endDate) {
                $q->where('request_children.start_date', '<=', $endDate)
                  ->where('request_children.end_date', '>=', $startDate);
            })
            ->with(['subscriptionRequest.children', 'child'])
            ->get(['id', 'subscription_request_id', 'child_id'])
            ->filter(function ($sub) use ($slot) {
                $subReq = $sub->subscriptionRequest;
                if (!$subReq) {
                    return false;
                }
                $childPivot = $subReq->children?->firstWhere('id', $sub->child_id)?->pivot ?? $subReq->children?->first()?->pivot;
                $timing = $childPivot?->timing ?? $subReq->timing ?? 'MORNING';
                $direction = $childPivot?->trip_direction ?? $subReq->direction ?? 'both';

                $subSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
                return in_array($slot, $subSlots);
            });

        if ($overlapping->isEmpty()) {
            return 0;
        }

        $start    = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end      = \Carbon\Carbon::parse($endDate)->startOfDay();
        $maxCount = 0;
        $cur      = $start->copy();

        while ($cur->lte($end)) {
            if (!in_array($cur->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY])) {
                $dayStr   = $cur->toDateString();
                $dayCount = $overlapping->filter(function ($sub) use ($dayStr) {
                    $subReq = $sub->subscriptionRequest;
                    $childPivot = $subReq?->children?->firstWhere('id', $sub->child_id)?->pivot ?? $subReq?->children?->first()?->pivot;
                    $sStart = $childPivot?->start_date ?? $subReq?->start_date;
                    $sEnd   = $childPivot?->end_date ?? $subReq?->end_date;
                    if (!$sStart || !$sEnd) {
                        return false;
                    }
                    $sStartStr = ($sStart instanceof \DateTimeInterface) ? $sStart->format('Y-m-d') : (string) $sStart;
                    $sEndStr   = ($sEnd   instanceof \DateTimeInterface) ? $sEnd->format('Y-m-d')   : (string) $sEnd;
                    return $sStartStr <= $dayStr && $sEndStr >= $dayStr;
                })->count();
                $maxCount = max($maxCount, $dayCount);
            }
            $cur->addDay();
        }

        return $maxCount;
    }

    // ============================================================
    // 1. تحديث حالة الطلب (نقطة الدخول الرئيسية)
    // ============================================================

    public function updateStatus(
        SubscriptionRequest $subscriptionRequest,
        string $status,
        ?string $rejectionReason = null
    ): SubscriptionRequest {

        return DB::transaction(function () use ($subscriptionRequest, $status, $rejectionReason) {
            
            // تحميل العلاقات المطلوبة مسبقاً لتجنب استعلامات N+1
            $subscriptionRequest->loadMissing(['parent.user', 'children', 'school', 'driver.user']);
            $parent = $subscriptionRequest->parent;

            if ($status === SubscriptionRequest::STATUS_ACCEPTED) {
                return $this->handleAcceptance($subscriptionRequest, $parent);
            }

            if ($status === SubscriptionRequest::STATUS_REJECTED) {
                return $this->handleRejection($subscriptionRequest, $parent, $rejectionReason);
            }

            throw new Exception("الحالة المطلوبة '{$status}' غير مدعومة.");
        });
    }

    // ============================================================
    // 2. منطق القبول
    // ============================================================

    private function handleAcceptance(SubscriptionRequest $req, ?ParentModel $parent): SubscriptionRequest
    {
        // 1. التحقق من وجود وحالة مركبة السائق وسعتها قبل أي تعديل
        $vehicle = \App\Models\Driver\Vehicle::where('driver_id', $req->driver_id)
            ->where('status', 'Active')
            ->first();

        if (!$vehicle) {
            throw new Exception("تعذر إتمام العملية: لا توجد مركبة نشطة مرتبطة بالسائق.");
        }

        // التحقق من توفر المقاعد مع الوعي الزمني الكامل بفترة الاشتراك
        $firstChildPivot = $req->children?->first()?->pivot;
        $requiredSeats   = $req->children_count > 0 ? $req->children_count : ($req->children ? $req->children->count() : 1);
        $startDate       = $firstChildPivot?->start_date ? \Carbon\Carbon::parse($firstChildPivot->start_date)->toDateString() : ($req->start_date ? \Carbon\Carbon::parse($req->start_date)->toDateString() : now()->toDateString());
        $endDate         = $firstChildPivot?->end_date ? \Carbon\Carbon::parse($firstChildPivot->end_date)->toDateString() : ($req->end_date ? \Carbon\Carbon::parse($req->end_date)->toDateString() : $startDate);
        $timing          = $firstChildPivot?->timing ?? $req->timing ?? 'MORNING';
        $direction       = $firstChildPivot?->trip_direction ?? $req->direction ?? 'both';

        // أقفل صفوف المقاعد المعنية لمنع السباق (race condition) عند القبول المتزامن
        $requiredSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
        \App\Models\Driver\DriverSeatSlot::where('driver_id', $req->driver_id)
            ->whereIn('slot', $requiredSlots)
            ->lockForUpdate()
            ->get();

        $req->loadMissing('driver.seatSlots');
        $this->validateSeatAvailabilityForPeriod(
            $req->driver,
            $timing,
            $direction,
            $requiredSeats,
            $startDate,
            $endDate
        );

        // 2. تحديث حالة الطلب الحالي إلى مقبول مع توثيق وقت الاستجابة
        $req->update([
            'status'       => SubscriptionRequest::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        // 3. إلغاء الطلبات الأخرى المعلقة لنفس العميل ونفس التوقيت
        SubscriptionRequest::where('parent_id', $req->parent_id)
            ->where('status', SubscriptionRequest::STATUS_PENDING)
            ->where('id', '!=', $req->id)
            ->whereHas('children', function ($q) use ($timing) {
                $q->where('request_children.timing', $timing);
            })
            ->update(['status' => SubscriptionRequest::STATUS_CANCELLED]);

        // 4. تحديد الـ slot الأساسي (الفترة/الاتجاه) بناءً على تفضيلات السائق الثابتة
        $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);
        $primarySlot = $slots[0] ?? null;

        // 5. البحث عن مسار رئيسي (Master Route) نشط بالفعل لنفس السائق لنفس الفترة/الاتجاه
        //    حتى لا يُنشأ مسار جديد مع كل طلب اشتراك مقبول، بل يُضاف الطفل إلى المسار الثابت الموجود.
        $route = $primarySlot
            ? \App\Models\Shared\Route::where('driver_id', $req->driver_id)
                ->where('shift_slot', $primarySlot)
                ->where('status', 'Active')
                ->first()
            : null;

        if ($route) {
            // مسار موجود بالفعل لهذا السائق/الفترة: نعيد استخدامه فقط ونحدّث المركبة إن تغيّرت
            if ($route->vehicle_id !== $vehicle->id) {
                $route->vehicle_id = $vehicle->id;
                $route->save();
            }
        } else {
            // لا يوجد مسار سابق لهذا السائق/الفترة: ننشئه لأول مرة فقط
            $distanceKm = 0;
            $durationMinutes = 0;
            $routeData = null;

            try {
                $osrm = new \App\Services\Shared\OsrmRoutingService();

                $driverPos = ['lat' => (float)($req->driver->current_lat ?? 0), 'lng' => (float)($req->driver->current_lng ?? 0)];
                $childPos  = ['lat' => (float)($req->children->first()->pivot->home_lat ?? $req->pickup_lat ?? 0), 'lng' => (float)($req->children->first()->pivot->home_lng ?? $req->pickup_lng ?? 0)];
                $schoolPos = ['lat' => (float)($req->school->lat ?? $req->school->latitude ?? $req->dropoff_lat ?? 0), 'lng' => (float)($req->school->lng ?? $req->school->longitude ?? $req->dropoff_lng ?? 0)];

                $routeData = $osrm->calculateRoute([$driverPos, $childPos, $schoolPos]);

                if ($routeData) {
                    $distanceInMeters = $routeData['routes'][0]['distance'] ?? 0;
                    $durationInSeconds = $routeData['routes'][0]['duration'] ?? 0;

                    $distanceKm = round($distanceInMeters / 1000, 2);
                    $durationMinutes = (int) ceil($durationInSeconds / 60);
                }
            } catch (\Exception $e) {
                Log::warning("فشل حساب المسار عبر OSRM للطلب ID: {$req->id} - " . $e->getMessage());
            }

            $timingUpper = strtoupper($req->timing ?? 'MORNING');
            $routeType = ($timingUpper === 'EVENING' || $timingUpper === 'AFTERNOON') ? 'Afternoon' : 'Morning';

            $route = \App\Models\Shared\Route::create([
                'subscription_request_id' => $req->id,
                'driver_id'               => $req->driver_id,
                'vehicle_id'              => $vehicle->id,
                'route_name'              => \App\Models\Shared\Route::generateGenericRouteName($req->timing, $req->direction),
                'route_type'              => $routeType,
                'shift_slot'              => $primarySlot,
                'start_time'              => $req->pickup_time ?? '07:00:00',
                'optimized_points'        => $routeData ? json_encode($routeData) : null,
                'total_distance'          => $distanceKm,
                'estimated_duration'      => $durationMinutes,
                'status'                  => 'Active'
            ]);
        }

        // 6. تفعيل اشتراكات الأطفال لجدول active_subscriptions وتفريغ المقاعد
        $this->createActiveSubscriptions($req, $route);

        // 6.2 حجز مبلغ الاشتراك اليومي في الأمانات إن وُجد
        $hasSingleDay = $req->children->contains(function ($child) {
            return ($child->pivot->subscription_type ?? '') === 'single_day';
        }) || $req->subscription_type === 'single_day';

        if ($hasSingleDay && (float) $req->total_amount_after_discount > 0) {
            $this->holdSingleDayFundsOnAcceptance($req, $parent);
        }

        // 6.5 مزامنة المسار الرئيسي (Master Route) لكل فترة/اتجاه مطلوبة (route_stops)
        try {
            $this->masterRouteStopSyncService->syncOnAcceptance($req, $route, $slots);
        } catch (\Throwable $e) {
            Log::warning("فشل مزامنة المسار الرئيسي (route_stops) للطلب ID: {$req->id} - " . $e->getMessage());
        }

        // 7. إرسال إشعار القبول مع حمايته من إلغاء الـ Transaction
        try {
            if ($parent && $parent->user) {
                $this->notifyUser(
                    $parent->user,
                    'تم قبول طلب الاشتراك',
                    "تم قبول طلبك مع السائق " . ($req->driver->user->full_name ?? 'السائق') . ". رقم الطلب: #{$req->id}",
                    'request_accepted',
                    (string) $req->id,
                    ['request_id' => (string) $req->id]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار FCM عند قبول الطلب ID {$req->id}: " . $e->getMessage());
        }

        return $req->refresh()->load(['children', 'driver.user', 'parent.user']);
    }

    // ============================================================
    // 3. منطق الرفض
    // ============================================================

    private function handleRejection(SubscriptionRequest $req, ?ParentModel $parent, ?string $reason): SubscriptionRequest
    {
        $req->update([
            'status'           => SubscriptionRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);

        try {
            if ($parent && $parent->user) {
                $this->notifyUser(
                    $parent->user,
                    'تم رفض طلب الاشتراك',
                    "عذراً، تم رفض طلبك. السبب: " . ($reason ?? 'لم يحدد السائق سبباً.'),
                    'request_rejected',
                    (string) $req->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار FCM عند رفض الطلب ID {$req->id}: " . $e->getMessage());
        }

        return $req->refresh();
    }

    /**
     * التحقق من الرصيد في محفظة ولي الأمر ومنع إرسال الطلب في حال عدم كفايته
     * (لا يتم حجز أو خصم المبلغ إلا عند قبول السائق للطلب)
     */
    protected function validateAndDeductWalletBalance(?ParentModel $parent, float $totalPrice): void
    {
        if (!$parent) {
            throw new Exception("حساب ولي الأمر غير موجود.");
        }

        $requiredCents = (int) round($totalPrice * 100);
        $balanceCents  = (int) ($parent->balance ?? $parent->wallet?->balance ?? 0);

        if ($balanceCents < $requiredCents) {
            throw new Exception('عذراً، رصيد المحفظة غير كافٍ. يرجى شحن محفظتك بقيمة الرحلة لإتمام الطلب.');
        }
    }

    /**
     * حجز مبلغ الاشتراك اليومي ونقله للأمانات عند قبول السائق للطلب
     */
    protected function holdSingleDayFundsOnAcceptance(SubscriptionRequest $req, ?ParentModel $parent): void
    {
        if (!$parent) {
            $parent = ParentModel::find($req->parent_id) ?? ParentModel::where('user_id', $req->parent_id)->first();
        }
        if (!$parent) {
            throw new Exception("تعذر العثور على حساب ولي الأمر لحجز قيمة الرحلة.");
        }

        $amountDinar = (float) $req->total_amount_after_discount;
        $amountCents = (int) round($amountDinar * 100);

        $currentBalance = (int) ($parent->balance ?? $parent->wallet?->balance ?? 0);
        if ($currentBalance < $amountCents) {
            throw new Exception("تعذر قبول الطلب: رصيد محفظة ولي الأمر غير كافٍ لحجز مبلغ الرحلة.");
        }

        $balBefore = $currentBalance;
        $parent->withdraw($amountCents);
        $balAfter = (int) $parent->balance;

        $vault = \App\Models\Shared\MasterEscrowVault::getVault();
        $vault->increment('parents_escrow_pool', $amountCents);

        $pricingSetting = PricingSetting::first();
        $commissionRate = (float) ($pricingSetting->platform_commission_rate ?? 8.00);
        $commissionAmount = round(($amountDinar * $commissionRate) / 100, 2);
        $driverNetAmount = max(0, round($amountDinar - $commissionAmount, 2));

        $platformFinance = \App\Models\Shared\PlatformFinance::create([
            'subscription_request_id'    => $req->id,
            'parent_id'                  => $parent->id,
            'driver_id'                  => $req->driver_id,
            'total_amount'               => $amountDinar,
            'platform_commission_rate'   => $commissionRate,
            'platform_commission_amount' => $commissionAmount,
            'driver_net_amount'          => $driverNetAmount,
            'status'                     => \App\Models\Shared\PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        try {
            app(\App\Services\Shared\FinancialLedgerService::class)->recordLedgerEntry(
                "parent_wallet_{$parent->user_id}",
                "parents_escrow_pool",
                $amountCents,
                'subscription_hold',
                $balBefore,
                $balAfter,
                "REQ-HOLD-{$req->id}",
                [
                    'subscription_request_id' => $req->id,
                    'platform_finance_id'     => $platformFinance->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("فشل تسجيل حركة السجل المالي لحجز الاشتراك ID {$req->id}: " . $e->getMessage());
        }
    }

    /**
     * معالجة استرجاع المبالغ المحجوزة عند إلغاء الاشتراك وفق سياسة التعويض بعد تحرك السائق
     */
    public function refundHeldFundsOnCancellation(int $requestId, string $cancelledBy, ?int $driverId = null): ?array
    {
        $finance = \App\Models\Shared\PlatformFinance::where('subscription_request_id', $requestId)
            ->where('status', \App\Models\Shared\PlatformFinance::STATUS_HELD)
            ->first();

        if (!$finance) {
            return null;
        }

        $parent = ParentModel::find($finance->parent_id) ?? ParentModel::where('user_id', $finance->parent_id)->first();
        $driver = Driver::find($finance->driver_id) ?? Driver::where('user_id', $finance->driver_id)->first();
        $vault = \App\Models\Shared\MasterEscrowVault::getVault();

        // فحص هل السائق تحرك بالفعل (بدأت الرحلة الفعلية) أم لا
        $hasDriverMoved = false;
        if ($finance->trip_id) {
            $trip = \App\Models\Shared\Trip::find($finance->trip_id);
            $hasDriverMoved = $trip && ($trip->status === 'in_progress' || !empty($trip->actual_start_time));
        } else {
            $hasDriverMoved = \App\Models\Shared\Trip::where('driver_id', $finance->driver_id)
                ->where('status', 'in_progress')
                ->whereDate('trip_date', now()->toDateString())
                ->exists();
        }

        $totalDinar = (float) $finance->total_amount;
        $totalCents = (int) round($totalDinar * 100);

        // إذا تحرك السائق الفعلي وكان الإلغاء من ولي الأمر:
        if ($hasDriverMoved && $cancelledBy === 'parent') {
            $nominalCompDinar = min(\App\Models\Shared\PlatformFinance::NOMINAL_FUEL_COMPENSATION, $totalDinar);
            $commissionRate = (float) ($finance->platform_commission_rate ?? 8.00);
            $commissionOnComp = round(($nominalCompDinar * $commissionRate) / 100, 2);
            $driverNetComp = max(0, round($nominalCompDinar - $commissionOnComp, 2));
            $refundToParent = max(0, round($totalDinar - $nominalCompDinar, 2));

            $refundCents = (int) round($refundToParent * 100);
            $driverCompCents = (int) round($driverNetComp * 100);
            $commissionCents = (int) round($commissionOnComp * 100);

            $vault->decrement('parents_escrow_pool', $totalCents);

            if ($refundCents > 0 && $parent) {
                $parent->deposit($refundCents);
            }

            if ($driverCompCents > 0 && $driver) {
                $driver->deposit($driverCompCents);
            }

            if ($commissionCents > 0) {
                $vault->increment('platform_revenue_pool', $commissionCents);
            }

            $finance->update([
                'status'                     => \App\Models\Shared\PlatformFinance::STATUS_PARTIALLY_REFUNDED,
                'compensation_fee'           => $nominalCompDinar,
                'platform_commission_amount' => $commissionOnComp,
                'driver_net_amount'          => $driverNetComp,
                'refunded_amount'            => $refundToParent,
                'refunded_at'                => now(),
                'notes'                      => 'تم إلغاء الرحلة بعد تحرك السائق. تم خصم تعويض وقود للسائق واقتطاع عمولة المنصة منه وإرجاع باقي المبلغ لولي الأمر.',
            ]);

            try {
                $ledger = app(\App\Services\Shared\FinancialLedgerService::class);
                if ($refundCents > 0 && $parent) {
                    $ledger->recordLedgerEntry(
                        'parents_escrow_pool',
                        "parent_wallet_{$parent->user_id}",
                        $refundCents,
                        'subscription_refund',
                        0,
                        (int) $parent->balance,
                        "REFUND-PARTIAL-{$requestId}",
                        ['subscription_request_id' => $requestId, 'cancelled_by' => $cancelledBy]
                    );
                }
                if ($driverCompCents > 0 && $driver) {
                    $ledger->recordLedgerEntry(
                        'parents_escrow_pool',
                        "driver_wallet_{$driver->id}",
                        $driverCompCents,
                        'driver_fuel_compensation',
                        0,
                        (int) $driver->balance,
                        "COMP-DRIVER-{$requestId}",
                        ['subscription_request_id' => $requestId]
                    );
                }
                if ($commissionCents > 0) {
                    $ledger->recordLedgerEntry(
                        'parents_escrow_pool',
                        'platform_revenue_pool',
                        $commissionCents,
                        'platform_commission',
                        0,
                        $commissionCents,
                        "COMMISSION-COMP-{$requestId}"
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("فشل تسجيل حركات السجل المالي للإلغاء الجزئي ID {$requestId}: " . $e->getMessage());
            }

            return [
                'refund_amount'     => $refundToParent,
                'compensation_fee'  => $nominalCompDinar,
                'driver_net_pay'    => $driverNetComp,
                'platform_fee'      => $commissionOnComp,
                'status'            => 'partially_refunded',
            ];
        } else {
            // قبل تحرك السائق أو إذا كان الإلغاء من السائق أو تلقائياً -> استرجاع كامل 100% لولي الأمر
            $vault->decrement('parents_escrow_pool', $totalCents);

            if ($parent) {
                $parent->deposit($totalCents);
            }

            $finance->update([
                'status'                     => \App\Models\Shared\PlatformFinance::STATUS_REFUNDED,
                'refunded_amount'            => $totalDinar,
                'compensation_fee'           => 0.00,
                'platform_commission_amount' => 0.00,
                'driver_net_amount'          => 0.00,
                'refunded_at'                => now(),
                'notes'                      => 'تم استرجاع كامل المبلغ لولي الأمر (إلغاء قبل تحرك السائق أو إلغاء من السائق).',
            ]);

            try {
                app(\App\Services\Shared\FinancialLedgerService::class)->recordLedgerEntry(
                    'parents_escrow_pool',
                    "parent_wallet_{$parent?->user_id}",
                    $totalCents,
                    'subscription_refund',
                    0,
                    (int) ($parent?->balance ?? 0),
                    "REFUND-FULL-{$requestId}",
                    ['subscription_request_id' => $requestId, 'cancelled_by' => $cancelledBy]
                );
            } catch (\Throwable $e) {
                Log::warning("فشل تسجيل حركة السجل المالي للاسترجاع الكامل ID {$requestId}: " . $e->getMessage());
            }

            return [
                'refund_amount'    => $totalDinar,
                'compensation_fee' => 0.00,
                'driver_net_pay'   => 0.00,
                'platform_fee'     => 0.00,
                'status'           => 'refunded',
            ];
        }
    }









    // ============================================================
    // 4. إنشاء سجلات الاشتراكات النشطة (مطابق لجدول active_subscriptions)
    // ============================================================

    private function createActiveSubscriptions(SubscriptionRequest $req, ?\App\Models\Shared\Route $route = null): void
    {
        $pickupTime  = $req->pickup_time  ?? '07:00:00';
        $dropoffTime = $req->dropoff_time ?? '14:00:00';
        $parentUserId = $req->parent?->user_id ?? $req->parent_id;

        foreach ($req->children as $child) {
            $pickupLat  = $child->pivot->home_lat   ?? $req->pickup_lat   ?? null;
            $pickupLng  = $child->pivot->home_lng   ?? $req->pickup_lng   ?? null;
            $pickupLbl  = $child->pivot->home_label ?? $req->pickup_label ?? 'الموقع السكني';

            $dropoffLat = $child->pivot->school_lat   ?? $req->school->lat       ?? $req->school->latitude  ?? $req->dropoff_lat ?? null;
            $dropoffLng = $child->pivot->school_lng   ?? $req->school->lng       ?? $req->school->longitude ?? $req->dropoff_lng ?? null;
            $dropoffLbl = $child->pivot->school_label ?? $req->school->name      ?? $req->dropoff_label     ?? 'المدرسة';

            ActiveSubscription::create([
                'subscription_request_id' => $req->id,
                'route_id'                => $route?->id,
                'status'                  => 'active',
                'child_id'                => $child->id,
                'driver_id'               => $req->driver_id,
                'parent_id'               => $parentUserId,
                'pickup_lat'              => $pickupLat,
                'pickup_lng'              => $pickupLng,
                'pickup_label'            => $pickupLbl,
                'pickup_time'             => $pickupTime,
                'dropoff_lat'             => $dropoffLat,
                'dropoff_lng'             => $dropoffLng,
                'dropoff_label'           => $dropoffLbl,
                'dropoff_time'            => $dropoffTime,
            ]);

            // زيادة عداد المقاعد المحجوزة لكل slot خاصة بهذا الطفل
            $childTiming    = $child->pivot->timing ?? $req->timing ?? 'MORNING';
            $childDirection = $child->pivot->trip_direction ?? $req->direction ?? 'both';
            $childSlots     = \App\Models\Driver\DriverSeatSlot::resolveSlots($childTiming, $childDirection);

            foreach ($childSlots as $slot) {
                \App\Models\Driver\DriverSeatSlot::where('driver_id', $req->driver_id)
                    ->where('slot', $slot)
                    ->increment('reserved_seats');
            }
        }
    }

    // ============================================================
    // 5. تغيير حالة الاشتراك النشط (مفعل، معلق، مكتمل، ملغي)
    // ============================================================

    public function updateActiveSubscriptionStatus(int $activeSubscriptionId, string $status): ActiveSubscription
    {
        $allowedStatuses = ['active', 'pending', 'completed', 'cancelled'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception("حالة غير صالحة. المسموح: " . implode(', ', $allowedStatuses));
        }

        return DB::transaction(function () use ($activeSubscriptionId, $status) {
            $activeSub = ActiveSubscription::lockForUpdate()->find($activeSubscriptionId);
            if (!$activeSub) {
                throw new Exception('الاشتراك النشط غير موجود.');
            }

            if (in_array($activeSub->status, ['cancelled', 'completed'])) {
                throw new Exception("لا يمكن تعديل اشتراك بحالة [{$activeSub->status}].");
            }

            $activeSub->update(['status' => $status]);

            if (in_array($status, ['cancelled', 'completed'])) {
                $this->releaseSeatsForSubscription($activeSub);
                try {
                    $this->masterRouteStopSyncService->removeChildFromDriverRoutes($activeSub);
                } catch (\Throwable $e) {
                    Log::warning("فشل تحديث المسار ID: {$activeSub->id} — " . $e->getMessage());
                }
            }

            return $activeSub->load(['subscriptionRequest', 'child', 'driver.user']);
        });
    }

    // ============================================================
    // إلغاء الاشتراك النشط — ولي الأمر
    // ============================================================

    public function cancelActiveSubscriptionByParent(int $activeSubscriptionId, int $userId): ActiveSubscription
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $activeSub = ActiveSubscription::where('id', $activeSubscriptionId)
            ->where(function ($q) use ($userId, $parent) {
                $q->where('parent_id', $parent->id)
                  ->orWhere('parent_id', $userId);
            })
            ->first();

        if (!$activeSub) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        if ($activeSub->status === 'cancelled') {
            throw new Exception('هذا الاشتراك ملغى بالفعل.');
        }
        if ($activeSub->status === 'completed') {
            throw new Exception('لا يمكن إلغاء اشتراك مكتمل.');
        }

        return DB::transaction(function () use ($activeSub) {
            $activeSub->update(['status' => 'cancelled']);

            $this->releaseSeatsForSubscription($activeSub);

            // معالجة استرجاع الرصيد المحجوز إن وُجد وفق سياسة التعويض
            if ($activeSub->subscription_request_id) {
                $this->refundHeldFundsOnCancellation($activeSub->subscription_request_id, 'parent');
            }

            try {
                $this->masterRouteStopSyncService->removeChildFromDriverRoutes($activeSub);
            } catch (\Throwable $e) {
                Log::warning("فشل تحديث المسار (إلغاء ولي الأمر) ID: {$activeSub->id} — " . $e->getMessage());
            }

            // إشعار السائق
            $activeSub->loadMissing(['driver.user', 'child']);
            $driverUser = $activeSub->driver?->user;
            if ($driverUser) {
                $childName = $activeSub->child?->full_name ?? 'الطفل';
                $this->notifyUser(
                    $driverUser,
                    'إلغاء اشتراك من قِبل ولي الأمر',
                    "قام ولي الأمر بإلغاء اشتراك الطفل [{$childName}].",
                    'subscription_cancelled_by_parent',
                    (string) $activeSub->id
                );
            }

            return $activeSub->fresh(['subscriptionRequest', 'child', 'driver.user']);
        });
    }

    // ============================================================
    // إلغاء الاشتراك النشط — السائق
    // ============================================================

    public function cancelActiveSubscriptionByDriver(int $activeSubscriptionId, int $driverId, ?string $reason = null): ActiveSubscription
    {
        $activeSub = ActiveSubscription::where('id', $activeSubscriptionId)
            ->where('driver_id', $driverId)
            ->first();

        if (!$activeSub) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        if ($activeSub->status === 'cancelled') {
            throw new Exception('هذا الاشتراك ملغى بالفعل.');
        }
        if ($activeSub->status === 'completed') {
            throw new Exception('لا يمكن إلغاء اشتراك مكتمل.');
        }

        return DB::transaction(function () use ($activeSub, $driverId, $reason) {
            $activeSub->update(['status' => 'cancelled']);

            $this->releaseSeatsForSubscription($activeSub);

            // استرجاع كامل المبلغ لولي الأمر عند إلغاء السائق
            if ($activeSub->subscription_request_id) {
                $this->refundHeldFundsOnCancellation($activeSub->subscription_request_id, 'driver', $driverId);
            }

            try {
                $this->masterRouteStopSyncService->removeChildFromDriverRoutes($activeSub);
            } catch (\Throwable $e) {
                Log::warning("فشل تحديث المسار (إلغاء السائق) ID: {$activeSub->id} — " . $e->getMessage());
            }

            // إشعار ولي الأمر عبر parent_id (users.id)
            $activeSub->loadMissing(['parent', 'child', 'driver.user']);
            $parentUser = $activeSub->parent; // العلاقة ترجع User مباشرة
            if ($parentUser) {
                $driverName = $activeSub->driver?->user?->full_name ?? 'السائق';
                $childName  = $activeSub->child?->full_name ?? 'الطفل';
                $body       = "أعلمك السائق [{$driverName}] بإلغاء اشتراك طفلك [{$childName}].";
                if ($reason) {
                    $body .= " السبب: {$reason}";
                }
                $this->notifyUser(
                    $parentUser,
                    'إلغاء اشتراك من قِبل السائق',
                    $body,
                    'subscription_cancelled_by_driver',
                    (string) $activeSub->id,
                    ['reason' => $reason]
                );
            }

            return $activeSub->fresh(['subscriptionRequest', 'child', 'driver.user']);
        });
    }

    // ============================================================
    // مساعد: تحرير مقاعد السائق عند الإلغاء/الإتمام
    // ============================================================

    private function releaseSeatsForSubscription(ActiveSubscription $activeSub): void
    {
        $activeSub->loadMissing(['subscriptionRequest.children']);
        $subReq = $activeSub->subscriptionRequest;
        if (!$subReq) {
            return;
        }

        $childPivot = $subReq->children?->firstWhere('id', $activeSub->child_id)?->pivot;
        $timing     = $childPivot?->timing ?? $subReq->timing ?? 'MORNING';
        $direction  = $childPivot?->trip_direction ?? $subReq->direction ?? 'both';

        $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots(
            $timing,
            $direction
        );

        foreach ($slots as $slot) {
            \App\Models\Driver\DriverSeatSlot::where('driver_id', $activeSub->driver_id)
                ->where('slot', $slot)
                ->where('reserved_seats', '>', 0)
                ->decrement('reserved_seats');
        }
    }

    // ============================================================
    // فحص الطلبات المعلقة وإلغاء غير القابلة للتنفيذ
    // ============================================================

    /**
     * يُنفَّذ كل 6 ساعات تلقائياً — يفحص كل الطلبات المعلقة:
     *   1. تاريخ البدء فات دون قبول → إلغاء تلقائي
     *   2. السائق لا يملك مقاعد كافية → إلغاء تلقائي
     * وفي الحالتين يُرسل إشعاراً لولي الأمر وآخر للسائق.
     */
    public function cancelStaleAndOvercapacityRequests(): array
    {
        $stats = ['cancelled_expired' => 0, 'cancelled_no_seats' => 0, 'healthy' => 0];

        $pending = SubscriptionRequest::where('status', SubscriptionRequest::STATUS_PENDING)
            ->with(['driver.user', 'driver.seatSlots', 'parent.user'])
            ->get();

        foreach ($pending as $req) {
            // ── السيناريو 1: تاريخ البدء انتهى دون قبول ──────────
            if ($req->start_date && \Carbon\Carbon::parse($req->start_date)->lt(now()->startOfDay())) {
                $this->autoCancelRequest(
                    $req,
                    'انتهى تاريخ بدء الطلب دون قبول السائق.',
                    'subscription_request_expired'
                );
                $stats['cancelled_expired']++;
                continue;
            }

            // ── السيناريو 2: لا تتوفر مقاعد كافية خلال فترة الطلب ──────────
            try {
                $startDate = $req->start_date ?? now()->toDateString();
                $endDate   = $req->end_date   ?? $startDate;
                $req->driver->loadMissing('seatSlots');
                $this->validateSeatAvailabilityForPeriod(
                    $req->driver,
                    $req->timing    ?? 'MORNING',
                    $req->direction ?? 'both',
                    $req->children_count ?? 1,
                    $startDate,
                    $endDate
                );
                $stats['healthy']++;
            } catch (Exception $e) {
                $this->autoCancelRequest(
                    $req,
                    'لا تتوفر مقاعد كافية لدى السائق خلال فترة الاشتراك.',
                    'subscription_request_no_seats'
                );
                $stats['cancelled_no_seats']++;
            }
        }

        Log::info('CheckPendingSubscriptions', $stats);

        return $stats;
    }

    private function autoCancelRequest(SubscriptionRequest $req, string $reason, string $notificationType): void
    {
        $req->update(['status' => SubscriptionRequest::STATUS_CANCELLED]);

        // استرجاع المبالغ المحجوزة إن وُجدت
        $this->refundHeldFundsOnCancellation($req->id, 'system');

        $driverName = $req->driver?->user?->full_name ?? 'السائق';

        // إشعار ولي الأمر
        $parentUser = $req->parent?->user;
        if ($parentUser) {
            $this->notifyUser(
                $parentUser,
                'إلغاء تلقائي لطلب اشتراك',
                "تم إلغاء طلب اشتراكك مع السائق [{$driverName}] تلقائياً. السبب: {$reason}",
                $notificationType,
                (string) $req->id
            );
        }

        // إشعار السائق
        $driverUser = $req->driver?->user;
        if ($driverUser) {
            $parentName = $req->parent?->user?->full_name ?? 'ولي الأمر';
            $this->notifyUser(
                $driverUser,
                'إلغاء تلقائي لطلب اشتراك',
                "تم إلغاء طلب الاشتراك من [{$parentName}] تلقائياً. السبب: {$reason}",
                $notificationType . '_driver',
                (string) $req->id
            );
        }
    }

    // ============================================================
    // نظام إشعارات موحد
    // ============================================================

    private function notifyUser($user, string $title, string $message, string $type, ?string $entityId = null, array $extra = []): void
    {
        if ($user) {
            try {
                $this->notificationService->sendToUser($user, $type, array_merge([
                    'title'       => $title,
                    'message'     => $message,
                    'entity_type' => 'subscription_request',
                    'entity_id'   => $entityId,
                ], $extra));
            } catch (Exception $e) {
                Log::error("فشل إرسال الإشعار لـ {$user->id}: " . $e->getMessage());
            }
        }
    }
    /**
     * جلب تفاصيل اشتراك نشط واحد خاص بالسائق مع العلاقات الكاملة
     */
    public function getDriverActiveSubscriptionDetails(int $activeSubscriptionId, int $driverId)
    {
        $activeSub = ActiveSubscription::where('id', $activeSubscriptionId)
            ->where('driver_id', $driverId)
            ->with([
                'subscriptionRequest',
                'child.school',
                'child.address',
                'parent',
                'school'
            ])
            ->first();

        if (!$activeSub) {
            throw new Exception('الاشتراك النشط غير موجود أو ليس لديك صلاحية للوصول إليه.');
        }

        return $activeSub;
    }

    // ============================================================
    // جلب طلبات الاشتراك الخاصة بولي الأمر
    // ============================================================
    public function getParentSubscriptions(int $userId, ?string $filter = null)
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $query = SubscriptionRequest::where('parent_id', $parent->id)
            ->with([
                'driver.user',
                'school:id,name',
                'children.school',
            ]);

        switch ($filter) {
            case 'pending':
                $query->where('status', SubscriptionRequest::STATUS_PENDING);
                break;
            case 'accepted':
                $query->where('status', SubscriptionRequest::STATUS_ACCEPTED);
                break;
            case 'rejected':
                $query->where('status', SubscriptionRequest::STATUS_REJECTED);
                break;
            case 'cancelled':
                $query->where('status', SubscriptionRequest::STATUS_CANCELLED);
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }
    
    /**
     * إلغاء طلب الاشتراك (pending / acquired) بواسطة ولي الأمر
     */
    public function cancelSubscriptionByParent(int $id, int $userId): SubscriptionRequest
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $subscription = SubscriptionRequest::where('id', $id)
            ->where('parent_id', $parent->id)
            ->first();

        if (!$subscription) {
            throw new Exception('طلب الاشتراك غير موجود، أو لا تملك صلاحية الوصول إليه.');
        }

        $cancellable = [SubscriptionRequest::STATUS_PENDING, SubscriptionRequest::STATUS_ACQUIRED];
        if (!in_array($subscription->status, $cancellable)) {
            throw new Exception('لا يمكن إلغاء هذا الطلب في حالته الحالية: ' . $subscription->status);
        }

        $subscription->update(['status' => SubscriptionRequest::STATUS_CANCELLED]);

        // استرجاع المبالغ المحجوزة إن وُجدت
        $this->refundHeldFundsOnCancellation($subscription->id, 'parent');

        // إشعار السائق إن وُجد
        $subscription->loadMissing('driver.user');
        $driverUser = $subscription->driver?->user;
        if ($driverUser) {
            $this->notifyUser(
                $driverUser,
                'إلغاء طلب اشتراك',
                'قام ولي الأمر بإلغاء طلب اشتراكه.',
                'subscription_request_cancelled_by_parent',
                (string) $subscription->id
            );
        }

        return $subscription;
    }

    /**
     * جلب الاشتراكات المفعّلة لولي الأمر والموافَق عليها مقسمة بالفلاتر الذكية وبنفس صيغة طلبات الاشتراك
     */
    public function getParentActiveSubscriptions(int $userId, ?string $filter = null)
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        $parentId = $parent ? $parent->id : $userId;

        $query = SubscriptionRequest::query()
            ->with([
                'driver.user',
                'children' => function ($query) {
                    $query->withPivot([
                        'subscription_type',
                        'trip_direction',
                        'timing',
                        'start_date',
                        'end_date',
                        'working_days_count',
                        'distance_km',                    
                        'price_per_child',
                        'trip_price',
                        'discount_amount',            
                        'total_amount_after_discount',
                        'driver_net_price'
                    ]);
                },
                'children.school',
                'children.address'
            ])
            ->where(function ($q) use ($parentId, $userId) {
                $q->where('parent_id', $parentId)
                  ->orWhere('parent_id', $userId);
            });

        if (!empty($filter)) {
            $filter = strtolower($filter);
            if ($filter === 'active') {
                $query->whereIn('status', ['accepted', 'active']);
            } elseif ($filter === 'pending') {
                $query->where('status', 'pending');
            } elseif ($filter === 'completed') {
                $query->where('status', 'completed');
            } elseif ($filter === 'cancelled') {
                $query->where('status', 'cancelled');
            } else {
                $query->where('status', $filter);
            }
        } else {
            $query->whereIn('status', ['accepted', 'active']);
        }

        return $query->orderBy('id', 'desc')->get();
    }

    /**
     * جلب طلبات الاشتراك المبدئية الواردة للسائق مع الفلترة الذكية
     */
    public function getDriverSubscriptionRequests(int $userId, ?string $filter = null)
    {
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            throw new Exception('لم يتم العثور على ملف السائق الخاص بك.', 403);
        }

        $query = SubscriptionRequest::where('driver_id', $driver->id)
            ->with([
                'parent.user',
                'school:id,name',
                'children'
            ]);

        switch ($filter) {
            case 'pending':
                $query->where('status', SubscriptionRequest::STATUS_PENDING);
                break;
            case 'cancelled':
                $query->where('status', SubscriptionRequest::STATUS_CANCELLED);
                break;
            case 'rejected':
                $query->where('status', SubscriptionRequest::STATUS_REJECTED);
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function parentHasSubscriptionWithDriver(int $userId, int $driverId): bool
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            return false;
        }

        return ActiveSubscription::where(function ($q) use ($userId, $parent) {
            $q->where('parent_id', $parent->id)
              ->orWhere('parent_id', $userId);
        })->where('driver_id', $driverId)
          ->whereIn('status', ['active', 'completed', 'cancelled']) // الحالات المطلوبة
          ->exists();
    }


    public function getDriverActiveSubscriptions(int $userId, ?string $filter = null)
    {
        try {
            $userExists = User::where('id', $userId)->exists();
            if (!$userExists) {
                throw new Exception("السبب: المستخدم رقم ({$userId}) غير موجود تماماً في جدول المستخدمين (users).");
            }

            $driver = Driver::where('user_id', $userId)->first();
            if (!$driver) {
                throw new Exception("السبب: المستخدم ({$userId}) موجود، ولكن ليس لديه سجل مرادف في جدول السائقين (drivers).");
            }

            $query = SubscriptionRequest::query()
                ->with([
                    'parent.user',
                    'children' => function ($query) {
                        $query->withPivot([
                            'subscription_type',
                            'trip_direction',
                            'timing',
                            'start_date',
                            'end_date',
                            'working_days_count',
                            'distance_km',                    
                            'price_per_child',
                            'trip_price',
                            'discount_amount',            
                            'total_amount_after_discount',
                            'driver_net_price'
                        ]);
                    },
                    'children.school',
                    'children.address'
                ])
                ->where('driver_id', $driver->id);

            if (!empty($filter)) {
                $filter = strtolower($filter);
                if ($filter === 'active' || $filter === 'current_active') {
                    $query->whereIn('status', ['accepted', 'active']);
                } elseif ($filter === 'pending_start') {
                    $query->whereIn('status', ['accepted', 'active'])
                          ->whereHas('children', function ($q) {
                              $q->where('request_children.start_date', '>', now()->toDateString());
                          });
                } elseif ($filter === 'completed') {
                    $query->where('status', 'completed');
                } elseif ($filter === 'cancelled') {
                    $query->where('status', 'cancelled');
                } else {
                    $query->where('status', $filter);
                }
            } else {
                $query->whereIn('status', ['accepted', 'active']);
            }

            return $query->orderBy('id', 'desc')->get();

        } catch (QueryException $e) {
            throw new Exception("خطأ قاعدة البيانات (DB Error): " . $e->getMessage());
        } catch (Throwable $e) {
            throw new Exception("خطأ أثناء التنفيذ: " . $e->getMessage());
        }
    }

    public function getSubscriptionDetails($id)
    {
        return SubscriptionRequest::with([
            'parent.user',
            'driver.user',
            'children.school',
            'children.address',
        ])->findOrFail($id);
    }
    /**
     * جلب أIDs جميع السائقين المشترك معهم ولي الأمر
     */
    public function getParentSubscribedDriverIds(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            return [];
        }

        return ActiveSubscription::where(function ($q) use ($userId, $parent) {
                $q->where('parent_id', $parent->id)
                  ->orWhere('parent_id', $userId);
            })
            ->whereIn('status', ['active', 'completed', 'cancelled'])
            ->pluck('driver_id')
            ->unique()
            ->values()
            ->toArray();
    }

    public function getParentChats(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        $parentId = $parent ? $parent->id : $userId;

        // جلب جميع الاشتراكات النشطة لولي الأمر، بالبحث بـ parent_id و user_id
        $subscriptions = ActiveSubscription::with(['driver.user'])
            ->where(function ($q) use ($parentId, $userId) {
                $q->where('parent_id', $parentId)->orWhere('parent_id', $userId);
            })
            ->get();

        // دعم إضافي: جلب طلبات الاشتراكات أيضاً في حال كانت تحت الإجراء أو العقد
        $requestSubs = SubscriptionRequest::with(['driver.user'])
            ->where(function ($q) use ($parentId, $userId) {
                $q->where('parent_id', $parentId)->orWhere('parent_id', $userId);
            })
            ->whereIn('status', ['accepted', 'contract_offered', 'pending', 'active'])
            ->get();

        $processedDrivers = [];
        $chats = [];

        foreach ($subscriptions as $sub) {
            $driver = $sub->driver;
            if (!$driver || !$driver->user) continue;

            if (in_array($driver->id, $processedDrivers)) continue;
            $processedDrivers[] = $driver->id;

            $driverUser = $driver->user;
            $canChat = in_array(strtolower($sub->status ?? 'active'), ['active', 'approved']);

            $chats[] = [
                "chat_room_id"        => "parent_" . $parentId . "_driver_" . $driver->id,
                "driver_id"           => $driver->id,
                "driver_user_id"      => $driverUser->id,
                "driver_name"         => $driverUser->full_name,
                "driver_phone"        => $driverUser->phone_number,
                "driver_photo"        => $driverUser->avatar_url,
                "can_chat"            => $canChat,
                "subscription_status" => $sub->status ?? 'active'
            ];
        }

        foreach ($requestSubs as $req) {
            $driver = $req->driver;
            if (!$driver || !$driver->user) continue;

            if (in_array($driver->id, $processedDrivers)) continue;
            $processedDrivers[] = $driver->id;

            $driverUser = $driver->user;

            $chats[] = [
                "chat_room_id"        => "parent_" . $parentId . "_driver_" . $driver->id,
                "driver_id"           => $driver->id,
                "driver_user_id"      => $driverUser->id,
                "driver_name"         => $driverUser->full_name,
                "driver_phone"        => $driverUser->phone_number,
                "driver_photo"        => $driverUser->avatar_url,
                "can_chat"            => true,
                "subscription_status" => $req->status
            ];
        }

        return $chats;
    }

    /**
     * جلب قائمة محادثات السائق بالكامل متوافقة مع كافة الهياكل
     */
    public function getDriverChats(int $userId): array
    {
        $driver = Driver::where('user_id', $userId)->first();
        $driverId = $driver ? $driver->id : $userId;

        $subscriptions = ActiveSubscription::with(['parent'])
            ->where(function ($q) use ($driverId, $userId) {
                $q->where('driver_id', $driverId)->orWhere('driver_id', $userId);
            })
            ->get();

        $requestSubs = SubscriptionRequest::with(['parent.user'])
            ->where(function ($q) use ($driverId, $userId) {
                $q->where('driver_id', $driverId)->orWhere('driver_id', $userId);
            })
            ->whereIn('status', ['accepted', 'contract_offered', 'pending', 'active'])
            ->get();

        $processedParents = [];
        $chats = [];

        foreach ($subscriptions as $sub) {
            $parentUser = $sub->parent; // العلاقة parent في ActiveSubscription ترجع User
            if (!$parentUser) {
                // تجربة جلب المستخدم من جدول parents إذا كان parent_id هو id من جدول parents
                $parentRecord = ParentModel::with('user')->find($sub->parent_id);
                $parentUser = $parentRecord?->user;
            }

            if (!$parentUser) continue;
            if (in_array($parentUser->id, $processedParents)) continue;
            $processedParents[] = $parentUser->id;

            $parentRecord = ParentModel::where('user_id', $parentUser->id)->first();
            $parentId = $parentRecord ? $parentRecord->id : $parentUser->id;

            $chats[] = [
                "chat_room_id"        => "parent_" . $parentId . "_driver_" . $driverId,
                "parent_id"           => $parentId,
                "parent_user_id"      => $parentUser->id,
                "parent_name"         => $parentUser->full_name,
                "parent_phone"        => $parentUser->phone_number,
                "parent_photo"        => $parentUser->avatar_url,
                "can_chat"            => in_array(strtolower($sub->status ?? 'active'), ['active', 'approved']),
                "subscription_status" => $sub->status ?? 'active'
            ];
        }

        foreach ($requestSubs as $req) {
            $parentRecord = $req->parent;
            $parentUser = $parentRecord?->user;

            if (!$parentUser) continue;
            if (in_array($parentUser->id, $processedParents)) continue;
            $processedParents[] = $parentUser->id;

            $parentId = $parentRecord ? $parentRecord->id : $parentUser->id;

            $chats[] = [
                "chat_room_id"        => "parent_" . $parentId . "_driver_" . $driverId,
                "parent_id"           => $parentId,
                "parent_user_id"      => $parentUser->id,
                "parent_name"         => $parentUser->full_name,
                "parent_phone"        => $parentUser->phone_number,
                "parent_photo"        => $parentUser->avatar_url,
                "can_chat"            => true,
                "subscription_status" => $req->status
            ];
        }

        return $chats;
    }
    
}