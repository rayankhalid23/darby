<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Shared\DriverReview;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Exception;

class DriverReviewController extends Controller
{
    /**
     * 📊 جلب تقييمات سائق معين (لوحة الأدمن)
     */
    public function index(int $driverId): JsonResponse
    {
        $reviews = DriverReview::with(['parent', 'driver'])
            ->where('driver_id', $driverId)
            ->latest()
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
     * 🗑️ إمكانية الحذف الكامل والنهائي لأي تعليق في المنصة (Force Delete)
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

    /**
     * 🌐 عرض كافة التعليقات والتقييمات الموجودة في المنصة بالكامل
     */
    public function allReviews(): JsonResponse
    {
        try {
            // جلب العلاقات مباشرة بناءً على بنية موديول مشروعك الحالية
            $reviews = DriverReview::with(['parent', 'driver'])
                ->latest()
                ->paginate(15);

            $formattedReviews = collect($reviews->items())->map(function ($review) {
                return [
                    'review_id'   => $review->id,
                    'comment'     => $review->comment ?? 'بدون تعليق ناصي',
                    'rating'      => $review->rating,
                    // التعديل الذكي: استخراج الاسم مباشرة من الحقل المرتبط بـ User أو حقول الموديول المتاحة
                    'parent_name' => $review->parent ? ($review->parent->full_name ?? $review->parent->name ?? 'ولي أمر') : 'مستخدم محذوف',
                    'driver_name' => $review->driver ? ($review->driver->full_name ?? $review->driver->name ?? 'سائق') : 'سائق محذوف',
                    'is_deleted'  => $review->trashed(),
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
}