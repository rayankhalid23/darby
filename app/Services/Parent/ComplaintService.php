<?php

namespace App\Services\Parent;

use App\Models\Shared\Complaint;
use App\Models\Shared\Contract;
use App\Models\Shared\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ComplaintService
{
    public function getParentComplaints(int $parentUserId, array $filters = []): LengthAwarePaginator
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $query = Complaint::with(['driver.user', 'trip', 'resolvedBy'])
            ->where(function ($q) use ($parentId, $parentUserId) {
                $q->where('submitted_by', $parentId)
                  ->orWhere('submitted_by', $parentUserId);
            });

        $statusFilter = strtolower($filters['status'] ?? $filters['type'] ?? $filters['filter'] ?? 'all');

        if ($statusFilter !== 'all' && $statusFilter !== '' && $statusFilter !== 'all_complaints') {
            if ($statusFilter === 'pending') {
                $query->where(function ($q) {
                    $q->where('status', 'pending')
                      ->orWhere(function ($sub) {
                          $sub->whereNull('action_taken')
                              ->orWhere('action_taken', 'none')
                              ->orWhere('action_taken', '');
                      });
                });
            } elseif ($statusFilter === 'completed' || $statusFilter === 'resolved' || $statusFilter === 'action_taken') {
                $query->where(function ($q) {
                    $q->whereIn('status', ['completed', 'resolved', 'closed'])
                      ->orWhere(function ($sub) {
                          $sub->where('status', '!=', 'pending')
                              ->whereNotNull('action_taken')
                              ->where('action_taken', '!=', 'none')
                              ->where('action_taken', '!=', '');
                      });
                });
            } else {
                $query->where('status', $statusFilter);
            }
        }

        return $query->latest()->paginate(15);
    }
    public function getParentComplaintDetail(int $parentUserId, int $complaintId): Complaint
    {
        $parentId = $this->getParentRecordId($parentUserId);

        return Complaint::with(['driver.user', 'trip', 'resolvedBy'])
            ->where('id', $complaintId)
            ->where(function ($q) use ($parentId, $parentUserId) {
                $q->where('submitted_by', $parentId)
                  ->orWhere('submitted_by', $parentUserId);
            })
            ->firstOrFail();
    }

    public function createComplaint(int $parentUserId, array $data): Complaint
    {
        $parentId = $this->getParentRecordId($parentUserId);

        // البحث عن السائق سواء تم إمراره كـ driver_id أو كـ user_id
        $driver = \App\Models\Driver\Driver::where('id', $data['driver_id'])
            ->orWhere('user_id', $data['driver_id'])
            ->first();

        if (!$driver) {
            throw ValidationException::withMessages([
                'driver_id' => ['السائق المحدد غير موجود في النظام.'],
            ]);
        }

        $realDriverId = $driver->id;
        $driverUserId = $driver->user_id;

        // فحص وجود اشتراك سابق أو حالي في أي من الجداول (ActiveSubscription, Contract, SubscriptionRequest)
        $hasSubscription = \App\Models\Shared\ActiveSubscription::where(function ($q) use ($parentId, $parentUserId) {
                $q->where('parent_id', $parentId)->orWhere('parent_id', $parentUserId);
            })
            ->where(function ($q) use ($realDriverId, $driverUserId) {
                $q->where('driver_id', $realDriverId)->orWhere('driver_id', $driverUserId);
            })
            ->exists()
            || Contract::where(function ($q) use ($parentId, $parentUserId) {
                $q->where('parent_id', $parentId)->orWhere('parent_id', $parentUserId);
            })
            ->where(function ($q) use ($realDriverId, $driverUserId) {
                $q->where('driver_id', $realDriverId)->orWhere('driver_id', $driverUserId);
            })
            ->exists()
            || \App\Models\Shared\SubscriptionRequest::where(function ($q) use ($parentId, $parentUserId) {
                $q->where('parent_id', $parentId)->orWhere('parent_id', $parentUserId);
            })
            ->where(function ($q) use ($realDriverId, $driverUserId) {
                $q->where('driver_id', $realDriverId)->orWhere('driver_id', $driverUserId);
            })
            ->whereIn('status', ['accepted', 'contract_offered', 'pending', 'active'])
            ->exists();

        if (!$hasSubscription) {
            throw ValidationException::withMessages([
                'driver_id' => ['يجب أن يكون لديك اشتراك سابق أو حالي مع هذا السائق لتتمكن من تقديم شكوى ضده.'],
            ]);
        }

        if (!empty($data['trip_id'])) {
            $trip = Trip::where('id', $data['trip_id'])
                ->where(function ($q) use ($realDriverId, $driverUserId) {
                    $q->where('driver_id', $realDriverId)->orWhere('driver_id', $driverUserId);
                })
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
            'against_id'    => $realDriverId,
            'driver_id'     => $realDriverId,
            'trip_id'       => $data['trip_id'] ?? null,
            'description'   => $data['description'],
            'status'        => 'pending',
        ]);
    }


    public function deleteComplaint(int $parentUserId, int $complaintId): void
    {
        $parentId = $this->getParentRecordId($parentUserId);

        $complaint = Complaint::where('id', $complaintId)
            ->where(function ($q) use ($parentId, $parentUserId) {
                $q->where('submitted_by', $parentId)
                  ->orWhere('submitted_by', $parentUserId);
            })
            ->where('status', 'pending')
            ->firstOrFail();

        $complaint->delete();
    }

    public function updateComplaint(int $userId, int $complaintId, array $data): Complaint
{
    $complaint = Complaint::where('id', $complaintId)->firstOrFail();

    // التحقق من حالة الشكوى (مثلاً: عدم السماح للتعديل إذا كانت مغلقة أو قيد المعالجة)
    if (in_array($complaint->status, ['in_progress', 'resolved', 'closed'])) {
        throw new Exception('لا يمكن تعديل الشكوى لأنها قيد المعالجة أو مكتملة.');
    }

    // تحديث البيانات مباشرة (إذا تم إرسال trip_id كـ null ستتحدث في قاعدة البيانات إلى null)
    $complaint->update($data);

    return $complaint;
}
    

    

    /**
     * جلب رحلات السائق المرتبطة بولي الأمر بناءً على العقود والاشتراكات النشطة بينهما.
     *
     * @param int $parentUserId  (المعرف الرئيسي للمستخدم - الولي)
     * @param int $driverId      (المعرف المباشر للسائق في جدول السائقين أو المستخدمين)
     * @return \Illuminate\Support\Collection|array
     */
    public function getDriverTripsForParent(int $parentUserId, int $driverId)
    {
        try {
            $parent = \Illuminate\Support\Facades\DB::table('parents')
                ->where('user_id', $parentUserId)
                ->first();

            $parentId = $parent ? $parent->id : null;

            // تحديد معرّفات السائق (سواء مرر كـ driver_id أو كـ user_id)
            $driver = \Illuminate\Support\Facades\DB::table('drivers')
                ->where('id', $driverId)
                ->orWhere('user_id', $driverId)
                ->first();

            $realDriverId = $driver ? $driver->id : $driverId;
            $driverUserId = $driver ? $driver->user_id : null;

            $trips = \Illuminate\Support\Facades\DB::table('trips')
                ->where(function ($q) use ($realDriverId, $driverUserId) {
                    $q->where('driver_id', $realDriverId);
                    if ($driverUserId) {
                        $q->orWhere('driver_id', $driverUserId);
                    }
                })
                ->orderBy('id', 'desc')
                ->get();

            return $trips;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("خطأ في دالة getDriverTripsForParent: " . $e->getMessage());
            return [];
        }
    }


    private function getParentRecordId(int $userId): int
    {
        $parent = \App\Models\Parent\ParentModel::where('user_id', $userId)->first();
        if ($parent) {
            return $parent->id;
        }
        return $userId;
    }
}
