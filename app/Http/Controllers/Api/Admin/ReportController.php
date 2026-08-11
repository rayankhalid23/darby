<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * 1️⃣ الإحصائيات السريعة (KPI Cards)
     * GET /api/admin/reports/kpi-summary
     */
    public function kpiSummary(): JsonResponse
    {
        try {
            $data = $this->reportService->getKpiSummary();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإحصائيات السريعة ومؤشرات KPI بنجاح.',
                'data'    => $data,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإحصائيات السريعة: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 2️⃣ التقارير المالية (Financial Reports)
     * GET /api/admin/reports/financial
     */
    public function financialReport(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['period', 'date_from', 'date_to']);
            $data = $this->reportService->getFinancialReport($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب التقارير المالية والرسوم البيانية بنجاح.',
                'data'    => $data,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التقرير المالي: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 3️⃣ تقارير التشغيل والرحلات (Operational & Trips Reports)
     * GET /api/admin/reports/trips
     */
    public function tripsReport(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['period', 'date_from', 'date_to']);
            $data = $this->reportService->getTripsReport($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تقارير الرحلات وإحصائيات الغياب والخريطة الحرارية بنجاح.',
                'data'    => $data,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تقرير الرحلات: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 4️⃣ تقارير الاشتراكات (Subscriptions Reports)
     * GET /api/admin/reports/subscriptions
     */
    public function subscriptionsReport(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['period', 'date_from', 'date_to']);
            $data = $this->reportService->getSubscriptionsReport($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تقارير وتوزيعات الاشتراكات والاشتراكات المنتهية قريباً بنجاح.',
                'data'    => $data,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تقرير الاشتراكات: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 5️⃣ تقارير أداء السائقين (Driver Performance Reports)
     * GET /api/admin/reports/drivers-performance
     */
    public function driversPerformanceReport(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'sort_by', 'per_page', 'page']);
            $data = $this->reportService->getDriversPerformanceReport($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تقارير أداء السائقين وحالة التراخيص والوثائق بنجاح.',
                'data'    => $data,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تقرير أداء السائقين: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 6️⃣ تصدير التقارير (CSV / JSON)
     * GET /api/admin/reports/export
     */
    public function exportReport(Request $request): mixed
    {
        try {
            $type    = $request->query('type', 'kpi');
            $format  = strtolower($request->query('format', 'json'));
            $filters = $request->only(['period', 'date_from', 'date_to', 'search', 'sort_by', 'per_page']);

            $result = $this->reportService->exportReport($type, $filters, $format);

            if ($format === 'csv') {
                $filename = "report_{$type}_" . date('Y-m-d_H-i-s') . ".csv";
                return response($result, 200, [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                    'Pragma'              => 'no-cache',
                    'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires'             => '0',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تصدير التقرير بنجاح.',
                'data'    => $result,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تصدير التقرير: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
