<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\StoreTripManualConfirmationRequest;
use App\Http\Resources\Api\Shared\TripManualConfirmationResource;
use App\Services\Trip\TripManualConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TripManualConfirmationController extends Controller
{
    protected TripManualConfirmationService $service;

    public function __construct(TripManualConfirmationService $service)
    {
        $this->service = $service;
    }

    /**
     * جميع أولياء الأمور والأطفال المشتركين مع السائق، لاختيار الطفل المعني.
     */
    public function subscribedParentsAndChildren(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getSubscribedParentsAndChildren($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أولياء الأمور والأطفال بنجاح.',
                'data'    => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * الرحلات السابقة التي لم تُنفَّذ/تُغلق بشكل صحيح.
     */
    public function incompleteTrips(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getIncompleteTrips($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الرحلات غير الموثّقة بنجاح.',
                'data'    => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * الأطفال القابلين لطلب تأكيد يدوي ضمن رحلة معيّنة.
     */
    public function tripChildren(Request $request, int $tripId): JsonResponse
    {
        try {
            $data = $this->service->getTripPendingChildren($request->user()->id, $tripId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أطفال الرحلة بنجاح.',
                'data'    => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function store(StoreTripManualConfirmationRequest $request): JsonResponse
    {
        try {
            $confirmations = $this->service->requestConfirmations(
                $request->user()->id,
                (int) $request->input('trip_id'),
                $request->input('child_ids')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال طلبات التأكيد لأولياء الأمور المعنيين.',
                'data'    => TripManualConfirmationResource::collection($confirmations),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
