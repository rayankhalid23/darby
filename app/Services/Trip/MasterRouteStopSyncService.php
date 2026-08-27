<?php

namespace App\Services\Trip;

use App\Models\Driver\DriverSeatSlot;
use App\Models\Parent\Child;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Contract;
use App\Models\Shared\Route;
use App\Models\Shared\RouteStop;
use App\Models\Shared\SubscriptionRequest;
use App\Support\GeoEstimator;
use Illuminate\Support\Facades\Log;

/**
 * يحافظ على تزامن الـ Master Route (routes + route_stops) لكل سائق/فترة (shift_slot)
 * مع الاشتراكات النشطة الفعلية — إضافة عند القبول، وإزالة عند الإلغاء/الانتهاء.
 * لا يمس أبداً النظام اليدوي القديم (route_id/sort_order على active_subscriptions)
 * الذي يستخدمه DriverRouteController/RouteRecommendationService.
 */
class MasterRouteStopSyncService
{
    /**
     * يُستدعى بعد قبول طلب اشتراك وإنشاء المسار الأساسي (Primary Route) والاشتراكات النشطة.
     * يضبط shift_slot على المسار الأساسي، ويزامن محطاته، وينشئ/يزامن مسار الـ slot الثاني
     * (فقط عندما تكون direction=both، مثال: morning_go + morning_return).
     */
    public function syncOnAcceptance(SubscriptionRequest $req, Route $primaryRoute, array $slots): void
    {
        if (empty($slots)) {
            return;
        }

        $activeSubs = ActiveSubscription::where('subscription_request_id', $req->id)
            ->with('child')
            ->get();

        if ($activeSubs->isEmpty()) {
            return;
        }

        $primaryRoute->shift_slot = $slots[0];
        $primaryRoute->save();

        $this->addChildStopsToRoute($primaryRoute, $activeSubs);
        $this->resequenceRoute($primaryRoute);

        for ($i = 1; $i < count($slots); $i++) {
            $slot = $slots[$i];

            $secondaryRoute = Route::where('driver_id', $primaryRoute->driver_id)
                ->where('shift_slot', $slot)
                ->where('status', 'Active')
                ->first();

            if (!$secondaryRoute) {
                $secondaryRoute = Route::create([
                    'driver_id'               => $primaryRoute->driver_id,
                    'vehicle_id'              => $primaryRoute->vehicle_id,
                    'subscription_request_id' => $req->id,
                    'route_name'              => 'مسار رئيسي - ' . (DriverSeatSlot::slotLabels()[$slot] ?? $slot),
                    'route_type'              => str_starts_with($slot, 'morning') ? 'Morning' : 'Afternoon',
                    'shift_slot'              => $slot,
                    'start_time'              => $primaryRoute->start_time ?? '07:00:00',
                    'status'                  => 'Active',
                ]);
            }

            $this->addChildStopsToRoute($secondaryRoute, $activeSubs);
            $this->resequenceRoute($secondaryRoute);
        }
    }

    /**
     * يُستدعى عند تحول حالة اشتراك نشط إلى cancelled/completed.
     * يزيل محطة منزل الطفل من كل مسار موجودة به، ويزيل محطة المدرسة أيضاً
     * إذا لم يعد هناك طفل آخر نشط من نفس المدرسة على هذا المسار.
     */
    public function removeChildFromDriverRoutes(ActiveSubscription $sub): void
    {
        $childId = $sub->child_id;
        if (!$childId) {
            return;
        }

        $routeIds = RouteStop::where('stop_type', RouteStop::TYPE_HOME)
            ->where('child_id', $childId)
            ->whereHas('route', function ($q) use ($sub) {
                $q->where('driver_id', $sub->driver_id);
            })
            ->pluck('route_id')
            ->unique();

        foreach ($routeIds as $routeId) {
            $route = Route::find($routeId);
            if (!$route) {
                continue;
            }

            RouteStop::where('route_id', $routeId)
                ->where('stop_type', RouteStop::TYPE_HOME)
                ->where('child_id', $childId)
                ->delete();

            $remainingChildIds = RouteStop::where('route_id', $routeId)
                ->where('stop_type', RouteStop::TYPE_HOME)
                ->pluck('child_id');

            $schoolStops = RouteStop::where('route_id', $routeId)
                ->where('stop_type', RouteStop::TYPE_SCHOOL)
                ->get();

            foreach ($schoolStops as $schoolStop) {
                $stillNeeded = Child::whereIn('id', $remainingChildIds)
                    ->where('school_id', $schoolStop->school_id)
                    ->exists();

                if (!$stillNeeded) {
                    $schoolStop->delete();
                }
            }

            $remainingStopsCount = RouteStop::where('route_id', $routeId)->count();

            if ($remainingStopsCount === 0) {
                $route->status = 'Inactive';
                $route->total_distance = 0;
                $route->estimated_duration = 0;
                $route->save();
            } else {
                $this->resequenceRoute($route->fresh());
            }
        }
    }

