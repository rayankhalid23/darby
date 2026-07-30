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
     * 1. GET /api/parent/trips/active
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
     * 2. GET /api/parent/trips/{tripId}/track
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
     * 3. GET /api/parent/trips/upcoming
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
     * 4. GET /api/parent/trips/history
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

    /**
     * 5. GET /api/parent/trips/{tripId}
     */
    public function getTripDetails($tripId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getTripDetails(Auth::id(), (int)$tripId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 6. GET /api/parent/trips/{tripId}/timeline
     */
    public function getTripTimeline($tripId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getTripTimeline(Auth::id(), (int)$tripId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 7. GET /api/parent/children/{childId}/trips
     */
    public function getChildTripsOverview($childId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getChildTripsOverview(Auth::id(), (int)$childId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 8. GET /api/parent/trips/{tripId}/children/{childId}/status
     */
    public function getChildTripStatus($tripId, $childId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getChildTripStatus(Auth::id(), (int)$tripId, (int)$childId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 9. GET /api/parent/trips/active/tracking
     */
    public function getBulkActiveTracking(): JsonResponse
    {
        try {
            $data = $this->parentTripService->getBulkActiveTracking(Auth::id());
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}