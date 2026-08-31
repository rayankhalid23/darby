<?php

namespace App\Http\Controllers\Api\Trip;

use App\Http\Controllers\Controller;
use App\Services\Trip\ParentTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function getActiveTrips(Request $request): JsonResponse
    {
        try {
            $data = $this->parentTripService->getActiveTripsForParent(Auth::id(), $request->query('date'));
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
    public function getUpcomingTrips(Request $request): JsonResponse
    {
        try {
            $data = $this->parentTripService->getUpcomingTrips(Auth::id(), $request->query('date'));
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 4. GET /api/parent/trips/history
     */
    public function getTripHistory(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $data = $this->parentTripService->getTripHistory(Auth::id(), $perPage, $request->query('date'));
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
    public function getChildTripsOverview(Request $request, $childId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getChildTripsOverview(Auth::id(), (int)$childId, $request->query('date'));
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
     * 9. GET /api/parent/trips/{tripId}/children/{childId}/progress
     */
    public function getChildTripProgress($tripId, $childId): JsonResponse
    {
        try {
            $data = $this->parentTripService->getChildTripProgress(Auth::id(), (int)$tripId, (int)$childId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 10. GET /api/parent/trips/active/tracking
     */
    public function getBulkActiveTracking(Request $request): JsonResponse
    {
        try {
            $data = $this->parentTripService->getBulkActiveTracking(Auth::id(), $request->query('date'));
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}