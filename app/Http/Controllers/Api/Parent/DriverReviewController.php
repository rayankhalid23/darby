<?php

namespace App\Http\Controllers\Api\Parent;

use App\Models\Shared\DriverReview;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Api\Parent\StoreDriverReviewRequest;
use Illuminate\Http\Request;
use Exception;

class DriverReviewController extends Controller
{
    /**
     * 🌐 عرض كافة التعليقات والتقييمات الموجودة في المنصة بالكامل
     */
    public function allReviews(): JsonResponse
    {
        try {
            $reviews = DriverReview::with(['parent.user', 'driver.user'])
                ->latest()
                ->withTrashed()
                ->paginate(15);

            $formattedReviews = collect($reviews->items())->map(function ($review) {
                return [
                    'review_id'   => $review->id,
                    'comment'     => $review->comment ?? 'بدون تعليق',
                    'rating'      => $review->rating,
                    'parent_name' => optional(optional($review->parent)->user)->full_name ?? 'مستخدم محذوف',
                    'driver_name' => ($review->driver && $review->driver->user) ? $review->driver->user->full_name : 'سائق محذوف',
                    'is_deleted'  => $review->trashed(),
                    'created_at'  => $review->created_at->toDateTimeString(),
                ];
            });

            return response()->json([
                'status'  => true,
                'data'    => $formattedReviews,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page'    => $reviews->lastPage(),
                    'total'        => $reviews->total(),
                    'per_page'     => $reviews->perPage(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error("AdminDriverReview [allReviews] - خطأ في جلب كافة التقييمات: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء جلب التقييمات من السيرفر.',
            ], 500);
        }
    }

    /**
     * 📊 جلب تقييمات سائق معين
     */
    public function index(int $driverId): JsonResponse
    {
        $reviews = DriverReview::with(['parent', 'driver'])
            ->where('driver_id', $driverId)
            ->latest()
            ->withTrashed()
            ->paginate(10);

        return response()->json([
            'status'  => true,
            'data'    => \App\Http\Resources\Api\Parent\DriverReviewResource::collection($reviews),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
                'per_page'     => $reviews->perPage(),
            ],
        ]);
    }

    /**
     * ➕ إضافة تقييم أو تعليق جديد للسائق من قبل ولي الأمر
     */
    public function store(StoreDriverReviewRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            
            // جلب الـ parent_id الصحيح المرتبط بجدول parents
            $parentRecord = $user->parentRecord ?? $user->parent ?? \App\Models\Parent\ParentModel::where('user_id', $user->id)->first();
            
            if (!$parentRecord) {
                return response()->json([
                    'status'  => false,
                    'message' => 'حساب ولي الأمر غير مرتبط بسجل صحيح.',
                ], 422);
            }

            // إنشاء تقييم جديد مع دعم التعدد لنفس السائق بكل سلاسة
            $review = DriverReview::create([
                'parent_id' => $parentRecord->id,
                'driver_id' => $request->driver_id,
                'rating'    => $request->rating,
                'comment'   => $request->comment,
                'status'    => 'active',
            ]);

            // تحميل العلاقات لعرضها بشكل كامل في الاستجابة
            $review->load(['parent.user', 'driver.user']);

            Log::info("DriverReview [Store] - تمت إضافة تقييم جديد بنجاح.", ['review_id' => $review->id]);

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة تقييمك وتعليقك بنجاح.',
                'data'    => new \App\Http\Resources\Api\Parent\DriverReviewResource($review)
            ], 201);

        } catch (Exception $e) {
            Log::error("DriverReview [Store] - خطأ أثناء حفظ التقييم: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء حفظ التعليق، يرجى المحاولة لاحقاً.',
            ], 500);
        }
    }

    /**
     * ✏️ تعديل تقييم أو تعليق سابق لولي الأمر
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $parentRecord = $user->parentRecord ?? $user->parent ?? \App\Models\Parent\ParentModel::where('user_id', $user->id)->first();

            if (!$parentRecord) {
                return response()->json([
                    'status'  => false,
                    'message' => 'حساب ولي الأمر غير مرتبط بسجل صحيح.',
                ], 422);
            }

            // البحث عن التقييم والتأكد أنه يتبع لولي الأمر نفسه لضمان الأمان
            $review = DriverReview::where('id', $id)
                ->where('parent_id', $parentRecord->id)
                ->first();

            if (!$review) {
                return response()->json([
                    'status'  => false,
                    'message' => 'التقييم غير موجود أو ليس لديك صلاحية لتعديله.',
                ], 404);
            }

            // التحقق من المدخلات الخاصة بالتعديل
            $validatedData = $request->validate([
                'rating'  => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // تحديث البيانات
            $review->update($validatedData);
            
            // إعادة تحميل العلاقات
            $review->load(['parent.user', 'driver.user']);

            Log::info("DriverReview [Update] - تم تعديل التقييم بنجاح.", ['review_id' => $review->id]);

            return response()->json([
                'status'  => true,
                'message' => 'تم تعديل التقييم بنجاح.',
                'data'    => new \App\Http\Resources\Api\Parent\DriverReviewResource($review)
            ]);

        } catch (Exception $e) {
            Log::error("DriverReview [Update] - خطأ أثناء تعديل التقييم رقم $id: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء تعديل التعليق، يرجى المحاولة لاحقاً.',
            ], 500);
        }
    }

    /**
     * 🗑️ إمكانية الحذف الكامل والنهائي لأي تعليق (لوحة الأدمن)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $review = DriverReview::withTrashed()->findOrFail($id);
            
            Log::info("AdminDriverReview [Destroy] - الأدمن يقوم بحذف التقييم نهائياً.", ['review_id' => $id]);
            
            $review->forceDelete();

            return response()->json([
                'status'  => true,
                'message' => 'تم حذف تقييم السائق نهائياً وبنجاح من المنصة.',
            ]);

        } catch (Exception $e) {
            Log::error("AdminDriverReview [Destroy] - فشل الحذف النهائي للتقييم رقم $id: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'التقييم غير موجود أو تم حذفه نهائياً مسبقاً.',
            ], 404);
        }
    }
}