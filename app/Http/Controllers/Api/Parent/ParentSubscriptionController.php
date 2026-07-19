<?php

namespace App\Http\Controllers\API\Parent;

use App\Http\Controllers\Controller;
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

    /**
     * إنشاء طلب اشتراك جديد
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->createRequest(
                $request->validated(), 
                $request->user()->id // استخدام $request->user()->id أكثر استقراراً في الـ APIs
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($result) // تغليف المخرج بالريسورس
            ], 201);

        } catch (Exception $e) {
            Log::error('Subscription Request Error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'عذراً، حدث خطأ أثناء معالجة الطلب، يرجى المحاولة لاحقاً.',
                // 'error' => $e->getMessage() // يمكن تفعيله أثناء التطوير فقط
            ], 500);
        }
    }

    /**
     * جلب كافة طلبات الاشتراكات (مع دعم الفلترة الذكية)
     * دمجنا index و indexPending في دالة واحدة احترافية
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // فلترة اختيارية للحالة (مثال: ?status=pending)
            $request->validate([
                'status' => 'nullable|string|in:pending,accepted,rejected,cancelled,completed'
            ]);

            $userId = $request->user()->id;
            $status = $request->query('status');

            // الاعتماد على الـ Service لجلب البيانات
            $subscriptions = $this->subscriptionService->getParentSubscriptions($userId, $status);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الاشتراكات بنجاح.',
                'data'    => SubscriptionRequestResource::collection($subscriptions)
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); // 400 Bad Request عادة إذا لم يكن المستخدم ولي أمر
        }
    }

    /**
     * عرض تفاصيل طلب اشتراك معين لولي الأمر
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // السيرفس تتكفل بالبحث والتأكد من الملكية وجلب العلاقات
            $subscription = $this->subscriptionService->getSubscriptionDetails($id, $userId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($subscription)
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() // رسالة الخطأ تأتي من السيرفس (مثلاً: غير موجود أو لا تملك الصلاحية)
            ], 404);
        }
    }

   

    /**
     * إلغاء الاشتراك
     */
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
            return response()->json([
                'success' => false,
                'message' => 'طلب الاشتراك غير موجود أو لا تملك صلاحية إلغائه.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}