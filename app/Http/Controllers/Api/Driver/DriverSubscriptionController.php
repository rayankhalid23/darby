<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Shared\SubscriptionRequestResource;
use App\Services\Shared\SubscriptionRequestService;
use App\Models\Shared\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Driver\Driver;
use Exception;

class DriverSubscriptionController extends Controller
{
    protected SubscriptionRequestService $subscriptionService;

    public function __construct(SubscriptionRequestService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * عرض قائمة طلبات الاشتراك الأولية الخاصة بالسائق مع فلتر اختياري
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $requests = $this->subscriptionService->getDriverSubscriptionRequests(
                auth()->id(),
                $request->query('filter')
            );

            return response()->json([
                'success' => true,
                'count'   => $requests->count(),
                'data'    => SubscriptionRequestResource::collection($requests)
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() == 403 ? 403 : 500);
        }
    }
    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|string|in:current_active,pending_start,completed,cancelled'
            ]);

            $driverId = auth()->id(); // أو جلب الـ driver_id الخاص بالسائق حسب الهيكلة لديك
            $filter = $request->query('filter');

            $activeSubscriptions = $this->subscriptionService->getDriverActiveSubscriptions($driverId, $filter);

            $formattedData = $activeSubscriptions->map(function ($item) {
                $parentUser = optional($item->parent);

                return [
                    'id' => $item->id,
                    'status' => $item->status ?? 'active',
                    'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                    'child' => [
                        'id' => optional($item->child)->id,
                        'name' => optional($item->child)->full_name ?? optional($item->child)->name,
                        'avatar' => optional($item->child)->photo_url,
                        'avatarInitials' => mb_substr(optional($item->child)->full_name ?? optional($item->child)->name ?? '', 0, 2),
                        'schoolName' => optional($item->school)->name ?? 'مدرسة الفلاح',
                    ],
                    'driver' => [
                        'id' => optional($item->driver)->id,
                        'name' => optional($parentUser)->name,
                        'phone' => optional($parentUser)->phone_number ?? optional($parentUser)->phone,
                        'rating' => 5.0,
                        'vehicle' => [
                            'model' => 'تويوتا هايس',
                            'color' => 'أبيض',
                            'plateNumber' => '12345 طرابلس',
                        ]
                    ],
                    'schedule' => [
                        'shift' => optional($item->driver)->shift ? (string) optional($item->driver)->shift->value : '1',
                        'shiftLabel' => optional($item->driver)->shift ? optional($item->driver)->shift->name : 'MORNING',
                        'pickupZoneName' => optional($item)->pickup_label ?? 'حي الأندلس',
                        'schoolName' => optional($item->school)->name ?? 'مدرسة الفلاح',
                    ],
                    'billing' => [
                        'subscriptionType' => optional($item->contract)->subscription_type ?? 'monthly',
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
                    'cancelReason' => optional($item)->rejection_reason,
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
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'status'           => 'required|in:accepted,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        // 2. جلب ملف السائق المرتبط بالحساب الحالي
        $user = auth()->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات السائق غير موجودة.'
            ], 403);
        }

        // 3. البحث عن طلب الاشتراك
        $subscriptionRequest = SubscriptionRequest::find($id);

        if (!$subscriptionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الاشتراك غير موجود.'
            ], 404);
        }

        // 4. التحقق من ملكية الطلب (أنه موجه لهذا السائق تحديداً)
        $driverIdMatches = ($subscriptionRequest->driver_id == $driver->id) || ($subscriptionRequest->driver_id == $user->id);
        if (!$driverIdMatches) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب غير موجه لك ولا يمكنك التحكم به.'
            ], 403);
        }

        // 5. تنفيذ عملية التحديث ومعالجة النتيجة
        try {
            $updatedRequest = $this->subscriptionService->updateStatus(
                $subscriptionRequest,
                $request->status,
                $request->input('rejection_reason')
            );

            return response()->json([
                'success' => true,
                'message' => $request->status === 'accepted' ? 'تم قبول الطلب وتفعيل الاشتراك بنجاح.' : 'تم رفض الطلب بنجاح.',
                'data'    => new SubscriptionRequestResource($updatedRequest)
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * عرض تفاصيل اشتراك نشط وفعلّي معين خاص بالسائق
     * GET /api/driver/active-subscriptions/{id}
     */
    public function activeSubscriptionDetails($id): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
        }

        try {
            $activeSub = $this->subscriptionService->getDriverActiveSubscriptionDetails($id, $driver->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك النشط بنجاح.',
                'data'    => [
                    'id' => $activeSub->id,
                    'status' => $activeSub->status ?? 'active',
                    'statusLabel' => $activeSub->status == 'active' ? 'نشط' : 'غير نشط',
                    'child' => [
                        'id' => optional($activeSub->child)->id,
                        'name' => optional($activeSub->child)->full_name ?? optional($activeSub->child)->name,
                        'gender' => optional($activeSub->child)->gender,
                        'avatar' => optional($activeSub->child)->photo_url,
                        'avatarInitials' => mb_substr(optional($activeSub->child)->full_name ?? optional($activeSub->child)->name ?? '', 0, 2),
                        'schoolName' => optional($activeSub->child->school)->name ?? optional($activeSub->school)->name ?? 'مدرسة الفلاح',
                        'homeAddress' => optional($activeSub->child->address)->label ?? optional($activeSub->child->address)->address ?? 'العنوان غير محدد',
                        'latitude' => (float) ($activeSub->pickup_lat ?? optional($activeSub->child->address)->latitude ?? 0),
                        'longitude' => (float) ($activeSub->pickup_lng ?? optional($activeSub->child->address)->longitude ?? 0),
                        'notes' => optional($activeSub->child)->notes,
                    ],
                    'parent' => [
                        'id' => optional($activeSub->parent)->id,
                        'name' => optional($activeSub->parent)->name,
                        'phone' => optional($activeSub->parent)->phone_number ?? optional($activeSub->parent)->phone,
                    ],
                    'schedule' => [
                        'pickupZoneName' => $activeSub->pickup_label ?? 'حي الأندلس',
                        'pickupTime' => $activeSub->pickup_time,
                        'dropoffTime' => $activeSub->dropoff_time,
                        'schoolName' => optional($activeSub->school)->name ?? 'مدرسة الفلاح',
                        'schoolAddress' => optional($activeSub->school)->address ?? null,
                    ],
                    'billing' => [
                        'contractNumber' => optional($activeSub->contract)->contract_number,
                        'subscriptionType' => optional($activeSub->contract)->subscription_type ?? 'monthly',
                        'totalPrice' => (float) (optional($activeSub->contract)->total_price ?? 89),
                        'currency' => 'SAR',
                        'startsAt' => optional($activeSub->contract)->start_date ? optional($activeSub->contract)->start_date->toDateString() : null,
                        'endsAt' => optional($activeSub->contract)->end_date ? optional($activeSub->contract)->end_date->toDateString() : null,
                    ],
                    'createdAt' => $activeSub->created_at ? $activeSub->created_at->toIso8601String() : null,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * عرض تفاصيل طلب اشتراك معين
     */
    public function show($id): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
        }

        $subscriptionRequest = SubscriptionRequest::where('id', $id)
            ->where('driver_id', $driver->id)
            ->with([
                'parent.user',
                'driver.user',
                'school',
                'children.school',
                'children.address',
                'contract',
            ])
            ->first();

        if (!$subscriptionRequest) {
            return response()->json([
                'success' => false, 
                'message' => 'الطلب غير موجود أو أنه غير مخصص لهذا السائق.',
                'debug_info' => [
                    'requested_id' => $id,
                    'driver_id' => $driver->id
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new SubscriptionRequestResource($subscriptionRequest)
        ], 200);
    }

    /**
     * عرض تفاصيل الرحلة الخاصة بطلب الاشتراك (مواقع الإنزال، الاستلام، وأوقات الرحلة للسائق)
     * GET /api/driver/requests/{id}/trip-details
     */
    public function tripDetails($id): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
        }

        $subscriptionRequest = SubscriptionRequest::where('id', $id)
            ->where('driver_id', $driver->id)
            ->with([
                'parent.user',
                'school',
                'children.school',
                'children.address',
            ])
            ->first();

        if (!$subscriptionRequest) {
            return response()->json([
                'success' => false, 
                'message' => 'الطلب غير موجود أو غير مخصص لك.'
            ], 404);
        }

        // تجهيز بيانات الرحلة المخصصة لعرضها في شاشة خريطة السائق
        $tripDetails = [
            'request_id'   => $subscriptionRequest->id,
            'status'       => $subscriptionRequest->status,
            'trip_type'    => $subscriptionRequest->direction ?? 'two_way',
            'pickup_time'  => $subscriptionRequest->pickup_time ?? null,
            'dropoff_time' => $subscriptionRequest->dropoff_time ?? null,
            'parent' => [
                'name'  => $subscriptionRequest->parent->user->name ?? 'غير محدد',
                'phone' => $subscriptionRequest->parent->user->phone_number ?? $subscriptionRequest->parent->user->phone ?? null,
            ],
            'school' => [
                'id'        => $subscriptionRequest->school->id ?? null,
                'name'      => $subscriptionRequest->school->name ?? 'غير محدد',
                'address'   => $subscriptionRequest->school->address ?? null,
                'latitude'  => (float) ($subscriptionRequest->school->latitude ?? 0),
                'longitude' => (float) ($subscriptionRequest->school->longitude ?? 0),
            ],
            'children' => $subscriptionRequest->children->map(function ($child) {
                return [
                    'id'        => $child->id,
                    'name'      => $child->name ?? 'طفل',
                    'gender'    => $child->gender ?? null,
                    'home_address' => $child->address->label ?? $child->address->address ?? 'العنوان غير محدد',
                    'latitude'  => (float) ($child->address->latitude ?? $child->latitude ?? 0),
                    'longitude' => (float) ($child->address->longitude ?? $child->longitude ?? 0),
                    'notes'     => $child->notes ?? null,
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data'    => $tripDetails
        ], 200);
    }

    public function getDriverChatList(Request $request): JsonResponse
    {
        try {
            $chats = $this->subscriptionService->getDriverChats($request->user()->id);

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