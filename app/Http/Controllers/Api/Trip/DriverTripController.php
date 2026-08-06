<?php

namespace App\Http\Controllers\Api\Trip;

use App\Http\Controllers\Controller;
use App\Models\Shared\Trip;
use App\Models\Shared\TripEvent;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\ActiveSubscription;
use App\Services\Trip\TripLifecycleService;
use App\Services\Trip\TripTrackingService;
use App\Services\Trip\TripStopService;
use App\Services\Trip\RouteRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use Throwable;

class DriverTripController extends Controller
{
    protected TripLifecycleService $lifecycleService;
    protected TripTrackingService $trackingService;
    protected TripStopService $stopService;
    protected RouteRecommendationService $recommendationService;
    protected \App\Services\Trip\DailyTripGenerationService $dailyTripGenerationService;
    protected \App\Services\Trip\GeofenceService $geofenceService;

    public function __construct(
        TripLifecycleService $lifecycleService,
        TripTrackingService $trackingService,
        TripStopService $stopService,
        RouteRecommendationService $recommendationService,
        \App\Services\Trip\DailyTripGenerationService $dailyTripGenerationService,
        \App\Services\Trip\GeofenceService $geofenceService
    ) {
        $this->lifecycleService = $lifecycleService;
        $this->trackingService = $trackingService;
        $this->stopService = $stopService;
        $this->recommendationService = $recommendationService;
        $this->dailyTripGenerationService = $dailyTripGenerationService;
        $this->geofenceService = $geofenceService;
    }

