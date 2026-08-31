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
        $query = DriverReview::with(['parent', 'driver.user'])
            ->where('driver_id', $driverId);

        if (request()->filled('ai_action')) {
            $query->where('ai_action', request('ai_action'));
        }

        if (request()->filled('min_severity')) {
            $query->where('ai_severity', '>=', (int) request('min_severity'));
        }

        if (request()->boolean('flagged_only')) {
            $query->where('ai_severity', '>=', 2);
        }

        $reviews = $query->latest()->paginate(request('per_page', 10));

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
            $query = DriverReview::with(['parent', 'driver.user']);

            if (request()->filled('ai_action')) {
                $query->where('ai_action', request('ai_action'));
            }

            if (request()->filled('min_severity')) {
                $query->where('ai_severity', '>=', (int) request('min_severity'));
            }

            if (request()->boolean('flagged_only')) {
                $query->where('ai_severity', '>=', 2);
            }

            $reviews = $query->latest()
                ->paginate(request('per_page', 15));

            return response()->json([
                'status'  => true,
                // نفس Resource المستخدم في index() لتوحيد شكل الاستجابة بين الـ endpoint-ين
                'data'    => \App\Http\Resources\Api\Parent\DriverReviewResource::collection($reviews),
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