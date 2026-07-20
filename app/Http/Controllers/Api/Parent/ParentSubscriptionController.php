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
                $request->user()->id 
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب الاشتراك بنجاح.',
                'data'    => new SubscriptionRequestResource($result)
            ], 201);

        } catch (Exception $e) {
            // تسجيل الخطأ بالتفصيل
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

    /**
     * جلب كافة طلبات الاشتراكات 
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'nullable|string|in:pending,cancelled,rejected'
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
            // تسجيل الخطأ بالتفصيل
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

    /**
     * عرض تفاصيل طلب اشتراك معين لولي الأمر
     */
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
            // لا نحتاج لتسجيل ModelNotFound كخطأ حرج (Error) بل كتحذير (Warning) أو تجاهله
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
                'message' => 'حدث خطأ أثناء الإلغاء.'
            ], 422);
        }
    }

    /**
     * جلب كافة الاشتراكات الموافَق عليها 
     */
    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|string|in:current_active,pending_start,completed,cancelled'
            ]);

            $userId = $request->user()->id;
            $filter = $request->query('filter');

            $activeSubscriptions = $this->subscriptionService->getParentActiveSubscriptions($userId, $filter);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب البيانات بنجاح.',
                'data'    => \App\Http\Resources\Api\Parent\SubscriptionResource::collection($activeSubscriptions)
            ], 200);

        } catch (Exception $e) {
            Log::error('Error in ParentSubscriptionController@activeSubscriptions', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'user_id' => $request->user()->id ?? null,
                'filter'  => $filter ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الاشتراكات النشطة.'
            ], 400);
        }
    }

    /**
     * التحقق من وجود اشتراك لولي الأمر مع سائق معين
     */
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
}