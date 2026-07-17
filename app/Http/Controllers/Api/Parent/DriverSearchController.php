<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\SearchDriversRequest;
use App\Services\Parent\DriverMatchingService;
use App\Http\Resources\Api\Parent\DriverMatchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DriverSearchController extends Controller
{
    public function __construct(
        protected DriverMatchingService $matchingService
    ) {}

    public function search(SearchDriversRequest $request): JsonResponse
    {
        try {
            // 💡 تصحيح المعرّف: جلب الـ parent_id الحقيقي المقابل للمستخدم الحالي
            $parent = DB::table('parents')->where('user_id', auth()->id())->first();
            
            if (!$parent) {
                return response()->json([
                    'status'  => false,
                    'message' => 'عذراً، لم يتم العثور على ملف ولي أمر نشط مرتبط بهذا الحساب.'
                ], Response::HTTP_NOT_FOUND);
            }

            $parentId = (int) $parent->id;
            $filters  = $request->validated();

            // تشغيل محرك الفلترة والتسعير بالمعرف الصحيح
            $drivers = $this->matchingService->matchDrivers($filters, $parentId);

            $isEmpty = $drivers->isEmpty();

            return response()->json([
                'status'  => true,
                'message' => $isEmpty
                    ? 'لم يتم العثور على سائقين مطابقين للبحث.'
                    : 'تمت الفلترة وجلب السائقين بنجاح.',

                'meta' => [
                    'current_page' => $drivers->currentPage(),
                    'last_page'    => $drivers->lastPage(),
                    'per_page'     => $drivers->perPage(),
                    'total'        => $drivers->total(),
                ],

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