    /**
     * 1️⃣ GET /api/driver/trips/today
     * جلب رحلات اليوم الخاصة بالسائق
     */
    public function todayTrips(): JsonResponse
    {
        try {
            $user = Auth::user();
            $driver = $user?->driver;

            if (!$driver) {
                return response()->json(['status' => 'error', 'message' => 'بيانات السائق غير مقترنة بالحساب.'], 403);
            }

            $today = Carbon::today()->toDateString();

            // جلب رحلات اليوم من جدول Trips أو إنشائها تلقائياً بناءً على المسارات النشطة
            $activeRoutes = RouteModel::where('driver_id', $driver->id)
                ->where('status', 'Active')
                ->get();

            $trips = [];

            foreach ($activeRoutes as $route) {
                $trip = $this->dailyTripGenerationService->generateForRoute($route);

                if (!$trip || strtolower($trip->status) === 'cancelled') {
                    continue;
                }

                $metrics = $this->recommendationService->calculateRouteMetrics($route);

                $formattedStatus = strtolower($trip->status);

                $trips[] = [
                    'trip_id'               => (int) $trip->id,
                    'route_id'              => (int) $route->id,
                    'route_name'            => $route->route_name,
                    'trip_type'             => $route->route_type,
                    'status'                => $formattedStatus,
                    'children_count'        => $metrics['children_count'],
                    'schools_count'         => $metrics['schools_count'],
                    'estimated_duration'   => $metrics['estimated_duration'],
                    'recommended_departure' => $metrics['recommended_departure'],
                    'started_at'            => $trip->actual_start_time ? Carbon::parse($trip->actual_start_time)->format('H:i') : null,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data'   => $trips
            ], 200);

        } catch (Throwable $e) {
            Log::error("Error in todayTrips: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * يحوّل حالة trip_stops الدقيقة إلى: القيمة الخام + pickup_status/dropoff_status المبسّطة (توافقية مع الفرونت الحالي)
     */
    private function tripStopStatusFields(?\App\Models\Shared\TripStop $stop): array
    {
        $raw = $stop->status ?? \App\Models\Shared\TripStop::STATUS_PENDING;

        $boardedLike = [
            \App\Models\Shared\TripStop::STATUS_BOARDED,
            \App\Models\Shared\TripStop::STATUS_DROPPED_OFF_SCHOOL,
            \App\Models\Shared\TripStop::STATUS_DELIVERED_HOME,
            \App\Models\Shared\TripStop::STATUS_DROPOFF_FAILED,
            \App\Models\Shared\TripStop::STATUS_DIRECT_PARENT_HANDLING,
        ];
        $absentLike = [\App\Models\Shared\TripStop::STATUS_ABSENT_PRE, \App\Models\Shared\TripStop::STATUS_ABSENT_LATE];
        $deliveredLike = [\App\Models\Shared\TripStop::STATUS_DROPPED_OFF_SCHOOL, \App\Models\Shared\TripStop::STATUS_DELIVERED_HOME];

        $pickupStatus = 'pending';
        if (in_array($raw, $boardedLike, true)) $pickupStatus = 'picked_up';
        elseif (in_array($raw, $absentLike, true)) $pickupStatus = 'absent';
        elseif ($raw === \App\Models\Shared\TripStop::STATUS_SKIPPED_UNRESPONSIVE) $pickupStatus = 'skipped';

        return [
            'status'         => $raw,
            'pickup_status'  => $pickupStatus,
            'dropoff_status' => in_array($raw, $deliveredLike, true) ? 'dropped_off' : 'pending',
            'eta'            => $stop?->eta ? substr((string) $stop->eta, 0, 5) : null,
            'sequence_order' => $stop?->sequence_order !== null ? (int) $stop->sequence_order : null,
        ];
    }

    /**
     * توافقية: يُستخدم فقط للرحلات القديمة التي لا تملك trip_stops بعد (قبل تفعيل المسار الرئيسي الجديد)
     */
    private function legacyStatusFromEvents(int $childId, $events): array
    {
        $pickupEvent = $events->where('child_id', $childId)->whereIn('action_type', ['picked_up', 'skipped', 'absent'])->first();
        $dropoffEvent = $events->where('child_id', $childId)->where('action_type', 'dropped_off')->first();

        $pickupStatus = 'pending';
        if ($pickupEvent) {
            if ($pickupEvent->action_type === 'picked_up') $pickupStatus = 'picked_up';
            elseif ($pickupEvent->action_type === 'skipped') $pickupStatus = 'skipped';
            elseif ($pickupEvent->action_type === 'absent') $pickupStatus = 'absent';
        }
        $dropoffStatus = $dropoffEvent ? 'dropped_off' : 'pending';

        return [
            'status'         => $dropoffStatus === 'dropped_off' ? 'delivered_home' : $pickupStatus,
            'pickup_status'  => $pickupStatus,
            'dropoff_status' => $dropoffStatus,
            'eta'            => null,
            'sequence_order' => null,
        ];
    }

    /**
     * 2️⃣ GET /api/driver/trips/{tripId}
     * تفاصيل الرحلة
     */
    public function show($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driver = $user?->driver;

            $trip = Trip::where('id', $tripId)
                ->where('driver_id', $driver->id)
                ->firstOrFail();

            $route = RouteModel::where('id', $trip->route_id)->first();
            $subs = ActiveSubscription::where('driver_id', $driver->id)
                ->where(function ($q) use ($trip, $route) {
                    $q->where('route_id', $trip->route_id);
                    if ($route?->contract_id) {
                        $q->orWhere('contract_id', $route->contract_id);
                    }
                })
                ->where('status', '!=', 'cancelled')
                ->with(['child.school', 'school'])
                ->get();

            if ($subs->isEmpty()) {
                $childIdsFromStopsOrEvents = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                    ->pluck('child_id')
                    ->merge(TripEvent::where('trip_id', $trip->id)->pluck('child_id'))
                    ->filter()
                    ->unique();

                if ($childIdsFromStopsOrEvents->isNotEmpty()) {
                    $subs = ActiveSubscription::whereIn('child_id', $childIdsFromStopsOrEvents)
                        ->with(['child.school', 'school'])
                        ->get();
                }

                if ($subs->isEmpty()) {
                    $subs = ActiveSubscription::where('driver_id', $driver->id)
                        ->where('status', '!=', 'cancelled')
                        ->with(['child.school', 'school'])
                        ->get();
                }
            }

            // مزامنة أية محطات ناقصة تلقائياً للرحلة
            $existingStopsCount = \App\Models\Shared\TripStop::where('trip_id', $trip->id)->count();
            if ($existingStopsCount === 0 && $subs->isNotEmpty()) {
                $seq = 1;
                foreach ($subs as $subItem) {
                    if ($subItem->child_id) {
                        \App\Models\Shared\TripStop::create([
                            'trip_id'        => $trip->id,
                            'stop_type'      => 'home',
                            'child_id'       => $subItem->child_id,
                            'school_id'      => $subItem->school_id,
                            'lat'            => $subItem->child?->latitude ?? $driver->latitude ?? 32.8872,
                            'lng'            => $subItem->child?->longitude ?? $driver->longitude ?? 13.1913,
                            'label'          => $subItem->pickup_label ?? 'الحي السكني',
                            'sequence_order' => $seq++,
                            'status'         => 'pending',
                        ]);
                    }
                }
            }

            $schoolsGrouped = $subs->groupBy('school_id')->map(function ($group) {
                $first = $group->first();
                $sName = optional($first->school)->name ?? optional($first->child?->school)->name ?? 'المدرسة';
                return [
                    'school_id'      => (int) $first->school_id,
                    'name'           => $sName,
                    'children_count' => $group->count(),
                ];
            })->values();

            $events = TripEvent::where('trip_id', $trip->id)->get();

            $stopsByChild = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                ->where('stop_type', 'home')
                ->get()
                ->keyBy('child_id');

            $children = $subs->map(function ($sub) use ($events, $stopsByChild) {
                $stop = $stopsByChild->get($sub->child_id);
                $statusFields = $stop
                    ? $this->tripStopStatusFields($stop)
                    : $this->legacyStatusFromEvents($sub->child_id, $events);

                $childSchoolName = optional($sub->school)->name 
                    ?? optional($sub->child?->school)->name 
                    ?? optional(\App\Models\Parent\School::find($sub->school_id ?? $sub->child?->school_id))->name 
                    ?? 'المدرسة';

                return [
                    'trip_child_id'   => (int) $sub->id,
                    'child_id'        => (int) $sub->child_id,
                    'name'            => $sub->child->full_name ?? $sub->child->name ?? 'طفل',
                    'school'          => $childSchoolName,
                    'school_name'     => $childSchoolName,
                    'pickup_address'  => $sub->pickup_label ?? 'الحي السكني',
                    'dropoff_address' => $childSchoolName,
                    'status'          => $statusFields['status'],
                    'pickup_status'   => $statusFields['pickup_status'],
                    'dropoff_status'  => $statusFields['dropoff_status'],
                    'eta'             => $statusFields['eta'],
                    'sequence_order'  => $statusFields['sequence_order'],
                ];
            })->values();

            $routeName = $route?->formatted_route_name ?? RouteModel::generateGenericRouteName(null, 'both', $trip->trip_type);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'               => (int) $trip->id,
                    'trip_type'             => $trip->trip_type,
                    'route_name'            => $routeName,
                    'status'                => strtolower($trip->status),
                    'suspension_reason'     => $trip->suspension_reason,
                    'trip_date'             => $trip->trip_date ? Carbon::parse($trip->trip_date)->format('Y-m-d') : Carbon::today()->format('Y-m-d'),
                    'recommended_departure' => '07:00',
                    'estimated_duration'   => (int) ($route?->estimated_duration ?? 45),
                    'vehicle' => [
                        'plate'    => $driver->vehicle?->plate_number ?? '5-12345',
                        'capacity' => (int) ($driver->vehicle?->capacity_manual ?? 14),
                    ],
                    'statistics' => [
                        'children' => $children->count(),
                        'schools'  => $schoolsGrouped->count(),
                    ],
                    'schools'  => $schoolsGrouped,
                    'children' => $children,
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'الرحلة غير موجودة.'], 404);
        }
    }

