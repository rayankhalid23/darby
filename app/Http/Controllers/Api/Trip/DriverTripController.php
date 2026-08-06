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

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'trip_id'               => (int) $trip->id,
                    'trip_type'             => $trip->trip_type,
                    'route_name'            => $route?->route_name ?? 'المسار العام',
                    'status'                => strtolower($trip->status),
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
                    'trip_status'   => 'in_progress',
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