<?php

namespace App\Http\Controllers\API\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Parent\SubscriptionRequestDetailsResource;
use App\Http\Requests\Api\Shared\StoreSubscriptionRequest;
use App\Services\Shared\SubscriptionRequestService;
use App\Http\Resources\Api\Shared\SubscriptionRequestResource;

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
                'message' => 'عذراً، حدث خطأ أثناء معالجة الطلب، يرجى المحاولة لاحقاً.',
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'nullable|string|in:pending,cancelled,rejected,accepted'
            ]);

            $userId = $request->user()->id;
            $status = $request->query('status'); 

            $subscriptions = $this->subscriptionService->getParentSubscriptions($userId, $status);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات الاشتراكات بنجاح.',
                'data'    => SubscriptionRequestResource::collection($subscriptions)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@index', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'query'   => $request->query()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات.'
            ], 400);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $subscription = $this->subscriptionService->getSubscriptionDetails($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($subscription)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@show', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'sub_id'  => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() 
            ], 404);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->cancelSubscriptionByParent($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء طلب الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($subscription)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Parent tried to cancel non-existing subscription', [
                'user_id' => auth()->id(),
                'sub_id'  => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'طلب الاشتراك غير موجود أو لا تملك صلاحية إلغائه.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error in ParentSubscriptionController@cancel', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => auth()->id(),
                'sub_id'  => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?? 'حدث خطأ أثناء الإلغاء.'
            ], 422);
        }
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
                
                // جلب عنوان البيت بشكل آمن إن وجد
                $parentModel = optional($item)->parent;
                $address = null;
                if ($parentModel && method_exists($parentModel, 'addresses')) {
                    $address = $parentModel->addresses()->where('is_default', true)->first() 
                               ?? $parentModel->addresses()->first();
                }

                $school = optional($item->school);

                return [
                    'id' => $item->id,
                    'status' => $item->status ?? 'active',
                    'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                    'child' => [
                        'id' => optional($item->child)->id,
                        'name' => optional($item->child)->full_name,
                        'avatar' => optional($item->child)->photo_url,
                        'avatarInitials' => mb_substr(optional($item->child)->full_name ?? '', 0, 2),
                        'schoolName' => $school->name ?? 'مدرسة الفلاح',
                        'schoolLocation' => [
                            'lat' => isset($school->lat) ? (float) $school->lat : null,
                            'lng' => isset($school->lng) ? (float) $school->lng : null,
                            'address' => $school->address ?? null,
                        ]
                    ],
                    'driver' => [
                        'id' => optional($item->driver)->id,
                        'name' => optional($driverUser)->full_name,
                        'phone' => optional($driverUser)->phone_number,
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
                        'pickupZoneName' => optional($item)->pickup_label ?? 'حي الأندلس',
                        'schoolName' => $school->name ?? 'مدرسة الفلاح',
                        'homeLocation' => [
                            'lat' => isset($address->lat) ? (float) $address->lat : null,
                            'lng' => isset($address->lng) ? (float) $address->lng : null,
                            'label' => $address->label ?? 'المنزل',
                        ],
                        'pickupTime' => $item->pickup_time ?? '07:00 AM',
                        'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                    ],
                    'billing' => [
                        'subscriptionType' => optional($item->driver)->subscription_type ?? 'monthly',
                        'totalPrice' => (float) (optional($item->contract)->total_price ?? $item->total_price ?? 89),
                        'childPrice' => (float) (optional($item->contract)->child_price ?? 89),
                        'currency' => 'SAR',
                        'startsAt' => optional($item->contract)->start_date ? optional($item->contract)->start_date->toDateString() : null,
                        'endsAt' => optional($item->contract)->end_date ? optional($item->contract)->end_date->toDateString() : null,
                        'remainingDays' => 14,
                        'autoRenew' => true,
                        'paymentMethod' => 'card',
                    ],
                    'requestId' => $item->id,
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

    public function checkSubscription(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'driver_id' => 'required|integer|exists:drivers,id',
            ]);

            $hasSubscription = $this->subscriptionService->parentHasSubscriptionWithDriver(
                $request->user()->id,
                $request->integer('driver_id')
            );

            return response()->json([
                'success'          => true,
                'has_subscription' => $hasSubscription,
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@checkSubscription', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من الاشتراك.'
            ], 400);
        }
    }

    public function showActive(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // جلب الاشتراك النشط مع التحقق الآمن
            $item = \App\Models\Shared\ActiveSubscription::with(['child', 'driver.user', 'contract', 'school'])
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
            
            // جلب عنوان البيت بطريقة آمنة
            $parentModel = optional($item)->parent;
            $address = null;
            if ($parentModel && method_exists($parentModel, 'addresses')) {
                $address = $parentModel->addresses()->where('is_default', true)->first() 
                           ?? $parentModel->addresses()->first();
            }

            $school = optional($item->school);

            $formattedData = [
                'id' => $item->id,
                'status' => $item->status ?? 'active',
                'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                'child' => [
                    'id' => optional($item->child)->id,
                    'name' => optional($item->child)->full_name,
                    'avatar' => optional($item->child)->photo_url,
                    'avatarInitials' => mb_substr(optional($item->child)->full_name ?? '', 0, 2),
                    'schoolName' => $school->name ?? 'مدرسة الفلاح',
                    'schoolLocation' => [
                        'lat' => isset($school->lat) ? (float) $school->lat : null,
                        'lng' => isset($school->lng) ? (float) $school->lng : null,
                        
                    ]
                ],
                'driver' => [
                    'id' => optional($item->driver)->id,
                    'name' => optional($driverUser)->full_name,
                    'phone' => optional($driverUser)->phone_number,
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
                    'pickupZoneName' => optional($item)->pickup_label ?? 'حي الأندلس',
                    'schoolName' => $school->name ?? 'مدرسة الفلاح',
                    'homeLocation' => [
                        'lat' => isset($address->lat) ? (float) $address->lat : null,
                        'lng' => isset($address->lng) ? (float) $address->lng : null,
                        'label' => $address->label ?? 'المنزل',
                    ],
                    'pickupTime' => $item->pickup_time ?? '07:00 AM',
                    'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                ],
                'billing' => [
                    'subscriptionType' => optional($item->driver)->subscription_type ?? 'monthly',
                    'totalPrice' => (float) (optional($item->contract)->total_price ?? $item->total_price ?? 89),
                    'childPrice' => (float) (optional($item->contract)->child_price ?? 89),
                    'currency' => 'SAR',
                    'startsAt' => optional($item->contract)->start_date ? optional($item->contract)->start_date->toDateString() : null,
                    'endsAt' => optional($item->contract)->end_date ? optional($item->contract)->end_date->toDateString() : null,
                    'remainingDays' => 14,
                    'autoRenew' => true,
                    'paymentMethod' => 'card',
                ],
                'requestId' => $item->id,
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
    public function showRequest(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // جلب الطلب مباشرة عبر الـ user_id أو الـ parent_id المتوفر للمستخدم الحالي
            $subscriptionRequest = \App\Models\Shared\SubscriptionRequest::with([
                'driver.user',
                'children.school',
                'children.address'
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
    
}