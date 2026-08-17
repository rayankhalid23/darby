<?php

namespace App\Services\Shared;

use App\Models\Shared\SubscriptionRequest;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\ContractService;
use App\Notifications\CustomDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SubscriptionRequestService
{
    protected ContractService $contractService;
    protected \App\Services\Trip\MasterRouteStopSyncService $masterRouteStopSyncService;

    public function __construct(
        ContractService $contractService,
        \App\Services\Trip\MasterRouteStopSyncService $masterRouteStopSyncService
    ) {
        $this->contractService = $contractService;
        $this->masterRouteStopSyncService = $masterRouteStopSyncService;
    }

    /**
     * حساب عدد أيام العمل بين تاريخ البدء والانتهاء باستثناء الجمعة والسبت
     */
    private function calculateWorkingDays(?string $startDate, ?string $endDate): int
    {
        if (empty($startDate) || empty($endDate)) {
            return 0;
        }

        try {
            $start = \Carbon\Carbon::parse($startDate)->startOfDay();
            $end = \Carbon\Carbon::parse($endDate)->startOfDay();

            if ($start->greaterThan($end)) {
                return 0;
            }

            $period = \Carbon\CarbonPeriod::create($start, $end);
            $workingDaysCount = 0;

            foreach ($period as $date) {
                // استثناء يوم الجمعة (5) والسبت (6)
                if (!in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY])) {
                    $workingDaysCount++;
                }
            }

            return $workingDaysCount;
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ============================================================
    // إنشاء طلب اشتراك
    // ============================================================

    public function createRequest(array $data, $userId)
    {
        $driverId = $data['driver_id'] ?? null;

        $parent = ParentModel::where('user_id', $userId)->with('user')->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        $driver = Driver::with(['user', 'seatSlots'])->find($driverId);
        if (!$driver) {
            throw new Exception("السائق المحدد غير موجود في النظام.");
        }

        // ✅ الفلتر 1: التحقق من تطابق الفترة/الاتجاه مع تفضيلات السائق
        $this->validateDriverShiftCompatibility($driver, $data['timing'] ?? 'MORNING', $data['direction'] ?? 'both');

        // ✅ الفلتر 2: التحقق من توفر المقاعد لكل فترة/اتجاه مطلوبة
        $this->validateSeatAvailability($driver, $data['timing'] ?? 'MORNING', $data['direction'] ?? 'both', count($data['children'] ?? []));

        return DB::transaction(function () use ($data, $parent, $driver) {
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;

            // 1. حساب عدد أيام العمل وتعريف المتغير هنا لتجنب الخطأ
            $daysCount = $this->calculateWorkingDays($startDate, $endDate);

            $totalPrice = collect($data['children'])->sum(fn($c) => $c['price_per_child'] ?? 0);

            $subscriptionRequest = SubscriptionRequest::create([
                'parent_id'         => $parent->id,
                'driver_id'         => $driver->id,
                'school_id'         => $data['school_id'] ?? null,
                'subscription_type' => $data['subscription_type'] ?? 'multi_day',
                'direction'         => $data['direction'],
                'timing'            => $data['timing'],
                'start_date'        => $startDate,
                'end_date'          => $endDate,
                'days_count'        => $daysCount, // 2. استخدامه هنا بشكل صحيح
                'total_price'       => $totalPrice,
                'pickup_time'       => $data['pickup_time'] ?? null,
                'dropoff_time'      => $data['dropoff_time'] ?? null,
                'max_waiting_time'  => $data['max_waiting_time'] ?? 15,
                'status'            => SubscriptionRequest::STATUS_PENDING,
                'notes'             => $data['notes'] ?? null,
                'children_count'          => count($data['children']),
                'children_acceptance_mode' => $data['children_acceptance_mode'] ?? 'all',
            ]);

            foreach ($data['children'] as $childData) {
                DB::table('request_children')->insert([
                    'request_id'         => $subscriptionRequest->id,
                    'child_id'           => $childData['child_id'],
                    'pickup_address_id'  => $childData['pickup_address_id'] ?? null,
                    'home_lat'           => $childData['home_lat'] ?? null,
                    'home_lng'           => $childData['home_lng'] ?? null,
                    'home_label'         => $childData['home_label'] ?? null,
                    'dropoff_address_id' => $childData['dropoff_address_id'] ?? null,
                    'school_lat'         => $childData['school_lat'] ?? null,
                    'school_lng'         => $childData['school_lng'] ?? null,
                    'school_label'       => $childData['school_label'] ?? null,
                    'price_per_child'    => $childData['price_per_child'] ?? 0,
                    'child_notes'        => $childData['child_notes'] ?? null,
                ]);
            }

            // إرسال الإشعار للسائق
            $this->notifyUser(
                $driver->user,
                'طلب اشتراك جديد',
                "لديك طلب اشتراك جديد من {$parent->user->full_name}.",
                'new_subscription_request',
                ['subscription_request_id' => $subscriptionRequest->id, 'parent_id' => $parent->id]
            );

            return $subscriptionRequest->load(['children', 'driver.user', 'parent.user', 'school']);
        });
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
            $seatSlot     = $driver->seatSlots->firstWhere('slot', $slot);
            $slotCapacity = $seatSlot?->total_seats ?? ($driver->vehicle?->capacity_manual ?? 0);

            $peak      = $this->computeSlotPeakConcurrency($driver->id, $slot, $startDate, $endDate);
            $available = max(0, $slotCapacity - $peak);

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
            ->whereHas('contract', function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate);
            })
            ->with('contract:id,timing,direction,start_date,end_date')
            ->get(['id', 'contract_id'])
            ->filter(function ($sub) use ($slot) {
                $contract = $sub->contract;
                if (!$contract) {
                    return false;
                }
                $subSlots = \App\Models\Driver\DriverSeatSlot::resolveSlots(
                    $contract->timing    ?? 'MORNING',
                    $contract->direction ?? 'both'
                );
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
                    $contract = $sub->contract;
                    $sStart   = $contract?->start_date;
                    $sEnd     = $contract?->end_date;
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
        $requiredSeats = $req->children_count > 0 ? $req->children_count : ($req->children ? $req->children->count() : 1);
        $startDate     = $req->start_date ?? now()->toDateString();
        $endDate       = $req->end_date   ?? $startDate;
        $timing        = $req->timing    ?? 'MORNING';
        $direction     = $req->direction ?? 'both';

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

        // 2. تحديث حالة الطلب الحالي إلى مقبول
        $req->update(['status' => SubscriptionRequest::STATUS_ACCEPTED]);

        // 3. إلغاء الطلبات الأخرى المعلقة لنفس العميل ونفس التوقيت
        SubscriptionRequest::where('parent_id', $req->parent_id)
            ->where('timing', $req->timing)
            ->where('status', SubscriptionRequest::STATUS_PENDING)
            ->where('id', '!=', $req->id)
            ->update(['status' => SubscriptionRequest::STATUS_CANCELLED]);

        // 4. توليد العقد
        $contract = $this->contractService->generateContract($req);

        // 5. تحديد الـ slot الأساسي (الفترة/الاتجاه) بناءً على تفضيلات السائق الثابتة
        $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots($req->timing ?? 'MORNING', $req->direction ?? 'both');
        $primarySlot = $slots[0] ?? null;

        // 6. البحث عن مسار رئيسي (Master Route) نشط بالفعل لنفس السائق لنفس الفترة/الاتجاه
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
                'contract_id'        => $contract->id,
                'driver_id'          => $req->driver_id,
                'vehicle_id'         => $vehicle->id,
                'route_name'         => \App\Models\Shared\Route::generateGenericRouteName($req->timing, $req->direction),
                'route_type'         => $routeType,
                'shift_slot'         => $primarySlot,
                'start_time'         => $req->pickup_time ?? '07:00:00',
                'optimized_points'   => $routeData ? json_encode($routeData) : null,
                'total_distance'     => $distanceKm,
                'estimated_duration' => $durationMinutes,
                'status'             => 'Active'
            ]);
        }

        // 7. تفعيل اشتراكات الأطفال لجدول active_subscriptions وتفريغ المقاعد
        $this->createActiveSubscriptions($req, $contract, $route);

        // 7.5 مزامنة المسار الرئيسي (Master Route) لكل فترة/اتجاه مطلوبة (route_stops)
        try {
            $this->masterRouteStopSyncService->syncOnAcceptance($req, $contract, $route, $slots);
        } catch (\Throwable $e) {
            Log::warning("فشل مزامنة المسار الرئيسي (route_stops) للطلب ID: {$req->id} - " . $e->getMessage());
        }

        // 8. إرسال إشعار القبول مع حمايته من إلغاء الـ Transaction
        try {
            if ($parent && $parent->user) {
                $this->notifyUser(
                    $parent->user,
                    'تم قبول طلب الاشتراك',
                    "تم قبول طلبك مع السائق " . ($req->driver->user->full_name ?? 'السائق') . ". رقم العقد: {$contract->contract_number}",
                    'request_accepted',
                    ['contract_id' => $contract->id]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار FCM عند قبول الطلب ID {$req->id}: " . $e->getMessage());
        }

        return $req->refresh()->load(['children', 'driver.user', 'parent.user', 'contract']);
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
                    'request_rejected'
                );
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعار FCM عند رفض الطلب ID {$req->id}: " . $e->getMessage());
        }

        return $req->refresh();
    }

    // ============================================================
    // 4. إنشاء سجلات الاشتراكات النشطة (مطابق لجدول active_subscriptions)
    // ============================================================

    private function createActiveSubscriptions(SubscriptionRequest $req, Contract $contract, ?\App\Models\Shared\Route $route = null): void
    {
        $pickupTime  = $req->pickup_time  ?? '07:00:00';
        $dropoffTime = $req->dropoff_time ?? '14:00:00';
        $parentUserId = $req->parent?->user_id ?? $contract->parent_id ?? $req->parent_id;

        // الـ slots المطلوبة — نحتاجها لتحديث عداد المقاعد المحجوزة
        $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots($req->timing ?? 'MORNING', $req->direction ?? 'both');

        foreach ($req->children as $child) {
            $pickupLat  = $child->pivot->home_lat   ?? $req->pickup_lat   ?? null;
            $pickupLng  = $child->pivot->home_lng   ?? $req->pickup_lng   ?? null;
            $pickupLbl  = $child->pivot->home_label ?? $req->pickup_label ?? 'الموقع السكني';

            $dropoffLat = $child->pivot->school_lat   ?? $req->school->lat       ?? $req->school->latitude  ?? $req->dropoff_lat ?? null;
            $dropoffLng = $child->pivot->school_lng   ?? $req->school->lng       ?? $req->school->longitude ?? $req->dropoff_lng ?? null;
            $dropoffLbl = $child->pivot->school_label ?? $req->school->name      ?? $req->dropoff_label     ?? 'المدرسة';

            ActiveSubscription::create([
                'contract_id'   => $contract->id,
                'route_id'      => $route?->id,
                'status'        => 'active',
                'child_id'      => $child->id,
                'driver_id'     => $req->driver_id,
                'parent_id'     => $parentUserId,
                'pickup_lat'    => $pickupLat,
                'pickup_lng'    => $pickupLng,
                'pickup_label'  => $pickupLbl,
                'pickup_time'   => $pickupTime,
                'dropoff_lat'   => $dropoffLat,
                'dropoff_lng'   => $dropoffLng,
                'dropoff_label' => $dropoffLbl,
                'dropoff_time'  => $dropoffTime,
            ]);

            // زيادة عداد المقاعد المحجوزة لكل slot (مقابل decrement في releaseSeatsForSubscription)
            foreach ($slots as $slot) {
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

            return $activeSub->load(['contract', 'child', 'driver.user']);
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
                    ['active_subscription_id' => $activeSub->id]
                );
            }

            return $activeSub->fresh(['contract', 'child', 'driver.user']);
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
                    ['active_subscription_id' => $activeSub->id, 'reason' => $reason]
                );
            }

            return $activeSub->fresh(['contract', 'child', 'driver.user']);
        });
    }

    // ============================================================
    // مساعد: تحرير مقاعد السائق عند الإلغاء/الإتمام
    // ============================================================

    private function releaseSeatsForSubscription(ActiveSubscription $activeSub): void
    {
        $activeSub->loadMissing('contract');
        $contract = $activeSub->contract;
        if (!$contract) {
            return;
        }

        $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots(
            $contract->timing    ?? 'MORNING',
            $contract->direction ?? 'both'
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

        $driverName = $req->driver?->user?->full_name ?? 'السائق';

        // إشعار ولي الأمر
        $parentUser = $req->parent?->user;
        if ($parentUser) {
            $this->notifyUser(
                $parentUser,
                'إلغاء تلقائي لطلب اشتراك',
                "تم إلغاء طلب اشتراكك مع السائق [{$driverName}] تلقائياً. السبب: {$reason}",
                $notificationType,
                ['subscription_request_id' => $req->id]
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
                ['subscription_request_id' => $req->id]
            );
        }
    }

    // ============================================================
    // نظام إشعارات موحد
    // ============================================================

    private function notifyUser($user, string $title, string $message, string $type, array $metadata = []): void
    {
        if ($user) {
            try {
                $user->notify(new CustomDatabaseNotification([
                    'title'    => $title,
                    'message'  => $message,
                    'type'     => $type,
                    'metadata' => $metadata
                ]));
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
                'contract',
                'child.school',
                'child.address',
                'parent', // تم تعديلها لتتوافق مع نموذج الأب مباشرة
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
                'contract'
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

        // إشعار السائق إن وُجد
        $subscription->loadMissing('driver.user');
        $driverUser = $subscription->driver?->user;
        if ($driverUser) {
            $this->notifyUser(
                $driverUser,
                'إلغاء طلب اشتراك',
                'قام ولي الأمر بإلغاء طلب اشتراكه.',
                'subscription_request_cancelled_by_parent',
                ['subscription_request_id' => $subscription->id]
            );
        }

        return $subscription;
    }

    /**
     * جلب الاشتراكات المفعّلة لولي الأمر والموافَق عليها مقسمة بالفلاتر الذكية
     */
    public function getParentActiveSubscriptions(int $userId, ?string $filter = null)
    {
        // 1. جلب سجل ولي الأمر والتأكد من وجوده
        $parent = ParentModel::where('user_id', $userId)->first();
        if (!$parent) {
            throw new Exception('هذا الحساب غير مسجل كولي أمر في النظام.');
        }

        // 2. بناء الاستعلام مع العلاقات الأساسية
        $query = ActiveSubscription::with([
            'contract', 
            'child', 
            'driver.user', 
            'driver.vehicles',
            'school'
        ])
        ->where(function ($q) use ($userId, $parent) {
            $q->where('parent_id', $parent->id)
              ->orWhere('parent_id', $userId);
        });

        // 3. تطبيق الفلتر بشكل صحيح وآمن فقط إذا تم إرساله وكان ضمن القيم المسموحة
        if (!empty($filter)) {
            $allowedFilters = ['active', 'pending', 'completed', 'cancelled'];
            if (in_array($filter, $allowedFilters)) {
                $query->where('status', $filter);
            }
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
        $driver = Driver::where('user_id', $userId)->first();
        if (!$driver) {
            throw new Exception('لم يتم العثور على ملف السائق الخاص بك.');
        }

        $query = ActiveSubscription::where('driver_id', $driver->id)
            ->with([
                'contract.subscriptionRequest',
                'child.school',
                'parent',
                'driver.user',
                'driver.vehicles',
            ]);

        $today = now()->toDateString();

        // تطبيق فلاتر الحالات مباشرة
        switch ($filter) {
            case 'active':
                $query->where('status', 'active');
                break;

            case 'pending':
                $query->where('status', 'pending');
                break;

            case 'completed':
                $query->where('status', 'completed');
                break;

            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
        }

        return $query->orderBy('id', 'desc')->get();
    }

    public function getSubscriptionDetails($id)
    {
        return SubscriptionRequest::with([
            'parent.user',
            'driver.user',
            'children.school',
            'children.address',
            'contract',
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