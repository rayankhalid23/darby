<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\SearchDriversRequest;
use App\Services\Parent\DriverMatchingService;
use App\Http\Resources\Api\Parent\DriverMatchResource;
use App\Http\Resources\Api\Parent\ChildMatchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DriverSearchController extends Controller
{
    public function __construct(
        protected DriverMatchingService $matchingService
    ) {}

    /**
     * البحث والفلترة المتقدمة للسائقين
     * ─────────────────────────────────────────────────────────
     * POST /api/parent/drivers/search
     *
     * الفلاتر المتاحة:
     *   search_query   : بحث بالاسم أو رقم الهاتف
     *   driver_gender  : جنس السائق (male / female)
     *   has_ac         : وجود مكيف في السيارة (true / false)
     *   child_ids      : مصفوفة معرفات الأطفال
     *                    (إذا فارغة → يعمل على كل أطفال ولي الأمر)
     *
     * يتم تلقائياً:
     *   - جلب بيانات الاشتراك (child_logistics) للأطفال
     *   - الفلترة بالمنطقة مع fallback للبلدية
     *   - حساب السعر التقديري لكل سائق حسب:
     *       * المسافة (Haversine) بين المنزل والمدرسة
     *       * نوع السيارة: مكيفة 2 د.ل/كم | غير مكيفة 1.5 د.ل/كم
     *       * نوع الاشتراك: يومي (1 يوم) | شهري (أيام العمل الفعلية)
     */
    public function search(SearchDriversRequest $request): JsonResponse
    {
        try {
            $parentId = auth()->id();
            $filters  = $request->validated();

            // تشغيل محرك الفلترة والتسعير
            $drivers = $this->matchingService->matchDrivers($filters, $parentId);

            $isEmpty = $drivers->isEmpty();

            return response()->json([
                'status'  => true,
                'message' => $isEmpty
                    ? 'لم يتم العثور على سائقين مطابقين للبحث.'
                    : 'تمت الفلترة وجلب السائقين بنجاح.',

                // ── بيانات التصفح ──
                'meta' => [
                    'current_page' => $drivers->currentPage(),
                    'last_page'    => $drivers->lastPage(),
                    'per_page'     => $drivers->perPage(),
                    'total'        => $drivers->total(),
                ],

                // ── قائمة السائقين مع التسعير ──
                'data' => DriverMatchResource::collection($drivers->items()),

            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status'     => false,
                'error_code' => 'FILTER_ERROR',
                'message'    => 'حدث خطأ أثناء معالجة طلب البحث: ' . $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}