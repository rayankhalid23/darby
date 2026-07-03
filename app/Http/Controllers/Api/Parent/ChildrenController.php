<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\StoreChildRequest;
use App\Http\Requests\Api\Parent\UpdateChildRequest;
use App\Models\Parent\Child;
use App\Services\Parent\ChildService;
use App\Http\Resources\Api\Parent\ChildResource;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Api\Parent\SubscriptionResource;
use Illuminate\Support\Facades\Response;

class ChildrenController extends Controller
{
    protected ChildService $childService;

    public function __construct(ChildService $childService)
    {
        $this->childService = $childService;
    }

    public function index(): JsonResponse
{
    // نستخدم ID المستخدم مباشرة لأن الصورة أظهرت أن parent_id في جدول الأطفال يساوي User ID
    $userId = auth()->id(); 

    $children = Child::where('parent_id', $userId)
        ->with(['school', 'address', 'logistics']) 
        ->get();

    // إذا كنت لا تزال تحصل على مصفوفة فارغة، فالسبب هو عدم وجود بيانات تطابق هذا الـ ID
    // أو أن العلاقات (Relationships) في الموديل لم تُعرف بشكل صحيح.
    return response()->json([
        'success' => true,
        'data'    => ChildResource::collection($children)
    ], 200);
}

    public function store(StoreChildRequest $request): JsonResponse
    {
        $child = $this->childService->createChild($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة بيانات الطفل بنجاح.',
            'data'    => new ChildResource($child->load('logistics'))
        ], 201);
    }

    public function show($id): JsonResponse
    {
        // نستخدم الـ ID الخاص بالمستخدم الحالي (8) لأن جدول الأطفال يستخدم الـ User ID كـ parent_id
        $userId = auth()->id();
    
        // نقوم بالبحث عن الطفل الذي يطابق الـ ID المرسل وفي نفس الوقت يتبع هذا المستخدم
        $child = Child::where('id', $id)
            ->where('parent_id', $userId) 
            ->with(['school', 'address', 'logistics'])
            ->first();
    
        // إذا لم يجد الطفل (بسبب عدم تطابق الـ parent_id أو لأنه غير موجود)
        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'عذراً، هذا السجل غير موجود أو لا تملك صلاحية الوصول إليه.'
            ], 404);
        }
    
        return response()->json([
            'success' => true,
            'data'    => new ChildResource($child)
        ], 200);
    }
    public function getSubscription($id): JsonResponse
    {
        $userId = auth()->id();
    
        // نجلب الطفل مع العلاقة الصحيحة (logistics)
        $child = Child::where('id', $id)
            ->where('parent_id', $userId)
            ->with(['logistics']) // تأكد أن الاسم هنا يطابق اسم الدالة في الموديل
            ->firstOrFail();
    
        // إرسال البيانات للـ Resource (مع التأكد من تمرير العلاقة logistics)
        return response()->json([
            'success' => true,
            'data'    => new SubscriptionResource($child->logistics) 
        ], 200);
    }
}