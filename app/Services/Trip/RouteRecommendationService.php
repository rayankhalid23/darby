<?php

namespace App\Services\Trip;

use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Shared\ActiveSubscription;
use Carbon\Carbon;
use Exception;

class RouteModuleException extends Exception
{
    protected string $errorCode;

    public function __construct(string $message, string $errorCode = 'ROUTE_ERROR', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

class RouteRecommendationService
{
    /**
     * حساب جميع مؤشرات الأداء والبيانات الديناميكية للمسار وتحديثها تلقائياً
     */
    public function calculateRouteMetrics(Route $route): array
    {
        $route->loadMissing(['driver.vehicle', 'activeSubscriptions.child', 'activeSubscriptions.school']);

        $activeSubs = $route->activeSubscriptions
            ->where('status', '!=', 'cancelled')
            ->values();

        $childrenCount = $activeSubs->count();
        $uniqueSchoolsCount = $activeSubs->pluck('school_id')->filter()->unique()->count();
        
        $vehicleCapacity = (int) ($route->driver?->vehicle?->capacity_manual ?? 12);
        $availableSeats = max(0, $vehicleCapacity - $childrenCount);

        // أول وآخر وقت دوام للمدارس المرتبطة بهذا المسار
        $schoolTimes = $activeSubs->map(function ($sub) {
            return $sub->school?->start_time ?? '08:00';
        })->sort()->values();

        $firstSchoolTime = $schoolTimes->first() ?? '08:00';
        $lastSchoolTime  = $schoolTimes->last() ?? '08:30';

        // حساب زمن المسار
        $estimatedDuration = (int) ($route->estimated_duration ?? ($childrenCount * 5 + 20));
        $firstSchoolCarbon = Carbon::createFromFormat('H:i', substr($firstSchoolTime, 0, 5));
        $recommendedDeparture = $firstSchoolCarbon->copy()->subMinutes($estimatedDuration + 10)->format('H:i');

        // فحص قفل المسار (Route Lock) عند وجود رحلة نشطة أو بدأت اليوم
        $activeTrip = Trip::where('route_id', $route->id)
            ->whereIn('status', ['started', 'InProgress', 'Planned'])
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->first();

        $isLocked = !is_null($activeTrip);
        $lockReason = $isLocked ? 'Trip Started' : null;

        // حالة المسار المتقدمة (draft, ready, in_trip, completed, archived)
        if ($isLocked) {
            $formattedStatus = 'in_trip';
        } elseif (strtolower($route->status) === 'inactive' || strtolower($route->status) === 'archived') {
            $formattedStatus = 'archived';
        } elseif ($childrenCount === 0) {
            $formattedStatus = 'draft';
        } else {
            $formattedStatus = 'ready';
        }

        // فحص هل يحتاج المسار لمراجعة السائق (Needs Review) وسبب المراجعة
        $needsReview = false;
        $reviewReason = null;
        foreach ($activeSubs as $sub) {
            if ($sub->status === 'needs_review') {
                $needsReview = true;
                $reviewReason = $sub->review_reason ?? 'تم تغيير مدرسة أو بيانات الطفل مؤخراً';
                break;
            }
        }

        // حساب Health Score للمسار (من 0 إلى 100)
        $healthScore = 100;
        if ($availableSeats === 0) {
            $healthScore -= 10;
        }
        if ($vehicleCapacity < $childrenCount) {
            $healthScore -= 40; // تحذير سعة زائدة
        }
        if ($childrenCount > 10) {
            $healthScore -= 15;
        }
        if ($estimatedDuration > 60) {
            $healthScore -= 15;
        }
        $healthScore = max(30, min(100, $healthScore));

        return [
            'id'                    => (int) $route->id,
            'name'                  => $route->route_name,
            'trip_type'             => strtolower($route->route_type),
            'status'                => $formattedStatus,
            'is_locked'             => $isLocked,
            'lock_reason'           => $lockReason,
            'children_count'        => $childrenCount,
            'schools_count'         => $uniqueSchoolsCount > 0 ? $uniqueSchoolsCount : 1,
            'vehicle_capacity'     => $vehicleCapacity,
            'available_seats'       => $availableSeats,
            'first_school_time'     => substr($firstSchoolTime, 0, 5),
            'last_school_time'      => substr($lastSchoolTime, 0, 5),
            'recommended_departure' => $recommendedDeparture,
            'estimated_duration'   => $estimatedDuration,
            'health_score'          => $healthScore,
            'needs_review'          => $needsReview,
            'review_reason'         => $reviewReason,
        ];
    }

    /**
     * التحقق من جميع قواعد عمل الاشتراكات والمسارات (Business Rules Validation)
     */
    public function validateSubscriptionAssignment(ActiveSubscription $sub, Route $route): void
    {
        // 1. لا يمكن إضافة اشتراك منتهي أو موقوف
        $statusLower = strtolower($sub->status ?? 'active');
        if ($statusLower === 'expired') {
            throw new RouteModuleException("الاشتراك منتهي الصلاحية ولا يمكن إسناده لمسار.", 'SUBSCRIPTION_EXPIRED');
        }
        if (in_array($statusLower, ['suspended', 'paused'])) {
            throw new RouteModuleException("الاشتراك موقوف مؤقتاً ولا يمكن إسناده حالياً.", 'SUBSCRIPTION_SUSPENDED');
        }

        // 2. لا يمكن إضافة طفل قبل تاريخ بدء الاشتراك
        $startDate = $sub->contract?->start_date ?? $sub->created_at;
        if ($startDate && Carbon::parse($startDate)->isFuture()) {
            $formattedDate = Carbon::parse($startDate)->format('Y-m-d');
            throw new RouteModuleException("لا يمكن إضافة طفل إلى المسار قبل تاريخ بداية اشتراكه المعتمد ($formattedDate).", 'SUBSCRIPTION_NOT_STARTED');
        }

        // 3. لا يمكن إضافة طفل إلى مسارين نفس الفترة (صباحيين أو مسائيين) في نفس الوقت
        $subTripType = strtolower($route->route_type);
        $alreadyAssignedOtherRoute = ActiveSubscription::where('child_id', $sub->child_id)
            ->where('id', '!=', $sub->id)
            ->whereNotNull('route_id')
            ->whereHas('route', function ($q) use ($subTripType) {
                $q->whereRaw('LOWER(route_type) = ?', [$subTripType]);
            })
            ->exists();

        if ($alreadyAssignedOtherRoute) {
            throw new RouteModuleException("الطفل مضاف بالفعل إلى مسار آخر في نفس الفترة الزمنية ($subTripType).", 'SUBSCRIPTION_ALREADY_ASSIGNED');
        }

        // 4. لا يمكن تجاوز سعة المركبة
        $metrics = $this->calculateRouteMetrics($route);
        if ($metrics['available_seats'] <= 0) {
            throw new RouteModuleException("لا يوجد مقاعد متاحة في هذا المسار. تم تجاوز سعة المركبة.", 'ROUTE_FULL');
        }

        // 5. لا يمكن التعديل أو الإسناد إذا كانت هناك رحلة حالية قيد التشغيل (Started / InProgress)
        $this->validateRouteNotRunning($route);
    }

    /**
     * التحقق من عدم وجود رحلة جارية للمسار عند التعديل أو النقل أو الحذف
     */
    public function validateRouteNotRunning(Route $route): void
    {
        $hasActiveTrip = Trip::where('route_id', $route->id)
            ->whereIn('status', ['started', 'InProgress', 'Planned'])
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->exists();

        if ($hasActiveTrip) {
            throw new RouteModuleException("المسار مقفل بسبب وجود رحلة جارية حالياً (Trip Started).", 'ROUTE_LOCKED');
        }
    }

    /**
     * حساب وتوليد التوصيات الهندسية لأفضل مسار يناسب اشتراك معين
     */
    public function getRecommendationsForSubscription(ActiveSubscription $sub): array
    {
        $driverId = $sub->driver_id;

        $routes = Route::where('driver_id', $driverId)
            ->where('status', 'Active')
            ->with(['activeSubscriptions.school', 'driver.vehicle'])
            ->get();

        if ($routes->isEmpty()) {
            return [
                'recommended_route' => null,
                'message'           => 'لا يوجد مسار مناسب حالياً. يمكنك إنشاء مسار جديد.',
                'other_routes'      => []
            ];
        }

        $scoredRoutes = [];

        foreach ($routes as $route) {
            $metrics = $this->calculateRouteMetrics($route);
            $score = 50;
            $reasons = [];
            $warnings = [];

            $subTripType = strtolower($sub->pickup_time ? 'morning' : 'morning');
            if (strtolower($route->route_type) === $subTripType) {
                $score += 20;
                $reasons[] = 'نفس الفترة الزمنية للرحلة';
            } else {
                $warnings[] = 'اختلاف الفترة الزمنية بين الاشتراك والمسار';
                continue;
            }

            if ($metrics['available_seats'] > 0) {
                $score += 15;
                $reasons[] = 'يوجد مقعد متاح في المركبة';
            } else {
                $score -= 30;
                $warnings[] = 'المقاعد المتبقية قليلة أو ممتلئة';
            }

            $sameSchoolCount = $route->activeSubscriptions->where('school_id', $sub->school_id)->count();
            if ($sameSchoolCount > 0) {
                $score += 15;
                $reasons[] = 'يحتوي على أطفال يدرسون بنفس المدرسة';
            }

            $reasons[] = 'ضمن النطاق الجغرافي للمسار';
            $score = min(99, max(40, $score));

            $scoredRoutes[] = [
                'id'       => (int) $route->id,
                'name'     => $route->route_name,
                'score'    => $score,
                'reasons'  => $reasons,
                'warnings' => $warnings,
                'metrics'  => $metrics
            ];
        }

        usort($scoredRoutes, fn($a, $b) => $b['score'] <=> $a['score']);

        if (empty($scoredRoutes)) {
            return [
                'recommended_route' => null,
                'message'           => 'لا يوجد مسار مطابق لهذه الفترة.',
                'other_routes'      => []
            ];
        }

        $best = array_shift($scoredRoutes);

        $otherRoutes = array_map(function ($r) {
            return [
                'id'      => $r['id'],
                'name'    => $r['name'],
                'warning' => !empty($r['warnings']) ? implode(', ', $r['warnings']) : 'قد يسبب زيادة في زمن المسار'
            ];
        }, $scoredRoutes);

        return [
            'recommended_route' => [
                'id'     => $best['id'],
                'name'   => $best['name'],
                'score'  => $best['score'],
                'reason' => $best['reasons']
            ],
            'other_routes' => $otherRoutes
        ];
    }
}
