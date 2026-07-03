<?php

namespace App\Http\Controllers\API\Driver;

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
    public function index(): JsonResponse
    {
        // 1. جلب السائق بناءً على الـ user_id للمستخدم المسجل حالياً
        $driver = Driver::where('user_id', auth()->id())->first();

        \Log::info('Debug Driver:', [
            'auth_id' => auth()->id(),
            'found_driver' => $driver ? $driver->toArray() : 'null',
            'driver_id_to_save' => $driver ? $driver->id : 'none'
        ]);

        // 2. التحقق من وجود السائق
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على ملف السائق الخاص بك. تأكد أنك مسجل كسائق.'
            ], 403);
        }

        // 3. جلب الطلبات المرتبطة بهذا السائق
        $requests = SubscriptionRequest::where('driver_id', $driver->id)
            ->with(['parent.user', 'school', 'children']) // جلب البيانات المرتبطة
            ->orderBy('id', 'desc')
            ->get();

        // 4. إرجاع النتيجة
        return response()->json([
            'success' => true,
            'data'    => $requests
        ], 200);
    }
  

    /**
     * تحديث حالة الطلب من قبل السائق
     */
    public function updateStatus(Request $request, $id, ContractService $contractService): JsonResponse
{
    $request->validate([
        'status' => 'required|in:accepted,rejected', 
    ]);

    // 1. جلب السائق أولاً والتأكد من وجوده
    $user = auth()->user();
    $driver = \App\Models\Driver\Driver::where('user_id', $user->id)->first();

    if (!$driver) {
        return response()->json(['success' => false, 'message' => 'بيانات السائق غير موجودة.'], 403);
    }

    $req = \App\Models\Shared\SubscriptionRequest::findOrFail($id);

    // 2. تصحيح: تنفيذ التحديث فقط إذا كان السائق موجوداً
    DB::transaction(function () use ($req, $request, $contractService, $driver) {
        
        $updateData = [
            'status' => $request->status,
        ];

        // فقط عند القبول نربط الطلب بالسائق
        if ($request->status === 'accepted') {
            $updateData['driver_id'] = $driver->id; // نستخدم الـ ID الفعلي من جدول السائقين
            
            // استدعاء خدمة العقود
            $contract = $contractService->generateContract($req);

            // إنشاء الاشتراكات (كما في السابق)
            foreach ($req->children as $child) {
                \App\Models\Shared\ActiveSubscription::create([
                    'contract_id'   => $contract->id,
                    'child_id'      => $child->id,
                    'driver_id'     => $driver->id, // تأكد من استخدام ID السائق هنا أيضاً
                    'parent_id'     => $req->parent_id,
                    'pickup_lat'    => $child->pivot->pickup_lat ?? null,
                    'pickup_lng'    => $child->pivot->pickup_lng ?? null,
                    'dropoff_lat'   => $child->pivot->dropoff_lat ?? null,
                    'dropoff_lng' => $child->pivot->dropoff_lng ?? null,
                    'status'        => 'active',
                ]);
            }
        }

        // 3. التحديث النهائي
        $req->update($updateData);
    });

    return response()->json([
        'success' => true,
        'message' => 'تمت العملية بنجاح.'
    ]);
}

    /**
     * عرض تفاصيل طلب اشتراك معين
     */
    public function show($id): JsonResponse
    {
        $driver = auth()->user()->driver;

        // جلب الطلب مع التأكد أنه يخص السائق الحالي فقط (حماية أمنية)
        $subscriptionRequest = SubscriptionRequest::where('id', $id)
            ->where('driver_id', $driver->id)
            ->with(['parent.user', 'school', 'children']) // نفس العلاقات التي استخدمتها في index
            ->firstOrFail(); // إذا لم يجد الطلب، سيرمي 404 تلقائياً

        return response()->json([
            'success' => true,
            'data'    => $subscriptionRequest
        ], 200);
    }
}