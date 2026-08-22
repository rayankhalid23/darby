<?php

namespace App\Http\Controllers\API\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
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
    }public function activeSubscriptions(Request $request): JsonResponse
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
                
                // جلب عنوان المنزل بشكل مباشر ودقيق من علاقة الطفل أو ولي الأمر
                $childModel = optional($item->child);
                $parentModel = optional($item)->parent;

                $address = null;
                if ($childModel->address) {
                    $address = $childModel->address;
                } elseif ($parentModel && method_exists($parentModel, 'addresses')) {
                    $address = $parentModel->addresses()->first();
                }

                $homeLat = $address->lat ?? $item->pickup_lat ?? null;
                $homeLng = $address->lng ?? $item->pickup_lng ?? null;
                $homeLabel = $address->label ?? $item->pickup_label ?? 'المنزل';

                // جلب المدرسة
                $school = optional($item->school ?? $childModel->school);
                $schoolLat = $school->lat ?? $item->school_lat ?? $item->dropoff_lat ?? null;
                $schoolLng = $school->lng ?? $item->school_lng ?? $item->dropoff_lng ?? null;
                $schoolAddress = $school->address ?? $item->school_address ?? 'غير محدد';

                return [
                    'id' => $item->id,
                    'status' => $item->status ?? 'active',
                    'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                    'child' => [
                        'id' => $childModel->id,
                        'name' => $childModel->full_name ?? $childModel->name,
                        'avatar' => $childModel->photo_url,
                        'avatarInitials' => mb_substr($childModel->full_name ?? '', 0, 2),
                        'schoolName' => $school->name ?? $item->school_name ?? 'مدرسة الفلاح',
                        'schoolLocation' => [
                            'name' => $schoolAddress,
                            'lat' => $schoolLat !== null ? (float) $schoolLat : null,
                            'lng' => $schoolLng !== null ? (float) $schoolLng : null,
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
                        'pickupZoneName' => $homeLabel,
                        'schoolName' => $school->name ?? $item->school_name ?? 'مدرسة الفلاح',
                        'homeLocation' => [
                            'label' => $homeLabel,
                            'address' => $homeLabel,
                            'lat' => $homeLat !== null ? (float) $homeLat : null,
                            'lng' => $homeLng !== null ? (float) $homeLng : null,
                        ],
                        'pickupTime' => $item->pickup_time ?? '07:00 AM',
                        'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                    ],
                    'billing' => $this->formatBilling($item),
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
    public function showActive(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // جلب الاشتراك النشط مع التحقق الآمن
            $item = \App\Models\Shared\ActiveSubscription::with(['child.address', 'child.school', 'driver.user', 'contract', 'school'])
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
            $parentModel = optional($item)->parent;
            
            // جلب عنوان البيت بدقة
            $address = null;
            if ($childModel->address) {
                $address = $childModel->address;
            } elseif ($parentModel && method_exists($parentModel, 'addresses')) {
                $address = $parentModel->addresses()->first();
            }

            $homeLat   = $address->lat ?? $item->pickup_lat ?? null;
            $homeLng   = $address->lng ?? $item->pickup_lng ?? null;
            $homeLabel = $address->label ?? $item->pickup_label ?? 'المنزل';

            // جلب بيانات المدرسة وعنوانها بدقة
            $school = optional($item->school ?? $childModel->school);
            $schoolLat     = $school->lat ?? $item->school_lat ?? $item->dropoff_lat ?? null;
            $schoolLng     = $school->lng ?? $item->school_lng ?? $item->dropoff_lng ?? null;
            $schoolAddress = $school->address ?? $item->school_address ?? 'غير محدد';

            // جلب بيانات المركبة مباشرة من جدول vehicles باستخدام driver_id لتجنب مشاكل علاقات المودل
            $vehicle = null;
            if ($item->driver_id) {
                $vehicle = \DB::table('vehicles')->where('driver_id', $item->driver_id)->first();
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
                    'schoolName' => $school->name ?? $item->school_name ?? 'مدرسة الفلاح',
                    'schoolLocation' => [
                        'name' => $schoolAddress,
                        'lat' => $schoolLat !== null ? (float) $schoolLat : null,
                        'lng' => $schoolLng !== null ? (float) $schoolLng : null,
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
                    'pickupZoneName' => $homeLabel,
                    'schoolName' => $school->name ?? $item->school_name ?? 'مدرسة الفلاح',
                    'homeLocation' => [
                        'label'   => $homeLabel,
                        'address' => $homeLabel,
                        'lat'     => $homeLat !== null ? (float) $homeLat : null,
                        'lng'     => $homeLng !== null ? (float) $homeLng : null,
                    ],
                    'pickupTime' => $item->pickup_time ?? '07:00 AM',
                    'dropoffTime' => $item->dropoff_time ?? '02:00 PM',
                ],
                'billing' => $this->formatBilling($item),
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

    /**
     * إلغاء اشتراك نشط (بعد قبول السائق) من قِبل ولي الأمر
     * POST /api/parent/active-subscriptions/{id}/cancel
     */
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

    /**
     * تجهيز بلوك البيانات المالية (billing) لاشتراك نشط بقيم حقيقية محسوبة،
     * بدل القيم الثابتة الوهمية (SAR، 89، 14 يوماً...) التي كانت مكتوبة يدوياً سابقاً.
     */
    private function formatBilling(\App\Models\Shared\ActiveSubscription $item): array
    {
        $contract = $item->contract;
        $totalPrice = (float) ($contract->total_price ?? 0);

        $childPrice = null;
        if ($contract && $contract->subscription_request_id) {
            $childPrice = DB::table('request_children')
                ->where('request_id', $contract->subscription_request_id)
                ->where('child_id', $item->child_id)
                ->value('price_per_child');
        }
        $childPrice = $childPrice !== null ? (float) $childPrice : $totalPrice;

        $endsAt = $contract?->end_date;
        $remainingDays = $endsAt ? max(0, (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false)) : null;

        return [
            'subscriptionType' => $contract?->subscription_type ?? 'multi_day',
            'totalPrice'       => $totalPrice,
            'childPrice'       => round($childPrice, 2),
            'currency'         => 'د.ل',
            'startsAt'         => $contract?->start_date?->toDateString(),
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

            // 1. جلب id ولي الأمر الفعلي من جدول parents بناءً على user_id
            $parentRecord = DB::table('parents')->where('user_id', $user->id)->first();
            $parentId = $parentRecord ? $parentRecord->id : $user->id;

            // 2. بناء الاستعلام مع دعم الفلترة حسب السائق ووالد الطفل
            $query = DB::table('active_subscriptions')
                ->where(function ($q) use ($parentId, $user) {
                    $q->where('parent_id', $parentId)
                      ->orWhere('parent_id', $user->id); // حماية إضافية لمطابقة أي نوع ID
                })
                ->whereIn('status', ['active', 'pending', 'completed']);

            // 3. التكد من السائق المحدد فقط إن تم إرسال driver_id في الـ Request (من Query Params أو Body)
            if ($request->has('driver_id') && !empty($request->driver_id)) {
                $query->where('driver_id', $request->driver_id);
            }

            $hasSubscription = $query->exists();

            return response()->json([
                'success'          => true,
                'has_subscription' => $hasSubscription
            ], 200);

        } catch (\Exception $e) {
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