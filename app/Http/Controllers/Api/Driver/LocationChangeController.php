<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\RespondLocationChangeRequestRequest;
use App\Http\Resources\Api\Shared\LocationChangeRequestResource;
use App\Services\Shared\LocationChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class LocationChangeController extends Controller
{
    protected LocationChangeService $service;

    public function __construct(LocationChangeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $requests = $this->service->getDriverRequests($request->user()->id, $request->query('status'));

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات تغيير الموقع بنجاح.',
                'data'    => LocationChangeRequestResource::collection($requests),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function respond(RespondLocationChangeRequestRequest $request, int $id): JsonResponse
    {
        try {
            $changeRequest = $this->service->respondToChange(
                $request->user()->id,
                $id,
                $request->input('status') === 'approved',
                $request->input('rejection_reason')
            );

            return response()->json([
                'success' => true,
                'message' => $request->input('status') === 'approved'
                    ? 'تمت الموافقة على تغيير الموقع وتحديث المسار، وتم إشعار ولي الأمر.'
                    : 'تم رفض طلب تغيير الموقع، وتم إشعار ولي الأمر.',
                'data'    => new LocationChangeRequestResource($changeRequest),
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
