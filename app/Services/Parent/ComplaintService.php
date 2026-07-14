<?php

namespace App\Services\Parent;

use App\Models\Shared\Complaint;
use App\Models\Shared\Contract;
use App\Models\Shared\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ComplaintService
{
    public function getParentComplaints(int $parentUserId, array $filters = []): LengthAwarePaginator
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $query = Complaint::with(['driver.user', 'trip', 'resolvedBy'])
            ->where('submitted_by', $parentId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function getParentComplaintDetail(int $parentUserId, int $complaintId): Complaint
    {
        $parentId = $this->getParentRecordId($parentUserId);

        return Complaint::with(['driver.user', 'trip', 'resolvedBy'])
            ->where('id', $complaintId)
            ->where('submitted_by', $parentId)
            ->firstOrFail();
    }

    public function createComplaint(int $parentUserId, array $data): Complaint
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $contract = Contract::where('parent_id', $parentUserId)
            ->where('driver_id', $data['driver_id'])
            ->whereIn('status', ['active', 'cancelled', 'terminated'])
            ->first();

        if (!$contract) {
            throw ValidationException::withMessages([
                'driver_id' => ['يجب أن يكون لديك اشتراك سابق مع هذا السائق لتتمكن من تقديم شكوى ضده.'],
            ]);
        }

        if (!empty($data['trip_id'])) {
            $trip = Trip::where('id', $data['trip_id'])
                ->where('driver_id', $data['driver_id'])
                ->first();

            if (!$trip) {
                throw ValidationException::withMessages([
                    'trip_id' => ['الرحلة المحددة لا تنتمي لهذا السائق.'],
                ]);
            }
        }

        return Complaint::create([
            'submitted_by'  => $parentId,
            'against_type'  => 'DRIVER',
            'against_id'    => $data['driver_id'],
            'driver_id'     => $data['driver_id'],
            'trip_id'       => $data['trip_id'] ?? null,
            'description'   => $data['description'],
            'status'        => 'pending',
        ]);
    }

    public function updateComplaint(int $parentUserId, int $complaintId, array $data): Complaint
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $complaint = Complaint::where('id', $complaintId)
            ->where('submitted_by', $parentId)
            ->where('status', 'pending')
            ->firstOrFail();

        $complaint->update([
            'description' => $data['description'],
        ]);

        return $complaint->fresh();
    }

    public function deleteComplaint(int $parentUserId, int $complaintId): void
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $complaint = Complaint::where('id', $complaintId)
            ->where('submitted_by', $parentId)
            ->where('status', 'pending')
            ->firstOrFail();

        $complaint->delete();
    }

    public function getDriverTripsForParent(int $parentUserId, int $driverId): \Illuminate\Support\Collection
    {
        $contract = Contract::where('parent_id', $parentUserId)
            ->where('driver_id', $driverId)
            ->whereIn('status', ['active', 'cancelled', 'terminated'])
            ->first();

        if (!$contract) {
            return collect();
        }

        $routeIds = \App\Models\Shared\Route::where('contract_id', $contract->id)->pluck('id');

        return Trip::whereIn('route_id', $routeIds)
            ->where('driver_id', $driverId)
            ->orderBy('trip_date', 'desc')
            ->orderBy('scheduled_at', 'desc')
            ->get(['id', 'trip_date', 'trip_type', 'status', 'scheduled_at']);
    }

    private function getParentRecordId(int $userId): int
    {
        $parent = \App\Models\Parent\ParentModel::where('user_id', $userId)->firstOrFail();
        return $parent->id;
    }
}
