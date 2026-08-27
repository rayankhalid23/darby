<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\RespondTripManualConfirmationRequest;
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

    public function index(Request $request): JsonResponse
    {
        $confirmations = $this->service->getParentPendingConfirmations($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب طلبات التأكيد المعلّقة بنجاح.',
            'data'    => TripManualConfirmationResource::collection($confirmations),
        ], 200);
    }

    public function respond(RespondTripManualConfirmationRequest $request, int $id): JsonResponse
    {
        try {
            $confirmation = $this->service->respondToConfirmation(
                $request->user()->id,
                $id,
                (bool) $request->input('confirmed')
            );

            return response()->json([
                'success' => true,
                'message' => $request->boolean('confirmed')
                    ? 'شكراً لتأكيدك، تم تحديث حالة الرحلة.'
                    : 'تم تسجيل ردك، وتم إشعار السائق.',
                'data'    => new TripManualConfirmationResource($confirmation),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
