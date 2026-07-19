<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\UpdateSubscriptionStatusRequest;
use App\Services\Shared\SubscriptionRequestService;
use App\Models\Shared\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Driver\Driver;
use Illuminate\Http\Request;
use App\Services\Shared\ContractService;
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
     * GET /api/driver/requests?filter=pending|cancelled|rejected
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
                'data'    => $requests
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() == 403 ? 403 : 500);
        }
    }

    /**
     * عرض قائمة الاشتراكات الفعلية والمثبتة المعتمدة للسائق مع فلتر اختياري
     * GET /api/driver/active-subscriptions?filter=current_active|pending_start|completed|cancelled
     */
    public function activeSubscriptions(Request $request): JsonResponse
    {
        try {
            $subscriptions = $this->subscriptionService->getDriverActiveSubscriptions(
                auth()->id(),
                $request->query('filter')
            );

            return response()->json([
                'success' => true,
                'count'   => $subscriptions->count(),
                'data'    => $subscriptions
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getCode() == 403 ? 403 : 500);
        }
    }
  

    /**
     * تحديث حالة الطلب من قبل السائق
     */
    public function updateStatus(Request $request, $id): JsonResponse
{
    $request->validate([
        'status' => 'required|in:accepted,rejected',
    ]);

    $user = auth()->user();
    $driver = \App\Models\Driver\Driver::where('user_id', $user->id)->first();

    if (!$driver) {
        return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
    }

    $req = \App\Models\Shared\SubscriptionRequest::findOrFail($id);

    if ($req->driver_id !== $driver->id) {
        return response()->json(['success' => false, 'message' => 'هذا الطلب غير موجه لك.'], 403);
    }

    try {
        $this->subscriptionService->updateStatus(
            $req,
            $request->status,
            $request->input('rejection_reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'تمت العملية بنجاح.'
        ]);
    } catch (\Exception $e) {
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
    $driver = auth()->user()->driver;

    if (!$driver) {
        return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
    }

    $subscriptionRequest = SubscriptionRequest::where('id', $id)
        ->where('driver_id', $driver->id)
        ->with(['parent.user', 'school', 'children'])
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
        'data'    => $subscriptionRequest
    ], 200);
}
}