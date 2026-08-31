<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver\Driver;
use App\Services\Driver\DriverStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DriverStatisticsController extends Controller
{
    protected DriverStatisticsService $statisticsService;

    public function __construct(DriverStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * جلب ملف السائق المرتبط بالمستخدم الموثق
     */
    private function getAuthenticatedDriver(Request $request): ?Driver
    {
        return $request->user()->driver ?? Driver::where('user_id', $request->user()->id)->first();
    }

    /**
     * GET /api/driver/statistics
     * GET /api/driver/dashboard/stats
     *
     * إرجاع لوحة الإحصائيات الشاملة للسائق:
     * 1. الإحصائيات المالية (صافي الأرباح، الشهر الحالي/السابق، الأرباح المتوقعة، الرصيد المعلق والمتاح).
     * 2. إحصائيات الاشتراكات والركاب (الطلبة النشطين، سعة المركبة والمقاعد المتبقية، السجل التاريخي).
     * 3. إحصائيات الرحلات اليومية والتشغيل (الرحلات المنجزة، معدل الالتزام بالمواعيد، الغيابات والأعطال).
     * 4. تنبيهات وإحصائيات سريعة (اشتراكات تنتهي خلال 5 أيام، حالة صلاحية الوثائق والرخصة).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $driver = $this->getAuthenticatedDriver($request);

            if (!$driver) {
                return response()->json([
                    'status'  => false,
                    'success' => false,
                    'message' => 'بيانات السائق غير موجودة أو الحساب غير مصرح له كـ سائق.',
                ], 403);
            }

            $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year'  => 'nullable|integer|min:2020|max:2099',
            ]);

            $month = $request->filled('month') ? (int) $request->query('month') : null;
            $year  = $request->filled('year') ? (int) $request->query('year') : null;

            $stats = $this->statisticsService->getDashboardStatistics($driver, $month, $year);

            return response()->json([
                'status'  => true,
                'success' => true,
                'message' => 'تم جلب إحصائيات السائق ولوحة التحكم بنجاح.',
                'data'    => $stats,
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إحصائيات السائق.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
