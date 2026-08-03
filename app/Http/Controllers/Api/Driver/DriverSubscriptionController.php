<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Shared\SubscriptionRequestResource;
use App\Services\Shared\SubscriptionRequestService;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Driver\Driver;
use Carbon\Carbon;
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

    /**
     * عرض قائمة الاشتراكات النشطة للسائق
     */
    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|string|in:current_active,pending_start,completed,cancelled'
            ]);

            $driverId = auth()->id();
            $filter   = $request->query('filter');

            $activeSubscriptions = $this->subscriptionService->getDriverActiveSubscriptions($driverId, $filter);

            $formattedData = $activeSubscriptions->map(function ($item) {
                $parentUser = optional($item->parent);

                return [
                    'id'          => $item->id,
                    'status'      => $item->status ?? 'active',
                    'statusLabel' => $item->status == 'active' ? 'نشط' : 'غير نشط',
                    'child'       => [
                        'id'             => optional($item->child)->id,
                        'name'           => optional($item->child)->full_name ?? optional($item->child)->name,
                        'avatar'         => optional($item->child)->photo_url,
                        'avatarInitials' => mb_substr(optional($item->child)->full_name ?? optional($item->child)->name ?? '', 0, 2),
                        'schoolName'     => optional($item->school)->name ?? 'مدرسة الفلاح',
                    ],
                    'driver'   => [
                        'id'    => optional($item->driver)->id,
                        'name'  => optional($parentUser)->name,
                        'phone' => optional($parentUser)->phone_number ?? optional($parentUser)->phone,
                        'rating'  => 5.0,
                        'vehicle' => [
                            'model'       => 'تويوتا هايس',
                            'color'       => 'أبيض',
                            'plateNumber' => '12345 طرابلس',
                        ]
                    ],
                    'schedule' => [
                        'shift'           => optional($item->driver)->shift ? (string) optional($item->driver)->shift->value : '1',
                        'shiftLabel'      => optional($item->driver)->shift ? optional($item->driver)->shift->name : 'MORNING',
                        'pickupZoneName'  => optional($item)->pickup_label ?? 'حي الأندلس',
                        'schoolName'      => optional($item->school)->name ?? 'مدرسة الفلاح',
                    ],
                    'billing' => [
                        'subscriptionType' => optional($item->contract)->subscription_type ?? 'monthly',
                        'totalPrice'       => (float) (optional($item->contract)->total_price ?? $item->total_price ?? 89),
                        'childPrice'       => (float) (optional($item->contract)->child_price ?? 89),
                        'currency'         => 'SAR',
                        'startsAt'         => optional($item->contract)->start_date ? optional($item->contract)->start_date->toDateString() : null,
                        'endsAt'           => optional($item->contract)->end_date ? optional($item->contract)->end_date->toDateString() : null,
                        'remainingDays'    => 14,
                        'autoRenew'        => true,
                        'paymentMethod'    => 'card',
                    ],
                    'requestId'    => $item->id,
                    'cancelReason' => optional($item)->rejection_reason,
                    'cancelledAt'  => null,
                    'createdAt'    => $item->created_at ? optional($item->created_at)->toIso8601String() : null,
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

    /**
     * قبول أو رفض طلب اشتراك من قبل السائق
     * PUT /api/driver/requests/{id}/status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status'           => 'required|in:accepted,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        $user   = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول.'
            ], 401);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات السائق غير موجودة.'
            ], 403);
        }

        $subscriptionRequest = SubscriptionRequest::find($id);

        if (!$subscriptionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'طلب الاشتراك غير موجود.'
            ], 404);
        }

        $driverIdMatches = ($subscriptionRequest->driver_id == $driver->id) || ($subscriptionRequest->driver_id == $user->id);
        if (!$driverIdMatches) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب غير موجه لك ولا يمكنك التحكم به.'
            ], 403);
        }

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
     * عرض تفاصيل اشتراك نشط معين خاص بالسائق بالشكل الكامل
     * GET /api/driver/active-subscriptions/{id}
     */
    public function activeSubscriptionDetails($id): JsonResponse
    {
        $driver = auth()->user()->driver;

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
        }

        try {
            $s = ActiveSubscription::where('id', $id)
                ->where('driver_id', $driver->id)
                ->with([
                    'contract.subscriptionRequest',
                    'child.school',
                    'child.address',
                    'parent',
                    'driver.vehicles',
                    'school',
                ])
                ->firstOrFail();

            $child    = $s->child;
            $contract = $s->contract;
            $parent   = $s->parent;
            $school   = optional($child?->school ?? $s->school);
            $address  = optional($child?->address);

            // جلب مركبة السائق (الأولى النشطة أو أي مركبة)
            $vehicle = optional($driver->vehicles)->where('status', 'Active')->first()
                    ?? optional($driver->vehicles)->first();

            // حساب عمر الطفل
            $age = null;
            if ($child && $child->birth_date) {
                $age = Carbon::parse($child->birth_date)->age;
            }

            $parentName  = optional($parent)->full_name ?? optional($parent)->name;
            $parentPhone = optional($parent)->phone_number ?? optional($parent)->phone;
            $parentImage = optional($parent)->avatar_url ?? optional($parent)->photo_url;

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك النشط بنجاح.',
                'data'    => [

                    // 1. معلومات الاشتراك
                    'subscription_info' => [
                        'subscription_id' => $s->id,
                        'request_id'      => optional($contract)->subscription_request_id,
                        'contract_number' => optional($contract)->contract_number,
                        'status'          => $s->status,
                        'created_at'      => $s->created_at?->toIso8601String(),
                    ],

                    // 2. ولي الأمر
                    'parent' => [
                        'name'  => $parentName,
                        'image' => $parentImage,
                        'phone' => $parentPhone,
                    ],

                    // 3. الطفل
                    'child' => [
                        'name'              => optional($child)->full_name,
                        'image'             => optional($child)->photo_url,
                        'age'               => $age,
                        'gender'            => optional($child)->gender,
                        'educational_stage' => optional($child)->grade,
                        'medical_notes'     => optional($child)->medical_notes,
                    ],

                    // 4. المواقع
                    'locations' => [
                        'home' => [
                            'address_name' => $s->pickup_label ?? $address->label ?? $address->address,
                            'latitude'     => $s->pickup_lat  ?? $address->latitude,
                            'longitude'    => $s->pickup_lng  ?? $address->longitude,
                        ],
                        'school' => [
                            'name'      => $school->name,
                            'address'   => $school->address,
                            'latitude'  => $school->latitude  ?? $s->dropoff_lat,
                            'longitude' => $school->longitude ?? $s->dropoff_lng,
                        ],
                    ],

                    // 5. تفاصيل الاشتراك
                    'subscription_details' => [
                        'subscription_type'        => optional($contract)->subscription_type,
                        'direction'                => optional($contract)->direction,
                        'period'                   => optional($contract)->timing,
                        'pickup_time'              => $s->pickup_time  ?? optional($contract)->pickup_time,
                        'dropoff_time'             => $s->dropoff_time ?? optional($contract)->dropoff_time,
                        'start_date'               => optional($contract)->start_date ? Carbon::parse($contract->start_date)->toDateString() : null,
                        'end_date'                 => optional($contract)->end_date   ? Carbon::parse($contract->end_date)->toDateString()   : null,
                        'subscription_days'        => optional($contract)->days_count,
                        'child_subscription_price' => (float) (optional($contract)->total_price / max(1, optional(optional($contract)->subscriptionRequest)->children_count ?? 1)),
                        'total_contract_value'     => (float) optional($contract)->total_price,
                    ],

                    // 6. المركبة
                    'vehicle' => $vehicle ? [
                        'manufacturer' => $vehicle->brand,
                        'model'        => $vehicle->model,
                        'color'        => $vehicle->color,
                        'plate_number' => $vehicle->plate_number,
                    ] : null,

                    // 7. العقد
                    'contract' => [
                        'status'    => optional($contract)->status,
                        'signed_at' => optional($contract)->signed_at ? Carbon::parse($contract->signed_at)->toIso8601String() : null,
                        'pdf_url'   => optional($contract)->pdf_path ? url('storage/' . $contract->pdf_path) : null,
                    ],

                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الاشتراك النشط غير موجود أو ليس لديك صلاحية للوصول إليه.'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض تفاصيل طلب اشتراك معين
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح بالوصول.'], 401);
        }

        $driver = $user->driver;
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
                'success'    => false,
                'message'    => 'الطلب غير موجود أو أنه غير مخصص لهذا السائق.',
                'debug_info' => [
                    'requested_id' => $id,
                    'driver_id'    => $driver->id
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
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح بالوصول.'], 401);
        }

        $driver = $user->driver;
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

        $tripDetails = [
            'request_id'   => $subscriptionRequest->id,
            'status'       => $subscriptionRequest->status,
            'trip_type'    => $subscriptionRequest->direction ?? 'two_way',
            'pickup_time'  => $subscriptionRequest->pickup_time  ?? null,
            'dropoff_time' => $subscriptionRequest->dropoff_time ?? null,
            'parent'       => [
                'name'  => $subscriptionRequest->parent?->user?->full_name ?? $subscriptionRequest->parent?->user?->name ?? 'غير محدد',
                'phone' => $subscriptionRequest->parent?->user?->phone_number ?? $subscriptionRequest->parent?->user?->phone ?? null,
            ],
            'school' => [
                'id'        => $subscriptionRequest->school?->id        ?? null,
                'name'      => $subscriptionRequest->school?->name      ?? 'غير محدد',
                'address'   => $subscriptionRequest->school?->address   ?? null,
                'latitude'  => (float) ($subscriptionRequest->school?->lat  ?? $subscriptionRequest->school?->latitude  ?? 0),
                'longitude' => (float) ($subscriptionRequest->school?->lng ?? $subscriptionRequest->school?->longitude ?? 0),
            ],
            'children' => $subscriptionRequest->children->map(function ($child) {
                return [
                    'id'           => $child->id,
                    'name'         => $child->full_name ?? $child->name ?? 'طفل',
                    'gender'       => $child->gender ?? null,
                    'home_address' => $child->pivot?->home_label ?? $child->address?->label ?? $child->address?->address ?? 'العنوان غير محدد',
                    'latitude'     => (float) ($child->pivot?->home_lat  ?? $child->address?->lat  ?? $child->latitude  ?? 0),
                    'longitude'    => (float) ($child->pivot?->home_lng ?? $child->address?->lng ?? $child->longitude ?? 0),
                    'notes'        => $child->pivot?->child_notes ?? $child->notes ?? null,
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data'    => $tripDetails
        ], 200);
    }

    /**
     * جلب قائمة محادثات السائق
     */
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