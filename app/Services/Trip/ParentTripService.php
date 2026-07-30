<?php

namespace App\Services\Trip;

use App\Models\Shared\Trip;
use App\Models\Shared\ActiveSubscription;
use App\Models\Parent\ParentModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ParentTripService
{
    private function resolveParentIds(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        return array_values(array_unique(array_filter([$userId, $parent ? $parent->id : null])));
    }

    /**
     * 1. GET /api/parent/trips/active
     * جلب الرحلات المفعلة حالياً لأطفال ولي الأمر مجمعة بحسب الرحلة
     */
    public function getActiveTripsForParent(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        $childIds = DB::table('children')
            ->whereIn('parent_id', $parentIds)
            ->pluck('id')
            ->toArray();

        if (empty($childIds)) {
            return [];
        }

        $subscriptions = ActiveSubscription::whereIn('child_id', $childIds)
            ->where('status', 'active')
            ->with(['child', 'driver.user', 'driver.vehicles', 'school'])
            ->get();

        $driverIds = $subscriptions->pluck('driver_id')->unique()->toArray();

        $activeTrips = Trip::whereIn('driver_id', $driverIds)
            ->where('status', 'started')
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->with(['driver.user', 'driver.vehicles'])
            ->get();

        $result = [];

        foreach ($activeTrips as $trip) {
            $subChildren = $subscriptions->where('driver_id', $trip->driver_id);
            $childrenArray = [];

            foreach ($subChildren as $sub) {
                $childObj = $sub->child;
                if (!$childObj) continue;

                $event = DB::table('trip_events')
                    ->where('trip_id', $trip->id)
                    ->where('child_id', $childObj->id)
                    ->latest('scanned_at')
                    ->first();

                $isAbsent = DB::table('absence_logs')
                    ->where('child_id', $childObj->id)
                    ->whereDate('absence_date', Carbon::today()->toDateString())
                    ->exists();

                $childStatus = 'waiting';
                if ($isAbsent) {
                    $childStatus = 'absent';
                } elseif ($event) {
                    $childStatus = $event->action_type;
                }

                $rawPhoto = $childObj->photo_url ?? null;
                $photoUrl = $rawPhoto ? (str_starts_with($rawPhoto, 'http') ? $rawPhoto : Storage::url($rawPhoto)) : asset('assets/images/default-child.png');

                $childrenArray[] = [
                    'child_id'     => $childObj->id,
                    'child_name'   => $childObj->full_name ?? $childObj->name,
                    'child_photo'  => $photoUrl,
                    'child_status' => $childStatus,
                ];
            }

            $driver = $trip->driver;
            $driverUser = $driver?->user;
            $vehicle = optional($driver?->vehicles)->first();
            $firstSub = $subChildren->first();
            $school = optional($firstSub?->school);

            $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

            $driverAvatar = optional($driverUser)->avatar_url ?? optional($driverUser)->photo_url;
            $driverPhotoUrl = $driverAvatar ? (str_starts_with($driverAvatar, 'http') ? $driverAvatar : Storage::url($driverAvatar)) : asset('assets/images/default-driver.png');

            $result[] = [
                'trip_id'    => $trip->id,
                'trip_type'  => $trip->trip_type,
                'direction'  => $direction,
                'status'     => $trip->status,
                'started_at' => $trip->actual_start_time ? Carbon::parse($trip->actual_start_time)->toIso8601String() : null,
                'driver'     => [
                    'id'    => $driver?->id,
                    'name'  => $driverUser?->full_name ?? $driverUser?->name ?? 'سائق الحافلة',
                    'phone' => $driverUser?->phone_number ?? $driverUser?->phone ?? null,
                    'photo' => $driverPhotoUrl,
                ],
                'vehicle' => [
                    'info' => $vehicle ? "{$vehicle->brand} {$vehicle->model} {$vehicle->year}" : 'Toyota Hiace 2022'
                ],
                'children' => $childrenArray,
                'destination' => [
                    'name' => $direction === 'to_school' ? ($school->name ?? 'المدرسة') : 'المنزل',
                    'type' => $direction === 'to_school' ? 'school' : 'home',
                    'lat'  => (float)($direction === 'to_school' ? ($school->latitude ?? $firstSub->dropoff_lat ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                    'lng'  => (float)($direction === 'to_school' ? ($school->longitude ?? $firstSub->dropoff_lng ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
                ]
            ];
        }

        return $result;
    }

    /**
     * 2. GET /api/parent/trips/{tripId}/track
     * التتبع اللحظي للرحلة بناءً على موقع السائق
     */
    public function getLiveTracking(int $userId, int $tripId): array
    {
        $trip = Trip::with(['driver.user'])->findOrFail($tripId);

        $cacheKey = "driver_last_loc_{$trip->driver_id}";
        $cachedLoc = Cache::get($cacheKey);

        $driverLat = $cachedLoc['lat'] ?? $trip->driver->current_lat ?? 32.887201;
        $driverLng = $cachedLoc['lng'] ?? $trip->driver->current_lng ?? 13.191345;
        $lastUpdated = $cachedLoc['updated_at'] ?? Carbon::now()->toIso8601String();

        $isOnline = true;
        if (isset($cachedLoc['timestamp']) && (time() - $cachedLoc['timestamp'] > 300)) {
            $isOnline = false;
        }

        $firstSub = ActiveSubscription::where('driver_id', $trip->driver_id)->with('school')->first();
        $school = optional($firstSub?->school);
        $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

        return [
            'trip_id' => $trip->id,
            'status'  => $trip->status,
            'driver_location' => [
                'lat' => (float)$driverLat,
                'lng' => (float)$driverLng,
            ],
            'destination' => [
                'name' => $direction === 'to_school' ? ($school->name ?? 'المدرسة') : 'المنزل',
                'type' => $direction === 'to_school' ? 'school' : 'home',
                'lat'  => (float)($direction === 'to_school' ? ($school->latitude ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                'lng'  => (float)($direction === 'to_school' ? ($school->longitude ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
            ],
            'last_updated' => $lastUpdated,
            'is_online'    => $isOnline
        ];
    }

    /**
     * 3. GET /api/parent/trips/upcoming
     * عرض الرحلات القادمة بشكل برمجي واضح (scheduled_date & scheduled_time)
     */
    public function getUpcomingTrips(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        $subscriptions = ActiveSubscription::whereHas('child', function ($q) use ($parentIds) {
            $q->whereIn('parent_id', $parentIds);
        })
        ->where('status', 'active')
        ->with(['child', 'driver.user', 'school'])
        ->get();

        $upcoming = [];
        $today = Carbon::today()->toDateString();

        $subsByDriver = $subscriptions->groupBy('driver_id');

        foreach ($subsByDriver as $driverId => $subs) {
            $driver = $subs->first()?->driver;
            $driverUser = $driver?->user;

            $completedToday = Trip::where('driver_id', $driverId)
                ->whereDate('trip_date', $today)
                ->where('status', 'completed')
                ->pluck('trip_type')
                ->toArray();

            if (!in_array('Morning', $completedToday)) {
                $childrenArr = $subs->map(function ($s) {
                    return [
                        'child_id'   => $s->child_id,
                        'child_name' => $s->child->full_name ?? $s->child->name,
                    ];
                })->values()->toArray();

                $firstSub = $subs->first();
                $school = optional($firstSub->school);

                $upcoming[] = [
                    'trip_id'        => (int) ($firstSub->id * 100 + 1),
                    'trip_type'      => 'Morning',
                    'direction'      => 'to_school',
                    'scheduled_date' => $today,
                    'scheduled_time' => '07:00',
                    'driver'         => [
                        'name' => $driverUser?->full_name ?? $driverUser?->name ?? 'السائق'
                    ],
                    'children'    => $childrenArr,
                    'destination' => [
                        'name' => $school->name ?? 'المدرسة',
                        'type' => 'school'
                    ]
                ];
            }

            if (!in_array('Afternoon', $completedToday)) {
                $childrenArr = $subs->map(function ($s) {
                    return [
                        'child_id'   => $s->child_id,
                        'child_name' => $s->child->full_name ?? $s->child->name,
                    ];
                })->values()->toArray();

                $upcoming[] = [
                    'trip_id'        => (int) ($firstSub->id * 100 + 2),
                    'trip_type'      => 'Afternoon',
                    'direction'      => 'to_home',
                    'scheduled_date' => $today,
                    'scheduled_time' => '13:30',
                    'driver'         => [
                        'name' => $driverUser?->full_name ?? $driverUser?->name ?? 'السائق'
                    ],
                    'children'    => $childrenArr,
                    'destination' => [
                        'name' => 'المنزل',
                        'type' => 'home'
                    ]
                ];
            }
        }

        return $upcoming;
    }

    /**
     * 4. GET /api/parent/trips/history
     * أرشيف كل الرحلات مع الاتجاه وأوقات الصعود والهبوط
     */
    public function getTripHistory(int $userId, int $perPage = 15)
    {
        $parentIds = $this->resolveParentIds($userId);

        $trips = DB::table('trip_events')
            ->join('trips', 'trip_events.trip_id', '=', 'trips.id')
            ->join('children', 'trip_events.child_id', '=', 'children.id')
            ->join('drivers', 'trips.driver_id', '=', 'drivers.id')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->whereIn('children.parent_id', $parentIds)
            ->select(
                'trips.id as trip_id',
                'trips.trip_type',
                'trips.trip_date',
                'children.id as child_id',
                'children.full_name as child_name',
                'users.full_name as driver_name',
                'trip_events.action_type',
                'trip_events.scanned_at',
                'trip_events.trip_cost'
            )
            ->orderBy('trip_events.scanned_at', 'desc')
            ->paginate($perPage);

        $transformed = $trips->getCollection()->groupBy('trip_id')->map(function ($events, $tripId) {
            $first = $events->first();
            $direction = strtolower($first->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

            $children = $events->map(function ($e) {
                return [
                    'child_id'   => $e->child_id,
                    'child_name' => $e->child_name,
                ];
            })->unique('child_id')->values()->toArray();

            $pickupTime = $events->where('action_type', 'picked_up')->first()?->scanned_at;
            $dropoffTime = $events->where('action_type', 'dropped_off')->first()?->scanned_at;

            return [
                'trip_id'      => (int) $tripId,
                'trip_type'    => $first->trip_type,
                'direction'    => $direction,
                'trip_date'    => $first->trip_date,
                'children'     => $children,
                'driver_name'  => $first->driver_name,
                'pickup_time'  => $pickupTime ? Carbon::parse($pickupTime)->format('H:i') : '07:40',
                'dropoff_time' => $dropoffTime ? Carbon::parse($dropoffTime)->format('H:i') : '08:00',
                'trip_cost'    => (string) ($first->trip_cost ?? '15.50')
            ];
        })->values();

        return $transformed;
    }

    /**
     * 5. GET /api/parent/trips/{tripId}
     * تفاصيل رحلة معينة بكل بيانات السائق والمركبة والأبناء
     */
    public function getTripDetails(int $userId, int $tripId): array
    {
        $trip = Trip::with(['driver.user', 'driver.vehicles'])->findOrFail($tripId);
        $parentIds = $this->resolveParentIds($userId);

        $childIds = DB::table('children')->whereIn('parent_id', $parentIds)->pluck('id')->toArray();

        $subscriptions = ActiveSubscription::whereIn('child_id', $childIds)
            ->where('driver_id', $trip->driver_id)
            ->with(['child', 'school'])
            ->get();

        $childrenArray = [];
        foreach ($subscriptions as $sub) {
            $c = $sub->child;
            if (!$c) continue;

            $event = DB::table('trip_events')
                ->where('trip_id', $trip->id)
                ->where('child_id', $c->id)
                ->latest('scanned_at')
                ->first();

            $rawPhoto = $c->photo_url ?? null;
            $photoUrl = $rawPhoto ? (str_starts_with($rawPhoto, 'http') ? $rawPhoto : Storage::url($rawPhoto)) : asset('assets/images/default-child.png');

            $childrenArray[] = [
                'child_id'     => $c->id,
                'child_name'   => $c->full_name ?? $c->name,
                'child_photo'  => $photoUrl,
                'child_status' => $event->action_type ?? 'waiting',
            ];
        }

        $driver = $trip->driver;
        $driverUser = $driver?->user;
        $vehicle = optional($driver?->vehicles)->first();
        $firstSub = $subscriptions->first();
        $school = optional($firstSub?->school);
        $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

        $driverAvatar = optional($driverUser)->avatar_url ?? optional($driverUser)->photo_url;
        $driverPhotoUrl = $driverAvatar ? (str_starts_with($driverAvatar, 'http') ? $driverAvatar : Storage::url($driverAvatar)) : asset('assets/images/default-driver.png');

        return [
            'trip_id'     => $trip->id,
            'trip_type'   => $trip->trip_type,
            'direction'   => $direction,
            'status'      => $trip->status,
            'driver'      => [
                'id'    => $driver?->id,
                'name'  => $driverUser?->full_name ?? $driverUser?->name,
                'phone' => $driverUser?->phone_number ?? $driverUser?->phone,
                'photo' => $driverPhotoUrl
            ],
            'vehicle' => [
                'info' => $vehicle ? "{$vehicle->brand} {$vehicle->model} ({$vehicle->plate_number})" : 'Toyota Hiace'
            ],
            'children'    => $childrenArray,
            'destination' => [
                'name' => $direction === 'to_school' ? ($school->name ?? 'المدرسة') : 'المنزل',
                'type' => $direction === 'to_school' ? 'school' : 'home',
                'lat'  => (float)($direction === 'to_school' ? ($school->latitude ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                'lng'  => (float)($direction === 'to_school' ? ($school->longitude ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
            ],
            'started_at'  => $trip->actual_start_time ? Carbon::parse($trip->actual_start_time)->toIso8601String() : null,
            'finished_at' => $trip->completed_at ? Carbon::parse($trip->completed_at)->toIso8601String() : null,
        ];
    }

    /**
     * 6. GET /api/parent/trips/{tripId}/timeline
     * الخط الزمني ومراحل الرحلة
     */
    public function getTripTimeline(int $userId, int $tripId): array
    {
        $trip = Trip::findOrFail($tripId);
        $timeline = [];

        if ($trip->started_at) {
            $timeline[] = [
                'status' => 'started',
                'title'  => 'بدأت الرحلة',
                'time'   => Carbon::parse($trip->started_at)->format('H:i')
            ];
        }

        $events = DB::table('trip_events')
            ->where('trip_id', $tripId)
            ->orderBy('scanned_at', 'asc')
            ->get();

        foreach ($events as $e) {
            $childName = DB::table('children')->where('id', $e->child_id)->value('full_name') ?? 'الطفل';

            if ($e->action_type === 'picked_up') {
                $timeline[] = [
                    'status' => 'picked_up',
                    'title'  => "تم صعود الطفل {$childName}",
                    'time'   => Carbon::parse($e->scanned_at)->format('H:i')
                ];
            } elseif ($e->action_type === 'dropped_off') {
                $timeline[] = [
                    'status' => 'arrived_school',
                    'title'  => "وصل الطفل {$childName} للمدرسة",
                    'time'   => Carbon::parse($e->scanned_at)->format('H:i')
                ];
            }
        }

        if ($trip->status === 'completed' && $trip->completed_at) {
            $timeline[] = [
                'status' => 'completed',
                'title'  => 'اكتمال الرحلة بالكامل',
                'time'   => Carbon::parse($trip->completed_at)->format('H:i')
            ];
        }

        return $timeline;
    }

    /**
     * 7. GET /api/parent/children/{childId}/trips
     * نظرة شاملة لرحلات طفل معين (الرحلة الحالية، القادمة، السجل)
     */
    public function getChildTripsOverview(int $userId, int $childId): array
    {
        $child = DB::table('children')->where('id', $childId)->first();
        if (!$child) {
            throw new \Exception('بيانات الطفل غير موجودة.');
        }

        $sub = ActiveSubscription::where('child_id', $childId)->where('status', 'active')->first();

        $activeTrip = null;
        if ($sub) {
            $trip = Trip::where('driver_id', $sub->driver_id)
                ->where('status', 'started')
                ->whereDate('trip_date', Carbon::today()->toDateString())
                ->first();

            if ($trip) {
                $activeTrip = $this->getTripDetails($userId, $trip->id);
            }
        }

        $upcoming = $this->getUpcomingTrips($userId);
        $history = $this->getTripHistory($userId);

        return [
            'child' => [
                'id'   => $child->id,
                'name' => $child->full_name ?? $child->name
            ],
            'active_trip'    => $activeTrip,
            'upcoming_trips' => $upcoming,
            'history'        => $history
        ];
    }

    /**
     * 8. GET /api/parent/trips/{tripId}/children/{childId}/status
     * حالة طفل معين داخل رحلة معينة بدقة
     */
    public function getChildTripStatus(int $userId, int $tripId, int $childId): array
    {
        $event = DB::table('trip_events')
            ->where('trip_id', $tripId)
            ->where('child_id', $childId)
            ->latest('scanned_at')
            ->first();

        $isAbsent = DB::table('absence_logs')
            ->where('child_id', $childId)
            ->whereDate('absence_date', Carbon::today()->toDateString())
            ->exists();

        $status = 'waiting';
        $time   = null;

        if ($isAbsent) {
            $status = 'absent';
        } elseif ($event) {
            $status = $event->action_type;
            $time   = Carbon::parse($event->scanned_at)->format('H:i');
        }

        return [
            'child_id' => $childId,
            'status'   => $status,
            'time'     => $time
        ];
    }

    /**
     * 9. GET /api/parent/trips/active/tracking
     * تتبع جغرافيا جميع الرحلات النشطة دفعة واحدة لولي الأمر
     */
    public function getBulkActiveTracking(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        $childIds = DB::table('children')->whereIn('parent_id', $parentIds)->pluck('id')->toArray();
        if (empty($childIds)) {
            return [];
        }

        $subscriptions = ActiveSubscription::whereIn('child_id', $childIds)
            ->where('status', 'active')
            ->with(['school'])
            ->get();

        $driverIds = $subscriptions->pluck('driver_id')->unique()->toArray();

        $activeTrips = Trip::whereIn('driver_id', $driverIds)
            ->where('status', 'started')
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->get();

        $result = [];

        foreach ($activeTrips as $trip) {
            $cacheKey = "driver_last_loc_{$trip->driver_id}";
            $cachedLoc = Cache::get($cacheKey);

            $driverLat = $cachedLoc['lat'] ?? $trip->driver->current_lat ?? 32.887201;
            $driverLng = $cachedLoc['lng'] ?? $trip->driver->current_lng ?? 13.191345;

            $firstSub = $subscriptions->where('driver_id', $trip->driver_id)->first();
            $school = optional($firstSub?->school);
            $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

            $result[] = [
                'trip_id' => $trip->id,
                'driver_location' => [
                    'lat' => (float)$driverLat,
                    'lng' => (float)$driverLng,
                ],
                'destination' => [
                    'lat' => (float)($direction === 'to_school' ? ($school->latitude ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                    'lng' => (float)($direction === 'to_school' ? ($school->longitude ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
                ]
            ];
        }

        return $result;
    }
}