    /**
     * 3️⃣ POST /api/driver/trips/{tripId}/start
     * بدء الرحلة
     */
    public function start(Request $request, $tripId = null): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $lat = $request->has('latitude') ? (float) $request->latitude : ($request->has('lat') ? (float) $request->lat : null);
            $lng = $request->has('longitude') ? (float) $request->longitude : ($request->has('lng') ? (float) $request->lng : null);

            $targetTripId = $tripId ?? $request->trip_id;

            if ($targetTripId) {
                $trip = Trip::where('id', $targetTripId)->where('driver_id', $driverId)->firstOrFail();
                $trip->status = 'in_progress';
                $trip->actual_start_time = now();
                $trip->started_at = now();

                if ($lat !== null && $lng !== null) {
                    $trip->start_lat = $lat;
                    $trip->start_lng = $lng;
                    \App\Models\Driver\Driver::where('id', $driverId)->update(['current_lat' => $lat, 'current_lng' => $lng]);
                }

                $trip->save();

                if ($lat !== null && $lng !== null) {
                    try {
                        $this->lifecycleService->computeLiveEtas($trip, $lat, $lng);
                    } catch (\Throwable $e) {
                        Log::warning("فشل حساب ETAs الحية عند بدء الرحلة ID: {$trip->id} - " . $e->getMessage());
                    }
                }
            } else {
                $tripType = $request->trip_type ?? 'Morning';
                $trip = $this->lifecycleService->startTrip($driverId, $tripType, $lat, $lng);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'تم بدء الرحلة.',
                'data'    => [
                    'trip_id'    => (int) $trip->id,
                    'status'     => 'in_progress',
                    'started_at' => Carbon::now()->format('H:i'),
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 4️⃣ GET /api/driver/trips/{tripId}/live
     * شاشة الرحلة المباشرة
     */
    public function live($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driver = $user?->driver;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driver->id)->firstOrFail();
            $subs = ActiveSubscription::where('driver_id', $driver->id)
                ->where('route_id', $trip->route_id)
                ->where('status', '!=', 'cancelled')
                ->with(['child', 'school'])
                ->get();

            $events = TripEvent::where('trip_id', $trip->id)->get();

            $homeStops = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                ->where('stop_type', 'home')
                ->orderBy('sequence_order')
                ->get();

            if ($homeStops->isNotEmpty()) {
                $total = $homeStops->count();
                $completed = $homeStops->whereNotIn('status', \App\Models\Shared\TripStop::NON_FINAL_STATUSES)->count();

                $currentStop = $homeStops
                    ->whereIn('status', \App\Models\Shared\TripStop::NON_FINAL_STATUSES)
                    ->sortBy('sequence_order')
                    ->first();

                $currentSub = $currentStop ? $subs->firstWhere('child_id', $currentStop->child_id) : null;
                $currentStatusFields = $currentStop ? $this->tripStopStatusFields($currentStop) : null;
            } else {
                // توافقية: رحلات قديمة بلا trip_stops بعد
                $total = $subs->count();
                $completed = $events->pluck('child_id')->unique()->count();

                $currentSub = $subs->first(function ($s) use ($events) {
                    return !$events->where('child_id', $s->child_id)->exists();
                }) ?? $subs->first();
                $currentStatusFields = $currentSub ? $this->legacyStatusFromEvents($currentSub->child_id, $events) : null;
            }

            $remaining = max(0, $total - $completed);

            $currentChildData = null;
            if ($currentSub) {
                $currentChildData = [
                    'trip_child_id'   => (int) $currentSub->id,
                    'child_id'        => (int) $currentSub->child_id,
                    'name'            => $currentSub->child->full_name ?? $currentSub->child->name ?? 'طفل',
                    'school'          => optional($currentSub->school)->name ?? 'المدرسة',
                    'pickup_address'  => $currentSub->pickup_label ?? 'حي الأندلس',
                    'latitude'        => (float) ($currentSub->pickup_lat ?? 32.880000),
                    'longitude'       => (float) ($currentSub->pickup_lng ?? 13.180000),
                    'status'          => $currentStatusFields['status'] ?? 'pending',
                    'pickup_status'   => $currentStatusFields['pickup_status'] ?? 'pending',
                    'dropoff_status'  => $currentStatusFields['dropoff_status'] ?? 'pending',
                    'eta'             => $currentStatusFields['eta'] ?? null,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_status'   => strtolower($trip->status),
                    'current_child' => $currentChildData,
                    'progress'      => [
                        'total'     => $total,
                        'completed' => $completed,
                        'remaining' => $remaining,
                    ]
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 5️⃣ POST /api/driver/trips/{tripId}/location
     * تحديث موقع GPS
     */
    public function updateLocation(Request $request, $tripId): JsonResponse
    {
        $driverId = Auth::user()->driver->id;
        $lat = $request->latitude ?? $request->lat;
        $lng = $request->longitude ?? $request->lng;
        $speed = $request->speed ?? 0;

        $this->trackingService->updateDriverLocation($tripId, $lat, $lng, $speed);
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * GET /api/v1/driver/trips/{tripId}/stops
     * القائمة الكاملة والمرتبة لمحطات الرحلة اليومية (trip_stops) بحالتها الدقيقة و ETA المحسوبة عند البدء
     */
    public function tripStops($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            $stops = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                ->with(['child', 'school'])
                ->orderBy('sequence_order')
                ->get()
                ->map(function ($stop) {
                    return [
                        'id'             => (int) $stop->id,
                        'stop_type'      => $stop->stop_type,
                        'sequence_order' => (int) $stop->sequence_order,
                        'status'         => $stop->status,
                        'child_id'       => $stop->child_id ? (int) $stop->child_id : null,
                        'child_name'     => $stop->child?->full_name ?? $stop->child?->name,
                        'school_id'      => $stop->school_id ? (int) $stop->school_id : null,
                        'school_name'    => $stop->school?->name,
                        'label'          => $stop->label,
                        'latitude'       => (float) $stop->lat,
                        'longitude'      => (float) $stop->lng,
                        'eta'            => $stop->eta ? substr((string) $stop->eta, 0, 5) : null,
                        'eta_minutes'    => $stop->eta_minutes !== null ? (int) $stop->eta_minutes : null,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'     => (int) $trip->id,
                    'trip_status' => strtolower($trip->status),
                    'stops'       => $stops,
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'الرحلة غير موجودة.'], 404);
        }
    }

    /**
     * POST /api/v1/driver/register-absence
     * تسجيل غياب السائق نفسه في تواريخ محددة — لن تُولَّد رحلات على مساراته في هذه الأيام
     */
    public function registerAbsence(Request $request): JsonResponse
    {
        $request->validate([
            'dates'   => 'required|array|min:1',
            'dates.*' => 'required|date|after_or_equal:today',
        ]);

        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $this->lifecycleService->setDriverAbsence($driverId, $request->dates);

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تسجيل غيابك في التواريخ المحددة، ولن يتم توليد رحلات لمساراتك في هذه الأيام.',
                'data'    => ['dates' => $request->dates],
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/driver/trips/{tripId}/report-breakdown
     * الإبلاغ عن عطل يوقف الرحلة الجارية مؤقتاً ويُخطر أولياء الأمور المتبقين
     */
    public function reportBreakdown(Request $request, $tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            if ($trip->status !== 'in_progress') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'لا يمكن الإبلاغ عن عطل إلا لرحلة قيد التشغيل حالياً.',
                ], 422);
            }

            $trip->status = 'suspended_breakdown';
            $trip->suspension_reason = $request->input('reason');
            $trip->save();

            try {
                $pendingChildIds = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                    ->where('stop_type', 'home')
                    ->whereIn('status', \App\Models\Shared\TripStop::NON_FINAL_STATUSES)
                    ->pluck('child_id');

                if ($pendingChildIds->isNotEmpty()) {
                    $parentUserIds = \App\Models\Parent\Child::whereIn('children.id', $pendingChildIds)
                        ->join('parents', 'children.parent_id', '=', 'parents.id')
                        ->pluck('parents.user_id')
                        ->unique();

                    $users = \App\Models\User::whereIn('id', $parentUserIds)->get();
                    if ($users->isNotEmpty()) {
                        \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\CustomDatabaseNotification([
                            'title'    => '🚨 توقف مؤقت للحافلة',
                            'message'  => 'حدث عطل طارئ وتوقفت الحافلة مؤقتاً، سيتم إبلاغكم فور استئناف الرحلة.',
                            'type'     => 'trip_suspended',
                            'metadata' => ['trip_id' => $trip->id],
                        ]));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("فشل إرسال إشعارات توقف الرحلة ID: {$trip->id} - " . $e->getMessage());
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تسجيل توقف الرحلة مؤقتاً وإخطار أولياء الأمور.',
                'data'    => ['trip_id' => (int) $trip->id, 'status' => 'suspended_breakdown'],
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/v1/driver/trips/{tripId}/resume
     * استئناف رحلة كانت متوقفة بسبب عطل
     */
    public function resumeTrip($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            if ($trip->status !== 'suspended_breakdown') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'هذه الرحلة ليست متوقفة حالياً.',
                ], 422);
            }

            $trip->status = 'in_progress';
            $trip->suspension_reason = null;
            $trip->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم استئناف الرحلة.',
                'data'    => ['trip_id' => (int) $trip->id, 'status' => 'in_progress'],
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 6️⃣ 7️⃣ 8️⃣ 9️⃣ الاندبوينت الموحد والمستقل لتحديث حالة الطفل داخل الرحلة
     * POST /api/driver/trips/{tripId}/children/{tripChildId}/status
     * POST /api/driver/trips/{tripId}/pickup
     * POST /api/driver/trips/{tripId}/dropoff
     * POST /api/driver/trips/{tripId}/absent
     */
    public function updateChildTripStatus(Request $request, $tripId, $tripChildId = null): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            $subId = $tripChildId ?? $request->trip_child_id;
            $action = strtolower($request->action ?? $request->route()->getActionMethod());

            // ⚠️ يجب فحص الأسماء الأكثر تحديداً أولاً (dropoff_failed يحتوي جزئياً على "dropoff")
            if (str_contains($action, 'dropoff_failed')) $action = 'dropoff_failed';
            elseif (str_contains($action, 'direct_parent') || str_contains($action, 'parent_handling')) $action = 'direct_parent_handling';
            elseif (str_contains($action, 'pickup')) $action = 'pickup';
            elseif (str_contains($action, 'dropoff')) $action = 'dropoff';
            elseif (str_contains($action, 'absent')) $action = 'absent';
            elseif (str_contains($action, 'skip')) $action = 'skip';

            // 🔒 التحقق من ملكية الاشتراك للسائق الحالي لمنع التلاعب باشتراكات سائقين آخرين (IDOR)
            $sub = ActiveSubscription::where('id', $subId)->where('driver_id', $driverId)->firstOrFail();
            $childId = $sub->child_id;

            // trip_events.trip_type/location_lat/location_lng أعمدة إلزامية (NOT NULL) في قاعدة البيانات
            $eventTripType = ($trip->trip_type === 'Morning' || $trip->trip_type === 'ذهاب') ? 'ذهاب' : 'عودة';

            // محطة منزل الطفل في trip_stops — مصدر الحقيقة لحالة الطفل ولإحداثيات فحص الـ Geofence
            $homeStop = \App\Models\Shared\TripStop::where('trip_id', $tripId)
                ->where('child_id', $childId)
                ->where('stop_type', 'home')
                ->first();

            $isQr = strtolower($request->verification_method ?? 'manual') === 'qr';

            // 🎯 المرونة مع الأمان: QR يمنح سرعة وتجاوزاً للنطاق، الزر اليدوي يُقيَّد بـ GPS
            if (in_array($action, ['pickup', 'dropoff'], true)) {
                if ($isQr) {
                    $providedToken = $request->qr_code_token;
                    if (!$providedToken || $providedToken !== ($sub->child->qr_code_token ?? null)) {
                        return response()->json([
                            'status'     => 'error',
                            'error_code' => 'QR_MISMATCH',
                            'message'    => 'كود الـ QR غير متطابق مع هذا الطفل.',
                        ], 400);
                    }
                } elseif ($homeStop) {
                    if (!$request->has('latitude') || !$request->has('longitude')) {
                        return response()->json([
                            'status'     => 'error',
                            'error_code' => 'LOCATION_REQUIRED',
                            'message'    => 'يجب إرسال الموقع الجغرافي الحالي للتأكيد اليدوي، أو استخدام مسح QR.',
                        ], 422);
                    }
                    try {
                        $this->geofenceService->assertWithinRadius((float) $request->latitude, (float) $request->longitude, $homeStop);
                    } catch (\App\Services\Trip\GeofenceViolationException $e) {
                        return response()->json([
                            'status'     => 'error',
                            'error_code' => $e->getErrorCode(),
                            'message'    => $e->getMessage(),
                        ], $e->getCode());
                    }
                }
            }

            if ($action === 'pickup') {
                TripEvent::create([
                    'trip_id'         => $tripId,
                    'child_id'        => $childId,
                    'subscription_id' => $sub->id,
                    'action_type'     => 'picked_up',
                    'trip_type'       => $eventTripType,
                    'scanned_at'      => now(),
                    'location_lat'    => $request->latitude ?? $sub->pickup_lat ?? 0,
                    'location_lng'    => $request->longitude ?? $sub->pickup_lng ?? 0,
                    'trip_cost'       => 0,
                ]);

                $homeStop?->update(['status' => \App\Models\Shared\TripStop::STATUS_BOARDED]);

                // 🔔 إرسال إشعار لحظي FCM لولي الأمر
                try {
                    $parentUser = $sub->parent?->user ?? \App\Models\User::find($sub->parent_id);
                    if ($parentUser) {
                        $childName = $sub->child->full_name ?? $sub->child->name ?? 'طفلك';
                        app(\App\Services\Notification\FcmService::class)->sendPushNotification($parentUser, [
                            'title'   => '🚌 صعود الحافلة بنجاح',
                            'message' => "صعد {$childName} إلى الحافلة الآن بسلام.",
                            'payload' => [
                                'type'     => 'child_picked_up',
                                'trip_id'  => (string) $tripId,
                                'child_id' => (string) $childId,
                                'screen'   => 'LIVE_TRACKING',
                            ]
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("FCM Notification error on pickup: " . $e->getMessage());
                }

                $nextSub = ActiveSubscription::where('driver_id', $driverId)
                    ->where('route_id', $sub->route_id)
                    ->where('id', '>', $sub->id)
                    ->first();

                return response()->json([
                    'status'     => 'success',
                    'message'    => 'تم تأكيد الصعود وإرسال الإشعار لولي الأمر.',
                    'next_child' => $nextSub ? [
                        'trip_child_id' => (int) $nextSub->id,
                        'name'          => $nextSub->child->full_name ?? $nextSub->child->name ?? 'الطفل التالي',
                    ] : null
                ], 200);

            } elseif ($action === 'dropoff') {
                TripEvent::create([
                    'trip_id'         => $tripId,
                    'child_id'        => $childId,
                    'subscription_id' => $sub->id,
                    'action_type'     => 'dropped_off',
                    'trip_type'       => $eventTripType,
                    'scanned_at'      => now(),
                    'location_lat'    => $request->latitude ?? $sub->dropoff_lat ?? 0,
                    'location_lng'    => $request->longitude ?? $sub->dropoff_lng ?? 0,
                    'trip_cost'       => 0,
                ]);

                // اتجاه الرحلة يحدد الوجهة النهائية: ذهاب → المدرسة، إياب → المنزل
                $isGoTrip = \App\Models\Driver\DriverSeatSlot::isGoSlot($trip->shift_slot ?? '')
                    || (!$trip->shift_slot && $trip->trip_type === 'Morning');
                $dropoffStatus = $isGoTrip
                    ? \App\Models\Shared\TripStop::STATUS_DROPPED_OFF_SCHOOL
                    : \App\Models\Shared\TripStop::STATUS_DELIVERED_HOME;

                $homeStop?->update(['status' => $dropoffStatus]);

                // 🔔 إرسال إشعار لحظي FCM لولي الأمر
                try {
                    $parentUser = $sub->parent?->user ?? \App\Models\User::find($sub->parent_id);
                    if ($parentUser) {
                        $childName = $sub->child->full_name ?? $sub->child->name ?? 'طفلك';
                        app(\App\Services\Notification\FcmService::class)->sendPushNotification($parentUser, [
                            'title'   => '🏫 وصول بسلام',
                            'message' => "وصل {$childName} ونزل بالمدرسة/المنزل بسلام.",
                            'payload' => [
                                'type'     => 'child_dropped_off',
                                'trip_id'  => (string) $tripId,
                                'child_id' => (string) $childId,
                                'screen'   => 'TRIP_DETAILS',
                            ]
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("FCM Notification error on dropoff: " . $e->getMessage());
                }

                return response()->json(['status' => 'success', 'message' => 'تم تأكيد النزول وإرسال الإشعار لولي الأمر.'], 200);

            } elseif (in_array($action, ['absent', 'skip', 'dropoff_failed', 'direct_parent_handling'], true)) {
                $actionTypeMap = [
                    'absent'                 => 'absent',
                    'skip'                   => 'skipped',
                    'dropoff_failed'         => 'dropoff_failed',
                    'direct_parent_handling' => 'direct_parent_handling',
                ];
                $stopStatusMap = [
                    'absent'                 => \App\Models\Shared\TripStop::STATUS_ABSENT_LATE,
                    'skip'                   => \App\Models\Shared\TripStop::STATUS_SKIPPED_UNRESPONSIVE,
                    'dropoff_failed'         => \App\Models\Shared\TripStop::STATUS_DROPOFF_FAILED,
                    'direct_parent_handling' => \App\Models\Shared\TripStop::STATUS_DIRECT_PARENT_HANDLING,
                ];
                $notificationMap = [
                    'absent'                 => ['title' => '⚠️ غياب الطفل', 'message' => 'لم يجد السائق {name} في محطة الانتظار، تم تسجيل غيابه.'],
                    'skip'                   => ['title' => '⚠️ تجاوز المحطة', 'message' => 'انتهى وقت الانتظار دون استجابة، تحركت الحافلة وتجاوزت محطة {name}.'],
                    'dropoff_failed'         => ['title' => '🚨 تعذر تسليم الطفل', 'message' => 'تعذر على السائق تسليم {name} في محطة النزول، يرجى التواصل الفوري.'],
                    'direct_parent_handling' => ['title' => 'ℹ️ استلام مباشر من ولي الأمر', 'message' => 'تم تسليم {name} مباشرة لولي الأمر خارج الإجراء المعتاد.'],
                ];

                TripEvent::create([
                    'trip_id'         => $tripId,
                    'child_id'        => $childId,
                    'subscription_id' => $sub->id,
                    'action_type'     => $actionTypeMap[$action],
                    'trip_type'       => $eventTripType,
                    'scanned_at'      => now(),
                    'location_lat'    => $sub->pickup_lat ?? 0,
                    'location_lng'    => $sub->pickup_lng ?? 0,
                    'trip_cost'       => 0,
                ]);

                $homeStop?->update(['status' => $stopStatusMap[$action]]);

                // 🔔 إرسال إشعار لحظي FCM لولي الأمر
                try {
                    $parentUser = $sub->parent?->user ?? \App\Models\User::find($sub->parent_id);
                    if ($parentUser) {
                        $childName = $sub->child->full_name ?? $sub->child->name ?? 'طفلك';
                        $notif = $notificationMap[$action];
                        app(\App\Services\Notification\FcmService::class)->sendPushNotification($parentUser, [
                            'title'   => $notif['title'],
                            'message' => str_replace('{name}', $childName, $notif['message']),
                            'payload' => [
                                'type'     => 'child_' . $action,
                                'trip_id'  => (string) $tripId,
                                'child_id' => (string) $childId,
                                'screen'   => 'TRIP_DETAILS',
                            ]
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("FCM Notification error on {$action}: " . $e->getMessage());
                }

                return response()->json(['status' => 'success', 'message' => 'تم تسجيل الحالة بنجاح.'], 200);
            }

            return response()->json(['status' => 'error', 'message' => 'الإجراء غير معرف.'], 422);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    // Wrappers للـ Routes الفردية
    public function pickup(Request $request, $tripId): JsonResponse
    {
        return $this->updateChildTripStatus($request, $tripId);
    }

    public function dropoff(Request $request, $tripId): JsonResponse
    {
        return $this->updateChildTripStatus($request, $tripId);
    }

    public function absent(Request $request, $tripId): JsonResponse
    {
        return $this->updateChildTripStatus($request, $tripId);
    }

    public function skip($tripId, $childId): JsonResponse
    {
        $request = new Request(['action' => 'skip']);
        return $this->updateChildTripStatus($request, $tripId, $childId);
    }

    public function verifyQr(Request $request, $tripId, $childId): JsonResponse
    {
        $stage = strtolower($request->stage ?? 'pickup') === 'dropoff' ? 'dropoff' : 'pickup';
        $request->merge(['action' => $stage, 'verification_method' => 'qr']);
        return $this->updateChildTripStatus($request, $tripId, $childId);
    }

    /**
     * 🔟 POST /api/driver/trips/{tripId}/complete
     * إنهاء الرحلة وإرجاع الملخص الإحصائي
     */
    public function complete($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            // 🛡️ صمام أمان الأطفال: يرمي TripLifecycleException (422) إذا وُجد طفل بحالة boarded/pending
            $result = $this->lifecycleService->completeTrip($trip->id);

            $events = TripEvent::where('trip_id', $trip->id)->get();
            $totalSubs = ActiveSubscription::where('route_id', $trip->route_id)->count();

            $pickedUp = $events->where('action_type', 'picked_up')->count();
            $droppedOff = $events->where('action_type', 'dropped_off')->count();
            $absent = $events->whereIn('action_type', ['absent', 'skipped', 'dropoff_failed', 'direct_parent_handling'])->count();

            return response()->json([
                'status'  => 'success',
                'message' => $result['message'],
                'summary' => [
                    'children'    => $totalSubs > 0 ? $totalSubs : max(1, $pickedUp + $absent),
                    'picked_up'   => $pickedUp,
                    'dropped_off' => $droppedOff,
                    'absent'      => $absent,
                    'duration'    => 48,
                    'distance'    => 19.3,
                ]
            ], 200);

        } catch (\App\Services\Trip\TripLifecycleException $e) {
            return response()->json([
                'status'     => 'error',
                'error_code' => $e->getErrorCode(),
                'message'    => $e->getMessage(),
            ], $e->getCode());
        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 11️⃣ GET /api/driver/trips/history
     * سجل الرحلات السابقة
     */
    public function history(): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trips = Trip::where('driver_id', $driverId)
                ->where('status', 'completed')
                ->with(['route'])
                ->orderBy('trip_date', 'desc')
                ->get();

            $data = $trips->map(function ($t) {
                return [
                    'trip_id'    => (int) $t->id,
                    'trip_date'  => $t->trip_date ? Carbon::parse($t->trip_date)->format('Y-m-d') : Carbon::parse($t->created_at)->format('Y-m-d'),
                    'route_name' => $t->route?->route_name ?? 'المسار العام',
                    'status'     => 'completed',
                    'duration'   => 48,
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data'   => $data
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 12️⃣ GET /api/driver/trips/history/{tripId}
     * تفاصيل رحلة سابقة مكتملة
     */
    public function historyDetails($tripId): JsonResponse
    {
        try {
            $user = Auth::user();
            $driverId = $user->driver->id;

            $trip = Trip::where('id', $tripId)->where('driver_id', $driverId)->firstOrFail();

            $events = TripEvent::where('trip_id', $trip->id)
                ->with(['child', 'subscription.school'])
                ->get();

            $tripStops = \App\Models\Shared\TripStop::where('trip_id', $trip->id)
                ->get()
                ->keyBy('child_id');

            $subs = ActiveSubscription::where('driver_id', $driverId)
                ->where(function($q) use ($trip) {
                    $q->where('route_id', $trip->route_id);
                    if ($trip->route?->contract_id) {
                        $q->orWhere('contract_id', $trip->route->contract_id);
                    }
                })
                ->with(['child', 'school'])
                ->get()
                ->keyBy('child_id');

            $eventsByChild = $events->groupBy('child_id');

            $allChildIds = $eventsByChild->keys()
                ->merge($tripStops->keys())
                ->merge($subs->keys())
                ->filter()
                ->unique();

            $children = $allChildIds->map(function ($childId) use ($eventsByChild, $tripStops, $subs) {
                $childEvents = $eventsByChild->get($childId, collect());
                $stop = $tripStops->get($childId);
                $sub = $subs->get($childId);

                $pickupEvent = $childEvents->first(fn($e) => in_array($e->action_type, ['picked_up', 'pickup']));
                $dropoffEvent = $childEvents->first(fn($e) => in_array($e->action_type, ['dropped_off', 'dropoff']));
                $absentEvent = $childEvents->first(fn($e) => in_array($e->action_type, ['absent', 'skip']));
                $latestEvent = $childEvents->sortByDesc('scanned_at')->first();

                $childObj = $pickupEvent?->child 
                    ?? $dropoffEvent?->child 
                    ?? $latestEvent?->child 
                    ?? $sub?->child;

                $schoolName = optional($sub?->school)->name 
                    ?? optional($childObj?->school)->name
                    ?? optional($pickupEvent?->subscription?->school)->name 
                    ?? optional($dropoffEvent?->subscription?->school)->name 
                    ?? optional(\App\Models\Parent\School::find($sub?->school_id ?? $childObj?->school_id))->name
                    ?? 'المدرسة';

                $pickupAddress = $sub?->pickup_label ?? $stop?->label ?? 'الحي السكني';
                $dropoffAddress = $schoolName;

                $pickupTime = $pickupEvent?->scanned_at 
                    ? Carbon::parse($pickupEvent->scanned_at)->format('H:i') 
                    : null;

                $dropoffTime = $dropoffEvent?->scanned_at 
                    ? Carbon::parse($dropoffEvent->scanned_at)->format('H:i') 
                    : null;

                $status = 'completed';
                if ($dropoffEvent) {
                    $status = 'completed';
                } elseif ($pickupEvent) {
                    $status = 'picked_up';
                } elseif ($absentEvent) {
                    $status = 'absent';
                }

                $scannedAt = $latestEvent?->scanned_at 
                    ? Carbon::parse($latestEvent->scanned_at)->format('Y-m-d H:i:s') 
                    : null;

                return [
                    'child_id'           => (int) $childId,
                    'child_name'         => $childObj->full_name ?? $childObj->name ?? 'طفل',
                    'name'               => $childObj->full_name ?? $childObj->name ?? 'طفل',
                    'school'             => $schoolName,
                    'school_name'        => $schoolName,
                    'pickup_address'     => $pickupAddress,
                    'dropoff_address'    => $dropoffAddress,
                    'pickup_time'        => $pickupTime,
                    'dropoff_time'       => $dropoffTime,
                    'scanned_pickup_at'  => $pickupEvent?->scanned_at ? Carbon::parse($pickupEvent->scanned_at)->format('Y-m-d H:i:s') : null,
                    'scanned_dropoff_at' => $dropoffEvent?->scanned_at ? Carbon::parse($dropoffEvent->scanned_at)->format('Y-m-d H:i:s') : null,
                    'status'             => $status,
                    'pickup_status'      => $pickupEvent ? 'completed' : ($absentEvent ? 'absent' : 'pending'),
                    'dropoff_status'     => $dropoffEvent ? 'completed' : 'pending',
                    'action_type'        => $latestEvent->action_type ?? $status,
                    'scanned_at'         => $scannedAt,
                ];
            })->values();

            $routeName = $trip->route?->formatted_route_name 
                ?? RouteModel::generateGenericRouteName(null, 'both', $trip->trip_type);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'    => (int) $trip->id,
                    'trip_date'  => $trip->trip_date ? Carbon::parse($trip->trip_date)->format('Y-m-d') : Carbon::parse($trip->created_at)->format('Y-m-d'),
                    'route_name' => $routeName,
                    'status'     => 'completed',
                    'duration'   => (int) ($trip->route?->estimated_duration ?? 48),
                    'distance'   => (float) ($trip->route?->total_distance ?? 19.3),
                    'children'   => $children,
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'الرحلة غير موجودة.'], 404);
        }
    }
}