<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\DriverFilterRequest;
use App\Http\Requests\Api\Admin\DriverReviewRequest;
use App\Http\Requests\Api\Admin\ReviewProfileChangeRequest; // الجديد الخاص بفحص قرار التعديل
use App\Http\Resources\Api\Admin\AdminDriverDetailResource;
use App\Http\Resources\Api\Admin\AdminDriverListResource;
use App\Http\Resources\Api\Admin\AdminPendingChangeResource; // الجديد الخاص بتنسيق شاشة التعديلات المعلقة
use App\Services\Admin\AdminDriverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminDriverController extends Controller
{
    protected AdminDriverService $adminDriverService;

    public function __construct(AdminDriverService $adminDriverService)
    {
        $this->adminDriverService = $adminDriverService;
    }

    /**
     * 1. عرض جميع السائقين وطلبات إنشاء الحساب مع الفلترة الذكية والبحث
     */
    /**
     * 1. عرض جميع السائقين وطلبات إنشاء الحساب مع الفلترة الذكية والبحث
     */
    public function index(DriverFilterRequest $request): JsonResponse
    {
        try {
            try { $filters = $request->validated(); } catch (\Throwable $e) { $filters = $request->all(); }
            $drivers = $this->adminDriverService->getDriversList($filters ?? []);

            $drivers->load('user');

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب قائمة السائقين والطلبات بنجاح.',
                'data'    => AdminDriverListResource::collection($drivers), // 🚀 أعدنا الـ Resource المحمي
                'meta'    => [
                    'current_page' => $drivers->currentPage(),
                    'last_page'    => $drivers->lastPage(),
                    'per_page'     => $drivers->perPage(),
                    'total'        => $drivers->total(),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Index Drivers Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء جلب قائمة السائقين.'], 500);
        }
    }

    /**
     * 2. عرض تفاصيل سائق معين بالكامل لمراجعة وثائقه وبيانات مركباته واحصائياته
     */
    public function show(int $id): JsonResponse
    {
        try {
            $driver = $this->adminDriverService->getDriverDetails($id);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب تفاصيل السائق والوثائق بنجاح.',
                'data'    => new AdminDriverDetailResource($driver)
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Show Driver Details Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'عذراً، السائق المطلوب غير موجود في النظام.'], 404);
        }
    }

    /**
     * 3. اتخاذ قرار القبول والتفعيل الاحتفالي أو الرفض المسبب للسائق (الطلب الأولي)
     */
    public function review(DriverReviewRequest $request, int $id): JsonResponse
    {
        try {
            $adminId = auth()->user()->admin->id ?? 1; 

            try { $data = $request->validated(); } catch (\Throwable $e) { $data = $request->all(); }
            $driver = $this->adminDriverService->reviewDriverRequest($id, $data, $adminId);
            $statusText = $driver->status === 'Approved' ? 'قبول وتفعيل حسابه بنجاح' : 'رفض الطلب مع إرسال التوضيحات';

            return response()->json([
                'status'  => true,
                'message' => 'تمت مراجعة طلب السائق وتحديث حالته بنجاح وعمل التفعيل.',
                'data'    => [
                    'id'               => $driver->id,
                    'status'           => $driver->status,
                    'full_name'        => $driver->user->full_name ?? null,
                    'email'            => $driver->user->email ?? null,
                    'is_active'        => (bool) ($driver->user->is_active ?? false),
                    
                    // 🚀 الحل: الحماية المطلقة في حال كان التاريخ null أو نصاً غير مفرس
                    'updated_at'       => $driver->updated_at ? \Carbon\Carbon::parse($driver->updated_at)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    
                    // إرجاع آخر قرار تم اتخاذه الآن للتوثيق الفوري
                    'latest_approval'  => $driver->approvals()->latest()->first([
                        'status', 'rejection_reason', 'created_at'
                    ])
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Review Driver Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر إتمام مراجعة طلب السائق.'], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🚀 الدوال الثلاث الجديدة للتحكم في تحديثات الملفات المعلقة وإشعاراتها
    |--------------------------------------------------------------------------
    */

    /**
     * 4. عرض كافة طلبات تعديل البيانات والمركبات المعلقة للآدمن
     * GET: /api/admin/drivers/pending-changes
     */
    public function pendingChanges(): JsonResponse
    {
        try {
            $changes = $this->adminDriverService->getPendingChangesList();

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب كافة التعديلات المعلقة للسائقين بنجاح.',
                'data'    => AdminPendingChangeResource::collection($changes),
                'meta'    => [
                    'current_page' => $changes->currentPage(),
                    'last_page'    => $changes->lastPage(),
                    'per_page'     => $changes->perPage(),
                    'total'        => $changes->total(),
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Pending Changes Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'حدث خطأ أثناء جلب قائمة التعديلات.'], 500);
        }
    }

    /**
     * 5. عرض تفصيلي مقارن لطلب تعديل محدد
     * GET: /api/admin/drivers/pending-changes/{id}
     */
    public function showPendingChange(int $id): JsonResponse
    {
        try {
            $change = $this->adminDriverService->getPendingChangeDetails($id);

            return response()->json([
                'status'  => true,
                'message' => 'تم جلب تفاصيل طلب التعديل بنجاح للمقارنة الإدارية.',
                'data'    => new AdminPendingChangeResource($change)
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Show Pending Change Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * 6. معالجة قرار الموافقة والتطبيق الفوري أو الرفض المسبب لتعديلات السائق
     * POST: /api/admin/drivers/pending-changes/{id}/review
     */
    public function reviewProfileChange(ReviewProfileChangeRequest $request, int $id): JsonResponse
    {
        try {
            $adminId = auth()->user()->admin->id ?? 1;
            
            $decision = $request->input('decision');
            $rejectionReason = $request->input('rejection_reason');

            $this->adminDriverService->reviewProfileChangeRequest($id, $decision, $rejectionReason, $adminId);

            $messageText = $decision === 'Approved' 
                ? 'تمت الموافقة على التعديلات وتطبيقها على حساب المركبة والسائق فوراً بنجاح.' 
                : 'تم رفض طلب تعديل البيانات، وإرسال مبررات الرفض إلي سائق .';

            return response()->json([
                'status'  => true,
                'message' => $messageText
            ], 200);
        } catch (Exception $e) {
            Log::error("Admin Review Profile Change Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'تعذر معالجة طلب التعديل: ' . $e->getMessage()], 500);
        }
    }
}