    /**
     * يُحدّث إحداثيات/تسمية محطة منزل طفل معيّن في كل المسارات الرئيسية النشطة لنفس السائق
     * (تُستخدم عند موافقة السائق على طلب تغيير موقع الاستلام من ولي الأمر)، ثم يعيد ترتيب
     * كل مسار متأثر. أيضاً يُحدّث محطات الرحلات (trip_stops) المُجدولة مستقبلاً والتي لم
     * تبدأ بعد (status = pending) حتى تنعكس عليها نقطة الاستلام الجديدة فوراً.
     */
    public function updateChildHomeStop(int $childId, int $driverId, float $lat, float $lng, ?string $label): void
    {
        $routeIds = RouteStop::where('stop_type', RouteStop::TYPE_HOME)
            ->where('child_id', $childId)
            ->whereHas('route', function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)->where('status', 'Active');
            })
            ->pluck('route_id')
            ->unique();

        foreach ($routeIds as $routeId) {
            RouteStop::where('route_id', $routeId)
                ->where('stop_type', RouteStop::TYPE_HOME)
                ->where('child_id', $childId)
                ->update(['lat' => $lat, 'lng' => $lng, 'label' => $label]);

            $route = Route::find($routeId);
            if ($route) {
                $this->resequenceRoute($route);
            }
        }

        \App\Models\Shared\TripStop::whereHas('trip', function ($q) use ($driverId) {
                $q->where('driver_id', $driverId)
                  ->where('trip_date', '>=', now()->toDateString());
            })
            ->where('stop_type', RouteStop::TYPE_HOME)
            ->where('child_id', $childId)
            ->where('status', \App\Models\Shared\TripStop::STATUS_PENDING)
            ->update(['lat' => $lat, 'lng' => $lng, 'label' => $label]);
    }

    private function addChildStopsToRoute(Route $route, \Illuminate\Support\Collection $activeSubs): void
    {
        foreach ($activeSubs as $sub) {
            if (!$sub->child_id) {
                continue;
            }

            RouteStop::firstOrCreate(
                [
                    'route_id'  => $route->id,
                    'stop_type' => RouteStop::TYPE_HOME,
                    'child_id'  => $sub->child_id,
                ],
                [
                    'lat'   => $sub->pickup_lat,
                    'lng'   => $sub->pickup_lng,
                    'label' => $sub->pickup_label,
                ]
            );

            $schoolId = $sub->child->school_id ?? null;
            if ($schoolId) {
                RouteStop::firstOrCreate(
                    [
                        'route_id'  => $route->id,
                        'stop_type' => RouteStop::TYPE_SCHOOL,
                        'school_id' => $schoolId,
                    ],
                    [
                        'lat'   => $sub->dropoff_lat,
                        'lng'   => $sub->dropoff_lng,
                        'label' => $sub->dropoff_label,
                    ]
                );
            }
        }
    }

    /**
     * يعيد ترتيب محطات المسار حسب اتجاه الوردية (ذهاب: منازل ثم مدرسة | إياب: مدرسة ثم منازل)
     * باستخدام خوارزمية أقرب جار (Nearest Neighbor)، ثم يعيد احتساب المسافة والزمن التقديريين.
     */
    private function resequenceRoute(Route $route): void
    {
        $stops = RouteStop::where('route_id', $route->id)->get();

        $homePoints = $stops->where('stop_type', RouteStop::TYPE_HOME)
            ->map(fn($s) => ['id' => $s->id, 'lat' => (float) $s->lat, 'lng' => (float) $s->lng])
            ->values()->all();

        $schoolPoints = $stops->where('stop_type', RouteStop::TYPE_SCHOOL)
            ->map(fn($s) => ['id' => $s->id, 'lat' => (float) $s->lat, 'lng' => (float) $s->lng])
            ->values()->all();

        if (empty($homePoints) && empty($schoolPoints)) {
            return;
        }

        $isGo = DriverSeatSlot::isGoSlot($route->shift_slot ?? '');
        $finalOrder = GeoEstimator::orderStopsForDirection($homePoints, $schoolPoints, $isGo);

        foreach ($finalOrder as $index => $point) {
            RouteStop::where('id', $point['id'])->update(['sequence_order' => $index + 1]);
        }

        $distanceKm = GeoEstimator::totalPathDistanceKm($finalOrder);

        $route->total_distance = round($distanceKm, 2);
        $route->estimated_duration = max(5, GeoEstimator::estimateMinutes($distanceKm));
        $route->save();
    }
}
