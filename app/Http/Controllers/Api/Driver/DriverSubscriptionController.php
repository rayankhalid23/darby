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
     * عرض جميع الطلبات الواردة للسائق الحالي
     */
    /**
     * عرض قائمة طلبات الاشتراك الخاصة بالسائق
     */
   /**
 * عرض قائمة طلبات الاشتراك الخاصة بالسائق
 */
public function index(): JsonResponse
{
    // 1. جلب بيانات السائق بناءً على الـ user_id للمستخدم المسجل حالياً
    $driver = Driver::where('user_id', auth()->id())->first();

    // 2. التحقق من وجود السائق
    if (!$driver) {
        \Log::warning('Driver profile not found for user ID: ' . auth()->id());
        return response()->json([
            'success' => false,
            'message' => 'لم يتم العثور على ملف السائق الخاص بك.'
        ], 403);
    }

    try {
        // 3. جلب الطلبات المرتبطة بهذا السائق مع علاقاتها
        // قمت بتقليل استهلاك الذاكرة بجلب الحقول الضرورية فقط إذا كانت القائمة طويلة جداً
        $requests = SubscriptionRequest::where('driver_id', $driver->id)
            ->with([
                'parent.user:id,full_name,phone', // جلب حقول محددة فقط لزيادة الأداء
                'school:id,name',
                'children'
            ])
            ->orderBy('id', 'desc')
            ->get();

        // 4. إرجاع النتيجة
        return response()->json([
            'success' => true,
            'count'   => $requests->count(), // مفيد جداً للموبايل
            'data'    => $requests
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error fetching driver requests: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء جلب البيانات، يرجى المحاولة لاحقاً.'
        ], 500);
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