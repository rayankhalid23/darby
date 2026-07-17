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

        // 🎯 الفلترة الذكية المعتمدة على الـ Status وقيمة الـ action_taken
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'pending') {
                // الشكاوى المعلقة: حالتها pending وحقل الإجراء هو 'none' أو فارغ
                $query->where('status', 'pending')
                      ->where(function ($q) {
                          $q->whereNull('action_taken')
                            ->orWhere('action_taken', 'none')
                            ->orWhere('action_taken', '');
                      });
            } elseif ($filters['type'] === 'resolved' || $filters['type'] === 'action_taken') {
                // الشكاوى المتخذ فيها قرار: حالتها ليست pending أو حقل الإجراء يحتوي على قرار حقيقي (ليس none وليس فارغاً)
                $query->where(function ($q) {
                    $q->where('status', '!=', 'pending')
                      ->orWhere(function ($sub) {
                          $sub->whereNotNull('action_taken')
                              ->where('action_taken', '!=', 'none')
                              ->where('action_taken', '!=', '');
                      });
                });
            }
        }

        // الحفاظ على فلترة الـ status المباشرة كدعم إضافي
        if (!empty($filters['status']) && empty($filters['type'])) {
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

        $contract = Contract::where('parent_id', $parentId)
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

    /**
     * جلب رحلات السائق المرتبطة بولي الأمر بناءً على العقود النشطة بينهما.
     *
     * @param int $parentUserId  (المعرف الرئيسي للمستخدم - الولي)
     * @param int $driverId      (المعرف المباشر للسائق في جدول السائقين)
     * @return \Illuminate\Support\Collection|array
     */
    public function getDriverTripsForParent(int $parentUserId, int $driverId)
    {
        try {
            // 1. جلب سجل الولي مباشرة من الجدول لضمان عدم حدوث خطأ Class Not Found
            $parent = \Illuminate\Support\Facades\DB::table('parents')
                ->where('user_id', $parentUserId)
                ->first();

            if (!$parent) {
                \Illuminate\Support\Facades\Log::warning("ComplaintService: لم يتم العثور على سجل ولي أمر للمستخدم ID: {$parentUserId}");
                return [];
            }

            // 2. جلب الرحلات باستخدام Query Builder لضمان السرعة والعمل الفوري
            // نقوم بجلب الرحلات التابعة للسائق والتي يربطها عقد نشط مع ولي الأمر هذا
            $trips = \Illuminate\Support\Facades\DB::table('trips')
                ->join('contracts', 'contracts.driver_id', '=', 'trips.driver_id')
                ->where('trips.driver_id', $driverId)
                ->where('contracts.parent_id', $parent->id)
                ->where('contracts.status', 'active')
                ->select('trips.*') // جلب بيانات جدول الرحلات فقط
                ->distinct()        // منع تكرار الرحلات
                ->orderBy('trips.id', 'desc') // أحدث الرحلات أولاً
                ->get();

            // تحويل النتيجة إلى مصفوفة أو تجميعة كائنات قياسية لتتوافق مع الـ Controller
            return $trips;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("خطأ في دالة getDriverTripsForParent: " . $e->getMessage());
            return [];
        }
    }


    private function getParentRecordId(int $userId): int
    {
        $parent = \App\Models\Parent\ParentModel::where('user_id', $userId)->firstOrFail();
        return $parent->id;
    }
}
