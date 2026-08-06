<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Trip\DailyTripGenerationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class TripOpsController extends Controller
{
    protected DailyTripGenerationService $dailyTripGenerationService;

    public function __construct(DailyTripGenerationService $dailyTripGenerationService)
    {
        $this->dailyTripGenerationService = $dailyTripGenerationService;
    }

    /**
     * تشغيل يدوي لتوليد الرحلات اليومية دون انتظار الـ Cron (للتشغيل/الاختبار)
     * POST /api/admin/trips/generate-daily
     */
    public function generateDaily(): JsonResponse
    {
        try {
            $result = $this->dailyTripGenerationService->generateDueTrips();

            return response()->json([
                'status'    => 'success',
                'checked'   => $result['checked'],
                'generated' => $result['generated'],
                'skipped'   => $result['skipped'],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
