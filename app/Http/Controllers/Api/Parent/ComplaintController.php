<?php

namespace App\Http\Controllers\Api\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Parent\StoreComplaintRequest;
use App\Http\Requests\Api\Parent\UpdateComplaintRequest;
use App\Http\Resources\Api\Parent\ComplaintResource;
use App\Services\Parent\ComplaintService;
use Illuminate\Http\JsonResponse;

class ComplaintController extends Controller
{
    protected ComplaintService $complaintService;

    public function __construct(ComplaintService $complaintService)
    {
        $this->complaintService = $complaintService;
    }

    public function index(): JsonResponse
    {
        $complaints = $this->complaintService->getParentComplaints(
            auth()->id(),
            request()->only(['status'])
        );

        return response()->json([
            'success'    => true,
            'data'       => ComplaintResource::collection($complaints),
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page'    => $complaints->lastPage(),
                'total'        => $complaints->total(),
                'per_page'     => $complaints->perPage(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $complaint = $this->complaintService->getParentComplaintDetail(auth()->id(), $id);

        return response()->json([
            'success' => true,
            'data'    => new ComplaintResource($complaint),
        ]);
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->complaintService->createComplaint(
            auth()->id(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم الشكوى بنجاح، بانتظار مراجعة الإدارة.',
            'data'    => new ComplaintResource($complaint),
        ], 201);
    }

    public function update(UpdateComplaintRequest $request, int $id): JsonResponse
    {
        $complaint = $this->complaintService->updateComplaint(
            auth()->id(),
            $id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الشكوى بنجاح.',
            'data'    => new ComplaintResource($complaint),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->complaintService->deleteComplaint(auth()->id(), $id);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الشكوى بنجاح.',
        ]);
    }

    public function driverTrips(int $driverId): JsonResponse
    {
        $trips = $this->complaintService->getDriverTripsForParent(auth()->id(), $driverId);

        return response()->json([
            'success' => true,
            'data'    => $trips,
        ]);
    }
}
