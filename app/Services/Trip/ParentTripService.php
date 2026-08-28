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
            ->where('status', 'in_progress')
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->with(['driver.user', 'driver.vehicles'])
            ->get();

        $result = [];

        foreach ($activeTrips as $trip) {
            $subChildren = $subscriptions->where('driver_id', $trip->driver_id);
            $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';
            $childrenArray = [];

            foreach ($subChildren as $sub) {
                $childObj = $sub->child;
                if (!$childObj) continue;

                // trip_stops هو مصدر الحقيقة الدقيق (boarded/absent_late/dropped_off_school/...)؛
                // نرجع لـ trip_events/absence_logs فقط للرحلات القديمة التي لا تملك trip_stops بعد
                $stop = DB::table('trip_stops')
                    ->where('trip_id', $trip->id)
                    ->where('child_id', $childObj->id)
                    ->where('stop_type', 'home')
                    ->first();

                if ($stop) {
                    $childStatus = $stop->status;
                } else {
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
                }

                $rawPhoto = $childObj->photo_url ?? null;
                $photoUrl = $rawPhoto ? (str_starts_with($rawPhoto, 'http') ? $rawPhoto : Storage::url($rawPhoto)) : asset('assets/images/default-child.png');

                $childSchool = optional($sub->school);
                $destName = $direction === 'to_school' ? ($childSchool->name ?? 'المدرسة') : 'المنزل';
                $destType = $direction === 'to_school' ? 'school' : 'home';
                $destLat  = (float)($direction === 'to_school' ? ($childSchool->lat ?? $sub->dropoff_lat ?? 32.890000) : ($sub->dropoff_lat ?? $sub->pickup_lat ?? 32.890000));
                $destLng  = (float)($direction === 'to_school' ? ($childSchool->lng ?? $sub->dropoff_lng ?? 13.180000) : ($sub->dropoff_lng ?? $sub->pickup_lng ?? 13.180000));

                $childrenArray[] = [
                    'child_id'     => $childObj->id,
                    'child_name'   => $childObj->full_name ?? $childObj->name,
                    'child_photo'  => $photoUrl,
                    'child_status' => $childStatus,
                    'destination'  => [
                        'name' => $destName,
                        'type' => $destType,
                        'lat'  => $destLat,
                        'lng'  => $destLng,
                    ],
                ];
            }

            $driver = $trip->driver;
            $driverUser = $driver?->user;
            $vehicle = optional($driver?->vehicles)->first();
            $firstSub = $subChildren->first();
            $school = optional($firstSub?->school);

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
                    'lat'  => (float)($direction === 'to_school' ? ($school->lat ?? $firstSub->dropoff_lat ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                    'lng'  => (float)($direction === 'to_school' ? ($school->lng ?? $firstSub->dropoff_lng ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
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
                'lat'  => (float)($direction === 'to_school' ? ($school->lat ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                'lng'  => (float)($direction === 'to_school' ? ($school->lng ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
            ],
            'last_updated' => $lastUpdated,
            'is_online'    => $isOnline
        ];
    }

    /**
     * 3. GET /api/parent/trips/upcoming
     * عرض الرحلات القادمة المجمعة على مستوى الرحلة
     */
    public function getUpcomingTrips(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        $subscriptions = ActiveSubscription::whereHas('child', function ($q) use ($parentIds) {
            $q->whereIn('parent_id', $parentIds);
        })
        ->where('status', 'active')
        ->with(['child', 'driver.user', 'school', 'subscriptionRequest'])
        ->get();

        $upcoming = [];
        $today = Carbon::today()->toDateString();

        $subsByDriver = $subscriptions->groupBy('driver_id');

        foreach ($subsByDriver as $driverId => $subs) {
            $driver = $subs->first()?->driver;
            $driverUser = $driver?->user;
            $driverName = $driverUser?->full_name ?? $driverUser?->name ?? 'عبد السلام المصراتي';

            $completedToday = Trip::where('driver_id', $driverId)
                ->whereDate('trip_date', $today)
                ->where('status', 'completed')
                ->pluck('trip_type')
                ->toArray();

            // 1. رحلة الذهاب صباحاً (Morning)
            if (!in_array('Morning', $completedToday)) {
                $childrenArr = [];
                $totalCost = 0.0;

                foreach ($subs as $s) {
                    $c = $s->child;
                    if (!$c) continue;
                    $req = $s->subscriptionRequest;
                    $costPerChildNum = (float)($req?->trip_price ?? 15.00);
                    $totalCost += $costPerChildNum;

                    $childrenArr[] = [
                        'child_id'    => (int)$c->id,
                        'child_name'  => $c->full_name ?? $c->name,
                        'school_name' => optional($s->school)->name ?? 'مدرسة الجيل الجديد الدولية',
                    ];
                }

                if (!empty($childrenArr)) {
                    $firstSub = $subs->first();
                    $school = optional($firstSub->school);
                    $costPerChildFormatted = number_format(($totalCost / count($childrenArr)), 2, '.', '');

                    $upcoming[] = [
                        'trip_id'       => (int) ($firstSub->id * 100 + 1),
                        'trip_type'     => 'Morning',
                        'title'         => 'رحلة الذهاب للمدرسة',
                        'scheduled_for' => 'اليوم صباحاً',
                        'driver'        => [
                            'name' => $driverName,
                        ],
                        'destination'   => [
                            'type' => 'school',
                            'name' => $school->name ?? 'مدرسة الجيل الجديد الدولية',
                        ],
                        'children'       => $childrenArr,
                        'total_children' => count($childrenArr),
                        'pricing'        => [
                            'total_trip_cost' => number_format($totalCost, 2, '.', ''),
                            'cost_per_child'  => $costPerChildFormatted,
                            'currency'        => 'LYD',
                        ],
                    ];
                }
            }

            // 2. رحلة العودة ظهراً (Afternoon)
            if (!in_array('Afternoon', $completedToday)) {
                $childrenArr = [];
                $totalCost = 0.0;

                foreach ($subs as $s) {
                    $c = $s->child;
                    if (!$c) continue;
                    $req = $s->subscriptionRequest;
                    $costPerChildNum = (float)($req?->trip_price ?? 15.00);
                    $totalCost += $costPerChildNum;

                    $childrenArr[] = [
                        'child_id'    => (int)$c->id,
                        'child_name'  => $c->full_name ?? $c->name,
                        'school_name' => optional($s->school)->name ?? 'مدرسة الجيل الجديد الدولية',
                    ];
                }

                if (!empty($childrenArr)) {
                    $firstSub = $subs->first();
                    $costPerChildFormatted = number_format(($totalCost / count($childrenArr)), 2, '.', '');

                    $upcoming[] = [
                        'trip_id'       => (int) ($firstSub->id * 100 + 2),
                        'trip_type'     => 'Afternoon',
                        'title'         => 'رحلة العودة للمنزل',
                        'scheduled_for' => 'اليوم ظهراً',
                        'driver'        => [
                            'name' => $driverName,
                        ],
                        'destination'   => [
                            'type' => 'home',
                            'name' => 'المنزل',
                        ],
                        'children'       => $childrenArr,
                        'total_children' => count($childrenArr),
                        'pricing'        => [
                            'total_trip_cost' => number_format($totalCost, 2, '.', ''),
                            'cost_per_child'  => $costPerChildFormatted,
                            'currency'        => 'LYD',
                        ],
                    ];
                }
            }
        }

        return $upcoming;
    }

    /**
     * 4. GET /api/parent/trips/history
     * أرشيف كل الرحلات السابقة مجمعة على مستوى الرحلة الواحدة
     */
    public function getTripHistory(int $userId, int $perPage = 15): array
    {
        $parentIds = $this->resolveParentIds($userId);
        $childIds = DB::table('children')->whereIn('parent_id', $parentIds)->pluck('id')->toArray();

        if (empty($childIds)) {
            return [
                'current_page' => 1,
                'per_page'     => $perPage,
                'total'        => 0,
                'data'         => [],
            ];
        }

        $paginatedTrips = Trip::whereHas('events', function ($q) use ($childIds) {
                $q->whereIn('child_id', $childIds);
            })
            ->orWhereHas('activeSubscriptions', function ($q) use ($childIds) {
                $q->whereIn('child_id', $childIds);
            })
            ->with(['driver.user'])
            ->orderBy('trip_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $transformedTrips = [];

        foreach ($paginatedTrips->items() as $trip) {
            $events = DB::table('trip_events')
                ->where('trip_id', $trip->id)
                ->whereIn('trip_events.child_id', $childIds)
                ->join('children', 'trip_events.child_id', '=', 'children.id')
                ->leftJoin('schools', 'children.school_id', '=', 'schools.id')
                ->leftJoin('active_subscriptions', function ($join) use ($trip) {
                    $join->on('children.id', '=', 'active_subscriptions.child_id')
                         ->where('active_subscriptions.driver_id', '=', $trip->driver_id);
                })
                ->select(
                    'children.id as child_id',
                    'children.full_name as child_name',
                    'trip_events.action_type',
                    'trip_events.scanned_at',
                    'trip_events.trip_cost',
                    DB::raw('COALESCE(schools.name, "مدرسة الجيل الجديد الدولية") as school_name')
                )
                ->get();

            $childrenArr = [];
            $totalTripCostNum = 0.0;
            $latestScannedAt = null;
            $primaryActionType = 'picked_up';

            foreach ($events as $e) {
                $costNum = (float)($e->trip_cost > 0 ? $e->trip_cost : 15.00);
                $totalTripCostNum += $costNum;
                if ($e->scanned_at && (!$latestScannedAt || $e->scanned_at > $latestScannedAt)) {
                    $latestScannedAt = $e->scanned_at;
                }
                if ($e->action_type) {
                    $primaryActionType = $e->action_type;
                }

                $childrenArr[] = [
                    'child_id'    => (int)$e->child_id,
                    'child_name'  => $e->child_name,
                    'school_name' => $e->school_name,
                    'trip_cost'   => number_format($costNum, 2, '.', ''),
                ];
            }

            if (empty($childrenArr)) {
                $subs = ActiveSubscription::whereIn('child_id', $childIds)
                    ->where('driver_id', $trip->driver_id)
                    ->with(['child', 'school'])
                    ->get();

                foreach ($subs as $sub) {
                    $c = $sub->child;
                    if (!$c) continue;
                    $costNum = 15.00;
                    $totalTripCostNum += $costNum;
                    $childrenArr[] = [
                        'child_id'    => (int)$c->id,
                        'child_name'  => $c->full_name ?? $c->name,
                        'school_name' => optional($sub->school)->name ?? 'مدرسة الجيل الجديد الدولية',
                        'trip_cost'   => '15.00',
                    ];
                }
            }

            if (empty($childrenArr)) {
                continue;
            }

            $driverUser = $trip->driver?->user;
            $driverName = $driverUser?->full_name ?? $driverUser?->name ?? 'عبد السلام المصراتي';
            $scannedAtFormatted = $latestScannedAt 
                ? Carbon::parse($latestScannedAt)->format('Y-m-d H:i:s') 
                : ($trip->actual_start_time 
                    ? Carbon::parse($trip->actual_start_time)->format('Y-m-d H:i:s') 
                    : Carbon::parse($trip->created_at ?? now())->format('Y-m-d H:i:s'));

            $transformedTrips[] = [
                'trip_id'     => (int)$trip->id,
                'trip_type'   => $trip->trip_type ?? 'Morning',
                'trip_date'   => $trip->trip_date ? Carbon::parse($trip->trip_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d'),
                'driver'      => [
                    'name' => $driverName,
                ],
                'action_type' => $primaryActionType,
                'scanned_at'  => $scannedAtFormatted,
                'children'    => $childrenArr,
                'pricing'     => [
                    'total_trip_cost' => number_format($totalTripCostNum > 0 ? $totalTripCostNum : (count($childrenArr) * 15.00), 2, '.', ''),
                    'currency'        => 'LYD',
                ],
            ];
        }

        return [
            'current_page' => $paginatedTrips->currentPage(),
            'per_page'     => $paginatedTrips->perPage(),
            'total'        => $paginatedTrips->total(),
            'data'         => $transformedTrips,
        ];
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
            ->with(['child.school'])
            ->get();

        $direction = strtolower($trip->trip_type) === 'afternoon' ? 'to_home' : 'to_school';

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

            // Read school name from child->school (reliable) or subscription school
            $schoolName = optional($c->school)->name ?? optional($sub->school)->name ?? 'المدرسة';

            $childrenArray[] = [
                'child_id'     => $c->id,
                'child_name'   => $c->full_name ?? $c->name,
                'child_photo'  => $photoUrl,
                'child_status' => $event->action_type ?? 'waiting',
                'school_name'  => $schoolName,
                'direction'    => $direction,
            ];
        }

        $driver = $trip->driver;
        $driverUser = $driver?->user;
        $vehicle = optional($driver?->vehicles)->first();
        $firstSub = $subscriptions->first();
        // Read school from first child's school (reliable)
        $school = optional($firstSub?->child?->school);
        // Fallback pickup coords from subscription
        $pickupLat = $firstSub?->pickup_lat ?? 32.890000;
        $pickupLng = $firstSub?->pickup_lng ?? 13.180000;

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
                'lat'  => (float)($direction === 'to_school' ? ($school->lat ?? 32.890000) : ($pickupLat ?? 32.890000)),
                'lng'  => (float)($direction === 'to_school' ? ($school->lng ?? 13.180000) : ($pickupLng ?? 13.180000)),
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
                ->where('status', 'in_progress')
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
        $stop = DB::table('trip_stops')
            ->where('trip_id', $tripId)
            ->where('child_id', $childId)
            ->where('stop_type', 'home')
            ->first();

        if ($stop) {
            return [
                'child_id' => $childId,
                'status'   => $stop->status,
                'time'     => $stop->updated_at ? Carbon::parse($stop->updated_at)->format('H:i') : null,
            ];
        }

        // توافقية: رحلات قديمة بلا trip_stops بعد
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
     * 8.1. GET /api/parent/trips/{tripId}/children/{childId}/progress
     * خطوات التقدم والتتبع المخصصة للطفل في رحلة معينة
     */
    public function getChildTripProgress(int $userId, int $tripId, int $childId): array
    {
        $trip = Trip::findOrFail($tripId);

        $child = DB::table('children')->where('id', $childId)->first();
        if (!$child) {
            throw new \Exception('بيانات الطفل غير موجودة.');
        }

        $isAfternoon = strtolower($trip->trip_type) === 'afternoon';

        $pickupEvent = DB::table('trip_events')
            ->where('trip_id', $tripId)
            ->where('child_id', $childId)
            ->where('action_type', 'picked_up')
            ->first();

        $dropoffEvent = DB::table('trip_events')
            ->where('trip_id', $tripId)
            ->where('child_id', $childId)
            ->where('action_type', 'dropped_off')
            ->first();

        $isStarted = !empty($trip->actual_start_time) || !empty($trip->started_at) || in_array($trip->status, ['in_progress', 'completed']);
        $startTime = $trip->actual_start_time ? Carbon::parse($trip->actual_start_time)->format('Y-m-d H:i:s') : ($trip->started_at ? Carbon::parse($trip->started_at)->format('Y-m-d H:i:s') : null);

        $isPickedUp = !empty($pickupEvent) || !empty($dropoffEvent);
        $pickupTime = $pickupEvent ? Carbon::parse($pickupEvent->scanned_at)->format('Y-m-d H:i:s') : null;

        $isArrived = !empty($dropoffEvent);
        $dropoffTime = $dropoffEvent ? Carbon::parse($dropoffEvent->scanned_at)->format('Y-m-d H:i:s') : null;

        $isCompleted = $trip->status === 'completed' && !empty($trip->completed_at);
        $completedTime = $trip->completed_at ? Carbon::parse($trip->completed_at)->format('Y-m-d H:i:s') : null;

        $steps = [
            [
                'key'       => 'started',
                'title'     => 'انطلقت',
                'completed' => (bool)$isStarted,
                'timestamp' => $startTime
            ],
            [
                'key'       => 'picked_up',
                'title'     => 'في الطريق',
                'completed' => (bool)$isPickedUp,
                'timestamp' => $pickupTime
            ],
            [
                'key'       => $isAfternoon ? 'arrived_home' : 'arrived_school',
                'title'     => 'الاستلام',
                'completed' => (bool)$isArrived,
                'timestamp' => $dropoffTime
            ],
            [
                'key'       => 'completed',
                'title'     => $isAfternoon ? 'وصلت للمنزل' : 'وصلت للمدرسة',
                'completed' => (bool)$isCompleted,
                'timestamp' => $completedTime
            ]
        ];

        $currentStep = 0;
        if ($isCompleted) {
            $currentStep = 4;
        } elseif ($isArrived) {
            $currentStep = 3;
        } elseif ($isPickedUp) {
            $currentStep = 2;
        } elseif ($isStarted) {
            $currentStep = 1;
        }

        return [
            'trip_id'      => (int)$tripId,
            'child_id'     => (int)$childId,
            'child_name'   => $child->full_name ?? $child->name ?? 'الطفل',
            'current_step' => $currentStep,
            'steps'        => $steps
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
            ->where('status', 'in_progress')
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
                    'lat' => (float)($direction === 'to_school' ? ($school->lat ?? 32.890000) : ($firstSub->pickup_lat ?? 32.890000)),
                    'lng' => (float)($direction === 'to_school' ? ($school->lng ?? 13.180000) : ($firstSub->pickup_lng ?? 13.180000)),
                ]
            ];
        }

        return $result;
    }
}