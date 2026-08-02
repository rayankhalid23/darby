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

    public function __construct(
        TripLifecycleService $lifecycleService,
        TripTrackingService $trackingService,
        TripStopService $stopService,
        RouteRecommendationService $recommendationService
    ) {
        $this->lifecycleService = $lifecycleService;
        $this->trackingService = $trackingService;
        $this->stopService = $stopService;
        $this->recommendationService = $recommendationService;
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
                $trip = Trip::firstOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'route_id'  => $route->id,
                        'trip_date' => $today,
                        'trip_type' => $route->route_type,
                    ],
                    [
                        'scheduled_at'         => now(),
                        'scheduled_start_time' => '07:15:00',
                        'status'               => 'Planned',
                    ]
                );

                if (strtolower($trip->status) === 'cancelled') {
                    continue;
                }

                $metrics = $this->recommendationService->calculateRouteMetrics($route);

                $statusMap = [
                    'planned'    => 'pending',
                    'inprogress' => 'started',
                    'started'    => 'started',
                    'completed'  => 'completed',
                ];

                $formattedStatus = $statusMap[strtolower($trip->status)] ?? strtolower($trip->status);

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
                ->where('route_id', $trip->route_id)
                ->where('status', '!=', 'cancelled')
                ->with(['child', 'school'])
                ->get();

            $schoolsGrouped = $subs->groupBy('school_id')->map(function ($group) {
                $first = $group->first();
                return [
                    'school_id'      => (int) $first->school_id,
                    'name'           => optional($first->school)->name ?? 'المدرسة',
                    'children_count' => $group->count(),
                ];
            })->values();

            $events = TripEvent::where('trip_id', $trip->id)->get();

            $children = $subs->map(function ($sub) use ($events) {
                $pickupEvent = $events->where('child_id', $sub->child_id)->whereIn('action_type', ['picked_up', 'skipped', 'absent'])->first();
                $dropoffEvent = $events->where('child_id', $sub->child_id)->where('action_type', 'dropped_off')->first();

                $pickupStatus = 'pending';
                if ($pickupEvent) {
                    if ($pickupEvent->action_type === 'picked_up') $pickupStatus = 'picked_up';
                    elseif ($pickupEvent->action_type === 'skipped') $pickupStatus = 'skipped';
                    elseif ($pickupEvent->action_type === 'absent') $pickupStatus = 'absent';
                }

                $dropoffStatus = $dropoffEvent ? 'dropped_off' : 'pending';

                return [
                    'trip_child_id'   => (int) $sub->id,
                    'child_id'        => (int) $sub->child_id,
                    'name'            => $sub->child->full_name ?? $sub->child->name ?? 'طفل',
                    'school'          => optional($sub->school)->name ?? 'المدرسة',
                    'pickup_address'  => $sub->pickup_label ?? 'الحي السكني',
                    'dropoff_address' => optional($sub->school)->name ?? 'المدرسة',
                    'pickup_status'   => $pickupStatus,
                    'dropoff_status'  => $dropoffStatus,
                ];
            })->values();

            $statusMap = [
                'planned'    => 'pending',
                'inprogress' => 'started',
                'started'    => 'started',
                'completed'  => 'completed',
            ];

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'               => (int) $trip->id,
                    'trip_type'             => $trip->trip_type,
                    'route_name'            => $route?->route_name ?? 'المسار العام',
                    'status'                => $statusMap[strtolower($trip->status)] ?? strtolower($trip->status),
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

            $targetTripId = $tripId ?? $request->trip_id;

            if ($targetTripId) {
                $trip = Trip::where('id', $targetTripId)->where('driver_id', $driverId)->firstOrFail();
                $trip->status = 'started';
                $trip->actual_start_time = now();
                $trip->started_at = now();
                $trip->save();
            } else {
                $tripType = $request->trip_type ?? 'Morning';
                $trip = $this->lifecycleService->startTrip($driverId, $tripType);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'تم بدء الرحلة.',
                'data'    => [
                    'trip_id'    => (int) $trip->id,
                    'status'     => 'started',
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

            $total = $subs->count();
            $completed = $events->pluck('child_id')->unique()->count();
            $remaining = max(0, $total - $completed);

            $currentSub = $subs->first(function ($s) use ($events) {
                return !$events->where('child_id', $s->child_id)->exists();
            }) ?? $subs->first();

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
                    'pickup_status'   => 'pending',
                    'dropoff_status'  => 'pending',
                ];
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_status'   => 'started',
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

            $subId = $tripChildId ?? $request->trip_child_id;
            $action = strtolower($request->action ?? $request->route()->getActionMethod());

            if (str_contains($action, 'pickup')) $action = 'pickup';
            elseif (str_contains($action, 'dropoff')) $action = 'dropoff';
            elseif (str_contains($action, 'absent')) $action = 'absent';
            elseif (str_contains($action, 'skip')) $action = 'skip';

            $sub = ActiveSubscription::where('id', $subId)->firstOrFail();
            $childId = $sub->child_id;

            if ($action === 'pickup') {
                TripEvent::create([
                    'trip_id'             => $tripId,
                    'child_id'            => $childId,
                    'subscription_id'     => $sub->id,
                    'action_type'         => 'picked_up',
                    'verification_method' => $request->verification_method ?? 'manual',
                    'scanned_at'          => now(),
                    'scanned_lat'         => $request->latitude,
                    'scanned_lng'         => $request->longitude,
                ]);

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
                    'trip_id'             => $tripId,
                    'child_id'            => $childId,
                    'subscription_id'     => $sub->id,
                    'action_type'         => 'dropped_off',
                    'verification_method' => $request->verification_method ?? 'manual',
                    'scanned_at'          => now(),
                    'scanned_lat'         => $request->latitude,
                    'scanned_lng'         => $request->longitude,
                ]);

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

            } elseif ($action === 'absent' || $action === 'skip') {
                TripEvent::create([
                    'trip_id'             => $tripId,
                    'child_id'            => $childId,
                    'subscription_id'     => $sub->id,
                    'action_type'         => $action === 'absent' ? 'absent' : 'skipped',
                    'verification_method' => 'manual',
                    'scanned_at'          => now(),
                ]);

                // 🔔 إرسال إشعار لحظي FCM لولي الأمر
                try {
                    $parentUser = $sub->parent?->user ?? \App\Models\User::find($sub->parent_id);
                    if ($parentUser) {
                        $childName = $sub->child->full_name ?? $sub->child->name ?? 'طفلك';
                        app(\App\Services\Notification\FcmService::class)->sendPushNotification($parentUser, [
                            'title'   => '⚠️ غياب / تجاوز محطة',
                            'message' => "تم تسجيل غياب/تجاوز محطة {$childName} في رحلة اليوم.",
                            'payload' => [
                                'type'     => 'child_absent',
                                'trip_id'  => (string) $tripId,
                                'child_id' => (string) $childId,
                                'screen'   => 'TRIP_DETAILS',
                            ]
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("FCM Notification error on absent: " . $e->getMessage());
                }

                return response()->json(['status' => 'success', 'message' => 'تم تسجيل الغياب والتجاوز.'], 200);
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
        $request->merge(['action' => 'pickup', 'verification_method' => 'qr']);
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
            $trip->status = 'completed';
            $trip->completed_at = now();
            $trip->save();

            $events = TripEvent::where('trip_id', $trip->id)->get();
            $totalSubs = ActiveSubscription::where('route_id', $trip->route_id)->count();

            $pickedUp = $events->where('action_type', 'picked_up')->count();
            $droppedOff = $events->where('action_type', 'dropped_off')->count();
            $absent = $events->whereIn('action_type', ['absent', 'skipped'])->count();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إنهاء الرحلة.',
                'summary' => [
                    'children'    => $totalSubs > 0 ? $totalSubs : max(1, $pickedUp + $absent),
                    'picked_up'   => $pickedUp,
                    'dropped_off' => $droppedOff,
                    'absent'      => $absent,
                    'duration'    => 48,
                    'distance'    => 19.3,
                ]
            ], 200);

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

            $children = $events->map(function ($e) {
                return [
                    'child_id'     => (int) $e->child_id,
                    'child_name'   => $e->child->full_name ?? $e->child->name ?? 'طفل',
                    'school'       => optional($e->subscription?->school)->name ?? 'المدرسة',
                    'action_type'  => $e->action_type,
                    'scanned_at'   => $e->scanned_at ? Carbon::parse($e->scanned_at)->format('Y-m-d H:i:s') : null,
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'    => (int) $trip->id,
                    'trip_date'  => $trip->trip_date ? Carbon::parse($trip->trip_date)->format('Y-m-d') : Carbon::parse($trip->created_at)->format('Y-m-d'),
                    'route_name' => $trip->route?->route_name ?? 'المسار العام',
                    'status'     => 'completed',
                    'duration'   => 48,
                    'distance'   => 19.3,
                    'children'   => $children,
                ]
            ], 200);

        } catch (Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'الرحلة غير موجودة.'], 404);
        }
    }
}