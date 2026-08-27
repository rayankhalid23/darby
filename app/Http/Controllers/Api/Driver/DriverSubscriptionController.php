<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Driver\DriverSubscriptionResource; 
use App\Services\Shared\SubscriptionRequestService;
use App\Services\Trip\RouteFeasibilityService;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Http\Resources\Api\Driver\SubscriptionRequestDetailsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Driver\Driver;
use Carbon\Carbon;
use Exception;

class DriverSubscriptionController extends Controller
{
    protected SubscriptionRequestService $subscriptionService;
    protected RouteFeasibilityService $feasibilityService;

    public function __construct(
        SubscriptionRequestService $subscriptionService,
        RouteFeasibilityService $feasibilityService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->feasibilityService = $feasibilityService;
    }

    /**
     * جلب ملف السائق المرتبط بالمستخدم الحاضر
     */
    private function getAuthenticatedDriver(Request $request): ?Driver
    {
        return $request->user()->driver ?? Driver::where('user_id', $request->user()->id)->first();
    }

    /**
     * استجابة موحدة في حال عدم وجود ملف سائق
     */
    private function driverNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'بيانات السائق غير موجودة.',
            'error'   => 'دراااااااااافيرلم يتم العثور عل     ى ملف السائق الخاص بك.'
        ], 403);
    }

    public function index(Request $request)
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        $requests = SubscriptionRequest::query()
            ->with(['parent.user', 'children.school', 'children.address'])
            ->where('driver_id', $driver->id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->get('per_page', 15));

        return DriverSubscriptionResource::collection($requests)->additional([
            'status'  => true,
            'message' => 'تم جلب طلبات الاشتراك بنجاح',
        ]);
    }
    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|string|in:current_active,pending_start,completed,cancelled'
            ]);
    
            $user = $request->user();
    
            // 1. التحقق من وجود مستخدم مسجل الدخول بالتوكن
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.',
                    'error'   => 'غير مصرح بالوصول، التوكن غير صحيح أو منتهي الصلاحية.'
                ], 401);
            }
    
            // 2. جلب ملف السائق
            $driver = $this->getAuthenticatedDriver($request);
    
            // 3. إرجاع تفاصيل تشخيصية إذا لم يتم العثور على ملف السائق
            if (!$driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.',
                    'error'   => 'لم يتم العثور على ملف السائق الخاص بك.',
                    'debug_details' => [
                        'user_id'    => $user->id,
                        'user_name'  => $user->full_name ?? $user->name,
                        'user_phone' => $user->phone_number ?? $user->phone,
                        'reason'     => "لا يوجد أي سجل في جدول (drivers) يربط بـ user_id = {$user->id}",
                        'sql_check'  => "SELECT * FROM drivers WHERE user_id = {$user->id};"
                    ]
                ], 404);
            }
    
            // 4. جلب الاشتراكات النشطة باستخدام id المستخدم
            $activeSubscriptions = $this->subscriptionService->getDriverActiveSubscriptions(
                $user->id, 
                $request->query('filter')
            );
    
            // 5. تنسيق وإرجاع البيانات مباشرة عبر الـ Resource المطلوب
            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح.',
                'data'    => SubscriptionRequestDetailsResource::collection($activeSubscriptions)
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
   

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status'           => 'required|in:accepted,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        $subscriptionRequest = SubscriptionRequest::find($id);

        if (!$subscriptionRequest) {
            return response()->json(['success' => false, 'message' => 'طلب الاشتراك غير موجود.'], 404);
        }

        if ($subscriptionRequest->driver_id != $driver->id) {
            return response()->json(['success' => false, 'message' => 'هذا الطلب غير موجه لك ولا يمكنك التحكم به.'], 403);
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
                'data'    => new SubscriptionRequestDetailsResource($updatedRequest)
            ], 200);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ في النظام: ' . $e->getMessage()], 500);
        }
    }

    public function activeSubscriptionDetails(Request $request, $id): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        try {
            $s = ActiveSubscription::where('id', $id)
                ->where('driver_id', $driver->id)
                ->with([
                    'subscriptionRequest',
                    'child.school',
                    'child.address',
                    'parent',
                    'driver.vehicles',
                    'school',
                ])
                ->firstOrFail();

            $child   = $s->child;
            $subReq  = $s->subscriptionRequest;
            $parent  = $s->parent;
            $school  = optional($child?->school ?? $s->school);
            $address = optional($child?->address);

            $vehicle = optional($driver->vehicles)->firstWhere('status', 'Active') ?? optional($driver->vehicles)->first();
            $age     = ($child && $child->birth_date) ? Carbon::parse($child->birth_date)->age : null;

            $childrenCount = $subReq?->children_count ?? 1;
            $totalPrice    = (float) ($subReq?->total_price ?? 0);
            $childPrice    = $childrenCount > 0 ? round($totalPrice / $childrenCount, 2) : $totalPrice;

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك النشط بنجاح.',
                'data'    => [
                    'subscription_info' => [
                        'subscription_id' => $s->id,
                        'request_id'      => $subReq?->id,
                        'status'          => $s->status,
                        'created_at'      => $s->created_at?->toIso8601String(),
                    ],
                    'parent' => [
                        'name'  => optional($parent)->full_name ?? optional($parent)->name,
                        'image' => optional($parent)->avatar_url ?? optional($parent)->photo_url,
                        'phone' => optional($parent)->phone_number ?? optional($parent)->phone,
                    ],
                    'child' => [
                        'name'              => optional($child)->full_name,
                        'image'             => optional($child)->photo_url,
                        'age'               => $age,
                        'gender'            => optional($child)->gender,
                        'educational_stage' => optional($child)->grade,
                        'medical_notes'     => optional($child)->medical_notes,
                    ],
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
                    'subscription_details' => [
                        'subscription_type'        => $subReq?->subscription_type,
                        'direction'                => $subReq?->direction,
                        'period'                   => $subReq?->timing,
                        'pickup_time'              => $s->pickup_time  ?? $subReq?->pickup_time,
                        'dropoff_time'             => $s->dropoff_time ?? $subReq?->dropoff_time,
                        'start_date'               => $subReq?->start_date ? Carbon::parse($subReq->start_date)->toDateString() : null,
                        'end_date'                 => $subReq?->end_date   ? Carbon::parse($subReq->end_date)->toDateString()   : null,
                        'subscription_days'        => $subReq?->days_count,
                        'child_subscription_price' => $childPrice,
                        'total_contract_value'     => $totalPrice,
                    ],
                    'vehicle' => $vehicle ? [
                        'manufacturer' => $vehicle->brand,
                        'model'        => $vehicle->model,
                        'color'        => $vehicle->color,
                        'plate_number' => $vehicle->plate_number,
                    ] : null,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'الاشتراك النشط غير موجود أو ليس لديك صلاحية للوصول إليه.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        $subscriptionRequest = SubscriptionRequest::query()
            ->with(['parent.user', 'children.school', 'children.address'])
            ->where('driver_id', $driver->id)
            ->findOrFail($id);

        return (new SubscriptionRequestDetailsResource($subscriptionRequest))
            ->additional([
                'status'  => true,
                'message' => 'تم جلب تفاصيل طلب الاشتراك بنجاح',
            ]);
    }

    public function tripDetails(Request $request, $id): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        $subscriptionRequest = SubscriptionRequest::where('id', $id)
            ->where('driver_id', $driver->id)
            ->with(['parent.user', 'school', 'children.school', 'children.address'])
            ->first();

        if (!$subscriptionRequest) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود أو غير مخصص لك.'], 404);
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
            'children' => $subscriptionRequest->children->map(fn($child) => [
                'id'           => $child->id,
                'name'         => $child->full_name ?? $child->name ?? 'طفل',
                'gender'       => $child->gender ?? null,
                'home_address' => $child->pivot?->home_label ?? $child->address?->label ?? $child->address?->address ?? 'العنوان غير محدد',
                'latitude'     => (float) ($child->pivot?->home_lat  ?? $child->address?->lat  ?? $child->latitude  ?? 0),
                'longitude'    => (float) ($child->pivot?->home_lng ?? $child->address?->lng ?? $child->longitude ?? 0),
                'notes'        => $child->pivot?->child_notes ?? $child->notes ?? null,
            ])
        ];

        return response()->json(['success' => true, 'data' => $tripDetails], 200);
    }

    public function feasibilityCheck(Request $request, $id): JsonResponse
    {
        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        $subscriptionRequest = SubscriptionRequest::where('id', $id)
            ->where('driver_id', $driver->id)
            ->with('children')
            ->first();

        if (!$subscriptionRequest) {
            return response()->json(['success' => false, 'message' => 'الطلب غير موجود أو غير مخصص لهذا السائق.'], 404);
        }

        try {
            $result = $this->feasibilityService->checkForRequest($subscriptionRequest);
            return response()->json(['success' => true, 'data' => $result], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء فحص إمكانية إضافة الطلب: ' . $e->getMessage()], 500);
        }
    }

    public function cancelActiveSubscription(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $driver = $this->getAuthenticatedDriver($request);
        if (!$driver) {
            return $this->driverNotFoundResponse();
        }

        try {
            $activeSub = $this->subscriptionService->cancelActiveSubscriptionByDriver(
                $id,
                $driver->id,
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الاشتراك النشط بنجاح وتم إشعار ولي الأمر.',
                'data'    => ['id' => $activeSub->id, 'status' => $activeSub->status],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'الاشتراك النشط غير موجود أو لا تملك صلاحية الوصول إليه.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function getDriverChatList(Request $request): JsonResponse
    {
        try {
            $chats = $this->subscriptionService->getDriverChats($request->user()->id);
            return response()->json(['success' => true, 'message' => 'تم جلب قائمة المحادثات بنجاح.', 'data' => $chats], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 400);
        }
    }
}