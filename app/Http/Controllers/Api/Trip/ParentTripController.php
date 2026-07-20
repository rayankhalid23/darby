<?php

namespace App\Http\Controllers\Api\Trip;

use App\Http\Controllers\Controller;
use App\Services\Trip\ParentTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class ParentTripController extends Controller
{
    protected ParentTripService $parentTripService;

    public function __construct(ParentTripService $parentTripService)
    {
        $this->parentTripService = $parentTripService;
    }

    /**
     * عرض الرحلة المفعلة تواً (مهم جداً للشاشة الرئيسية لولي الأمر)
     */
    public function getActiveTrips(): JsonResponse
    {
        try {
            $data = $this->parentTripService->getActiveTripsForParent(Auth::id());
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * التتبع اللحظي للرحلة من خريطة ولي الأمر
     */
    public function getLiveTracking($tripId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getLiveTracking(Auth::id(), (int)$tripId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * عرض الرحلات القادمة
     */
    public function getUpcomingTrips(): JsonResponse
    {
        try {
            $data = $this->parentTripService->getUpcomingTrips(Auth::id());
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * أرشيف كل الرحلات للأطفال المشتركين
     */
    public function getTripHistory(): JsonResponse
    {
        try {
            $data = $this->parentTripService->getTripHistory(Auth::id());
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}