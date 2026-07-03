<?php

namespace App\Http\Controllers\API\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shared\StoreSubscriptionRequest;
use App\Services\Shared\SubscriptionRequestService;
use App\Models\Shared\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log; // لا تنسَ إضافة هذا الاستيراد

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
            // استدعاء الخدمة بمتغيرين فقط كما صممناها سابقاً
            $result = $this->subscriptionService->createRequest(
                $request->validated(), 
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلب الاشتراك بنجاح.',
                'data'    => $result
            ], 201);

        } catch (\Exception $e) {
            // تسجيل الخطأ في ملف السجلات (Log) وليس إظهاره للمستخدم (لأمان النظام)
            Log::error('Subscription Request Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'عذراً، حدث خطأ أثناء معالجة الطلب، يرجى المحاولة لاحقاً.',
            ], 500);
        }
    }

    /**
     * جلب كافة طلبات الاشتراكات الخاصة بولي الأمر الحالي
     */
    public function index(): JsonResponse
    {
        // استخدام العلاقة مباشرة (Relationship) وتفادي الاستعلامات اليدوية الطويلة
        $parent = auth()->user()->parent;
        
        if (!$parent) {
            return response()->json(['success' => true, 'data' => []], 200);
        }

        $requests = SubscriptionRequest::where('parent_id', $parent->id)
            ->with(['driver.user', 'school', 'children']) 
            ->latest() // بدلاً من orderBy('id', 'desc') - أكثر احترافية
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $requests
        ], 200);
    }

    /**
     * عرض جميع الطلبات "المعلقة" فقط لولي الأمر الحالي
     */
    public function indexPending(): JsonResponse
{
    // 1. استرجاع المستخدم الحالي
    $user = auth()->user();

    // 2. التحقق من وجود علاقة "parent" قبل محاولة الوصول لأي خاصية
    if (!$user->parent) {
        return response()->json([
            'success' => false,
            'message' => 'عذراً، لا يوجد حساب "ولي أمر" مرتبط بهذا المستخدم.'
        ], 404);
    }

    // 3. الآن بأمان يمكننا الوصول للـ id
    $parentId = $user->parent->id;

    $requests = SubscriptionRequest::where('parent_id', $parentId)
        ->where('status', 'pending') // فلترة الطلبات المعلقة فقط
        ->with(['driver.user', 'school', 'children']) // تحميل البيانات المرتبطة
        ->orderBy('id', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'data'    => $requests
    ], 200);
}

    /**
     * عرض تفاصيل طلب معين (مع التأكد من ملكية ولي الأمر له)
     */
    /**
     * عرض تفاصيل طلب اشتراك معين لولي الأمر
     */
    public function show($id): JsonResponse
    {
        // 1. استرجاع المستخدم الحالي
        $user = auth()->user();

        // 2. التحقق من وجود علاقة "parent" قبل محاولة الوصول لأي خاصية
        if (!$user->parent) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، لا يوجد حساب "ولي أمر" مرتبط بهذا المستخدم.'
            ], 404);
        }

        $parentId = $user->parent->id;

        try {
            // 3. جلب الطلب المحدد مع التأكد الصارم أنه يخص ولي الأمر الحالي (حماية أمنية)
            $request = SubscriptionRequest::where('id', $id)
                ->where('parent_id', $parentId)
                ->with(['driver.user', 'school', 'children']) // تحميل البيانات المرتبطة كاملة
                ->firstOrFail(); // إذا لم يجد الطلب أو لم يكن لولي الأمر سيرمي 404 تلقائياً

            return response()->json([
                'success' => true,
                'data'    => $request
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا الطلب غير موجود أو لا تملك صلاحية لعرضه.'
            ], 404);
        }
    }
}