<?php

namespace App\Services\Admin;

use App\Models\Shared\Complaint;
use App\Models\Driver\Driver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function getAllComplaints(array $filters = []): LengthAwarePaginator
    {
        $query = Complaint::with(['submittedBy.user', 'driver.user', 'trip', 'resolvedBy'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        return $query->paginate(15);
    }

    public function getComplaintDetail(int $id): Complaint
    {
        return Complaint::with(['submittedBy.user', 'driver.user', 'trip', 'resolvedBy'])
            ->findOrFail($id);
    }

    public function getDriverComplaints(int $driverId, array $filters = []): LengthAwarePaginator
    {
        $query = Complaint::with(['submittedBy.user', 'trip', 'resolvedBy'])
            ->where('driver_id', $driverId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    public function reviewComplaint(int $complaintId, string $action, ?string $actionDetails, int $adminId): Complaint
    {
        return DB::transaction(function () use ($complaintId, $action, $actionDetails, $adminId) {
            $complaint = Complaint::findOrFail($complaintId);

            if ($complaint->status !== 'pending') {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], []),
                    response()->json(['status' => false, 'message' => 'هذه الشكوى تم معالجتها مسبقاً.'], 422)
                );
            }

            switch ($action) {
                case 'warning':
                    $complaint->status = 'completed';
                    $complaint->action_taken = 'warning';
                    break;

                case 'suspension':
                    $complaint->status = 'completed';
                    $complaint->action_taken = 'suspension';
                    if ($complaint->driver_id) {
                        Driver::where('id', $complaint->driver_id)->update(['status' => 'Suspended']);
                    }
                    break;

                case 'dismiss':
                    $complaint->status = 'dismissed';
                    $complaint->action_taken = 'none';
                    break;
            }

            $complaint->resolved_by = $adminId;
            $complaint->action_details = $actionDetails;
            $complaint->resolved_at = now();
            $complaint->save();

            return $complaint->fresh()->load(['submittedBy.user', 'driver.user', 'resolvedBy']);
        });
    }

    public function suspendDriver(int $driverId, int $adminId): Driver
    {
        $driver = Driver::findOrFail($driverId);
        $driver->status = 'Suspended';
        $driver->save();

        return $driver;
    }
}
