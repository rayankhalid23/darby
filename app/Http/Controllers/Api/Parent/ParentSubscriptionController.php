<?php

namespace App\Http\Controllers\API\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\Shared\StoreSubscriptionRequest;
use App\Services\Shared\SubscriptionRequestService;
use App\Http\Resources\Api\Parent\SubscriptionRequestDetailsResource;
use App\Http\Resources\Api\Shared\SubscriptionRequestResource;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class ParentSubscriptionController extends Controller
{
    protected SubscriptionRequestService $subscriptionService;

    public function __construct(SubscriptionRequestService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->createRequest(
                $request->validated(), 
                $request->user()->id 
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($result)
            ], 201);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@store', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'request' => $request->all(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'عذراً، حدث خطأ أثناء معالجة الطلب، يرجى المحاولة لاحقاً.',
            ], 500);
        }
    }
    public function index(Request $request)
    {
        $user = $request->user();
        
        // جلب الـ parent_id الخاص بولي الأمر المرتبط بهذا المستخدم إن وجد
        $parentId = DB::table('parents')->where('user_id', $user->id)->value('id') ?? $user->id;

        $requests = SubscriptionRequest::query()
            ->with([
                'driver.user',
                'children.school',
                'children.address' // ✅ التصحيح هنا بناءً على علاقات الموديل الفعلية
            ])
            ->where('parent_id', $parentId) // ✅ استعلام مباشر وأسرع بدون تعقيد
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->latest()
            ->paginate($request->get('per_page', 15));

        return SubscriptionRequestDetailsResource::collection($requests)
            ->additional([
                'status'  => true,
                'message' => 'تم جلب طلبات الاشتراك بنجاح',
            ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $parentId = DB::table('parents')->where('user_id', $user->id)->value('id') ?? $user->id;

        $subscriptionRequest = SubscriptionRequest::query()
            ->with([
                'driver.user',
                'children.school',
                'children.address' // ✅ التصحيح هنا أيضاً
            ])
            ->where('parent_id', $parentId)
            ->findOrFail($id);

        return (new SubscriptionRequestDetailsResource($subscriptionRequest))
            ->additional([
                'status'  => true,
                'message' => 'تم جلب تفاصيل طلب الاشتراك بنجاح',
            ]);
    }

    public function cancel($id, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $subRequest = SubscriptionRequest::where('id', $id)
            ->where(function($q) use ($userId) {
                $q->where('parent_id', $userId)
                  ->orWhereHas('parent', function($query) use ($userId) {
                      $query->where('user_id', $userId);
                  });
            })
            ->first();

        if (!$subRequest) {
            return response()->json(['message' => 'الطلب غير موجود.'], 404);
        }

        if (strtoupper($subRequest->status) !== 'PENDING') {
            return response()->json(['message' => 'لا يمكن إلغاء الطلب في حالته الحالية.'], 400);
        }

        $subRequest->update(['status' => 'cancelled']);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم إلغاء طلب الاشتراك بنجاح.'
        ]);
    }

    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|string|in:active,pending,completed,cancelled',
            ]);

            $userId = $request->user()->id;
            $filter = $request->query('filter');

            $activeSubscriptions = $this->subscriptionService->getParentActiveSubscriptions($userId, $filter);

            $formattedData = $activeSubscriptions->map(function ($item) {
                $driverUser = optional($item->driver)->user;
                $childModel = optional($item->child);

                $pickupAddr = $item->pickupAddress;
                $dropoffAddr = $item->dropoffAddress;

                $pickupLat   = $pickupAddr?->latitude ?? $pickupAddr?->lat ?? null;
                $pickupLng   = $pickupAddr?->longitude ?? $pickupAddr?->lng ?? null;
                $pickupLabel = $pickupAddr?->address_line ?? $pickupAddr?->label ?? 'موقع الأخذ';

                $dropoffLat   = $dropoffAddr?->latitude ?? $dropoffAddr?->lat ?? null;
                $dropoffLng   = $dropoffAddr?->longitude ?? $dropoffAddr?->lng ?? null;
                $dropoffLabel = $dropoffAddr?->address_line ?? $dropoffAddr?->label ?? 'موقع الوضع';

                return [
                    'id' => $item->id,
                    'status' => $item->status ?? 'active',
                    'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                    'child' => [
                        'id' => $childModel->id,
                        'name' => $childModel->full_name ?? $childModel->name,
                        'avatar' => $childModel->photo_url,
                        'avatarInitials' => mb_substr($childModel->full_name ?? '', 0, 2),
                        'schoolName' => $dropoffLabel,
                        'schoolLocation' => [
                            'name' => $dropoffLabel,
                            'lat' => $dropoffLat !== null ? (float) $dropoffLat : null,
                            'lng' => $dropoffLng !== null ? (float) $dropoffLng : null,
                        ]
                    ],
                    'driver' => [
                        'id' => optional($item->driver)->id,
                        'name' => optional($driverUser)->full_name ?? optional($driverUser)->name,
                        'phone' => optional($driverUser)->phone_number ?? optional($driverUser)->phone,
                        'rating' => (float) (optional($item->driver)->rating_avg ?? 5.0),
                        'vehicle' => [
                            'model' => 'تويوتا هايس',
                            'color' => 'أبيض',
                            'plateNumber' => '12345 طرابلس',
                        ]
                    ],
                    'schedule' => [
                        'shift' => optional($item->driver)->shift ? (string) optional($item->driver)->shift->value : '1',
                        'shiftLabel' => optional($item->driver)->shift ? optional($item->driver)->shift->name : 'صباحي',
                        'pickupZoneName' => $pickupLabel,
                        'schoolName' => $dropoffLabel,
                        'homeLocation' => [
                            'label' => $pickupLabel,
                            'address' => $pickupLabel,
                            'lat' => $pickupLat !== null ? (float) $pickupLat : null,
                            'lng' => $pickupLng !== null ? (float) $pickupLng : null,
                        ],
                        'pickupTime' => $item->pickup_time ?? '07:00 AM',
                        'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                    ],
                    'billing' => $this->formatBilling($item),
                    'requestId' => $item->subscription_request_id ?? $item->id,
                    'cancelReason' => null,
                    'cancelledAt' => null,
                    'createdAt' => $item->created_at ? optional($item->created_at)->toIso8601String() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح.',
                'data'    => $formattedData
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@activeSubscriptions', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'filter'  => $request->query('filter')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.'
            ], 400);
        }
    }

    public function showActive(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $item = ActiveSubscription::with([
                'child', 
                'driver.user', 
                'subscriptionRequest', 
                'pickupAddress', 
                'dropoffAddress'
            ])
            ->where('parent_id', $userId)
            ->where('id', $id)
            ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'الاشتراك النشط غير موجود أو لا تملك صلاحية الوصول إليه.'
                ], 404);
            }

            $driverUser = optional($item->driver)->user;
            $childModel = optional($item->child);
            
            $pickupAddr = $item->pickupAddress;
            $dropoffAddr = $item->dropoffAddress;

            $pickupLat   = $pickupAddr?->latitude ?? $pickupAddr?->lat ?? null;
            $pickupLng   = $pickupAddr?->longitude ?? $pickupAddr?->lng ?? null;
            $pickupLabel = $pickupAddr?->address_line ?? $pickupAddr?->label ?? 'موقع الأخذ';

            $dropoffLat   = $dropoffAddr?->latitude ?? $dropoffAddr?->lat ?? null;
            $dropoffLng   = $dropoffAddr?->longitude ?? $dropoffAddr?->lng ?? null;
            $dropoffLabel = $dropoffAddr?->address_line ?? $dropoffAddr?->label ?? 'موقع الوضع';

            $vehicle = null;
            if ($item->driver_id) {
                $vehicle = DB::table('vehicles')->where('driver_id', $item->driver_id)->first();
            }

            $formattedData = [
                'id' => $item->id,
                'status' => $item->status ?? 'active',
                'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                'child' => [
                    'id' => $childModel->id,
                    'name' => $childModel->full_name ?? $childModel->name,
                    'avatar' => $childModel->photo_url,
                    'avatarInitials' => mb_substr($childModel->full_name ?? '', 0, 2),
                    'schoolName' => $dropoffLabel,
                    'schoolLocation' => [
                        'name' => $dropoffLabel,
                        'lat' => $dropoffLat !== null ? (float) $dropoffLat : null,
                        'lng' => $dropoffLng !== null ? (float) $dropoffLng : null,
                    ]
                ],
                'driver' => [
                    'id' => optional($item->driver)->id,
                    'name' => optional($driverUser)->full_name ?? optional($driverUser)->name,
                    'phone' => optional($driverUser)->phone_number ?? optional($driverUser)->phone,
                    'rating' => (float) (optional($item->driver)->rating_avg ?? 5.0),
                    'vehicle' => [
                        'model' => $vehicle->model ?? $vehicle->name ?? 'غير محدد',
                        'color' => $vehicle->color ?? 'غير محدد',
                        'plateNumber' => $vehicle->plate_number ?? $vehicle->plate ?? 'غير محدد',
                    ],
                ],
                'schedule' => [
                    'shift' => optional($item->driver)->shift ? (string) optional($item->driver)->shift->value : '1',
                    'shiftLabel' => optional($item->driver)->shift ? optional($item->driver)->shift->name : 'صباحي',
                    'pickupZoneName' => $pickupLabel,
                    'schoolName' => $dropoffLabel,
                    'homeLocation' => [
                        'label'   => $pickupLabel,
                        'address' => $pickupLabel,
                        'lat'     => $pickupLat !== null ? (float) $pickupLat : null,
                        'lng'     => $pickupLng !== null ? (float) $pickupLng : null,
                    ],
                    'pickupTime' => $item->pickup_time ?? '07:00 AM',
                    'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                ],
                'billing' => $this->formatBilling($item),
                'requestId' => $item->subscription_request_id ?? $item->id,
                'cancelReason' => null,
                'cancelledAt' => null,
                'createdAt' => $item->created_at ? optional($item->created_at)->toIso8601String() : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك بنجاح.',
                'data'    => $formattedData
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@showActive', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'sub_id'  => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الاشتراك.'
            ], 500);
        }
    }

    public function cancelActiveSubscription(Request $request, $id): JsonResponse
    {
        try {
            $updated = $this->subscriptionService->cancelActiveSubscriptionByParent(
                (int) $id,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الاشتراك بنجاح وتم إشعار السائق.',
                'data'    => ['id' => $updated->id, 'status' => $updated->status],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الاشتراك النشط غير موجود أو لا تملك صلاحية إلغائه.',
            ], 404);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@cancelActiveSubscription', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null,
                'sub_id'  => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function formatBilling(ActiveSubscription $item): array
    {
        $subReq = $item->subscriptionRequest;
        $totalPrice = (float) ($subReq?->total_price ?? 0);

        $pivotData = null;
        if ($subReq) {
            $pivotData = DB::table('request_children')
                ->where('request_id', $subReq->id)
                ->where('child_id', $item->child_id)
                ->first();
        }

        $childPrice = $pivotData?->price_per_child !== null ? (float) $pivotData->price_per_child : $totalPrice;
        $startDate  = $pivotData?->start_date ?? $item->start_date;
        $endDate    = $pivotData?->end_date ?? $item->end_date;
        $subType    = $pivotData?->subscription_type ?? 'multi_day';

        $endsAt = $endDate ? \Carbon\Carbon::parse($endDate) : null;
        $remainingDays = $endsAt ? max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)) : null;

        return [
            'subscriptionType' => $subType,
            'totalPrice'       => $totalPrice,
            'childPrice'       => round($childPrice, 2),
            'currency'         => 'د.ل',
            'startsAt'         => $startDate ? \Carbon\Carbon::parse($startDate)->toDateString() : null,
            'endsAt'           => $endsAt?->toDateString(),
            'remainingDays'    => $remainingDays,
            'autoRenew'        => false,
            'paymentMethod'    => 'wallet',
        ];
    }

    public function checkSubscription(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $parentRecord = DB::table('parents')->where('user_id', $user->id)->first();
            $parentId = $parentRecord ? $parentRecord->id : $user->id;

            $query = DB::table('active_subscriptions')
                ->where(function ($q) use ($parentId, $user) {
                    $q->where('parent_id', $parentId)
                      ->orWhere('parent_id', $user->id);
                })
                ->whereIn('status', ['active', 'pending', 'completed']);

            if ($request->has('driver_id') && !empty($request->driver_id)) {
                $query->where('driver_id', $request->driver_id);
            }

            $hasSubscription = $query->exists();

            return response()->json([
                'success'          => true,
                'has_subscription' => $hasSubscription
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@checkSubscription: ' . $e->getMessage());

            return response()->json([
                'success'          => false,
                'has_subscription' => false,
                'message'          => 'حدث خطأ أثناء التحقق من الاشتراك.'
            ], 500);
        }
    }

    public function showRequest(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $subscriptionRequest = SubscriptionRequest::with([
                'driver.user',
                'children.school',
                'children.pickupAddress',
                'children.dropoffAddress'
            ])
            ->where(function($query) use ($userId) {
                $query->where('parent_id', $userId)
                      ->orWhereHas('parent', function($q) use ($userId) {
                          $q->where('user_id', $userId);
                      });
            })
            ->where('id', $id)
            ->first();

            if (!$subscriptionRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'طلب الاشتراك غير موجود أو لا تملك صلاحية الوصول إليه.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الطلب بنجاح.',
                'data'    => new SubscriptionRequestDetailsResource($subscriptionRequest)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@showRequest', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'request_id' => $id,
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تفاصيل الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getParentChatList(Request $request): JsonResponse
    {
        try {
            $chats = $this->subscriptionService->getParentChats($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة المحادثات بنجاح.',
                'data'    => $chats
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 400);
        }
    }
}