<?php

namespace App\Services\Admin;

use App\Models\Shared\Complaint;
use App\Models\Driver\Driver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ComplaintService
{
    /**
     * جلب كافة الشكاوى في النظام مع الفلترة الذكية والربط الآمن للعلاقات
     */
    public function getAllComplaints(array $filters = []): LengthAwarePaginator
    {
        $query = Complaint::with(['submittedBy.user', 'driver.user', 'trip', 'resolvedBy'])
            ->latest();
    
        // استخدام دالة array_filter لإزالة أي فلاتر فارغة أو تم إرسالها كـ null بالخطأ من Postman
        $cleanFilters = array_filter($filters, function($value) {
            return !is_null($value) && $value !== '';
        });
    
        if (!empty($cleanFilters['status'])) {
            $query->where('status', $cleanFilters['status']);
        }
    
        if (!empty($cleanFilters['driver_id'])) {
            $query->where('driver_id', $cleanFilters['driver_id']);
        }
    
        return $query->paginate(15);
    }

    /**
     * عرض تفصيلي لشكوى واحدة معينة بمعرفها الرقمي
     */
    public function getComplaintDetail(int $id): Complaint
    {
        return Complaint::with(['submittedBy.user', 'driver.user', 'trip', 'resolvedBy'])
            ->findOrFail($id);
    }

    /**
     * جلب سجل وتاريخ الشكاوى الخاص بسائق محدد مع إمكانية الفلترة حسب الحالة
     */
    public function getDriverComplaints(int $driverId, array $filters = []): LengthAwarePaginator
    {
        $query = Complaint::with(['submittedBy.user', 'trip', 'resolvedBy'])
            ->where('driver_id', $driverId);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate(15);
    }

    /**
     * مراجعة الشكوى واتخاذ الإجراء الإداري الحاسم ضمن بيئة تراسلية آمنة (Database Transaction)
     */
    public function reviewComplaint(int $complaintId, string $action, ?string $actionDetails, int $adminId): Complaint
    {
        return DB::transaction(function () use ($complaintId, $action, $actionDetails, $adminId) {
            
            $complaint = Complaint::findOrFail($complaintId);

            // منع التعديل على شكاوى تم البت فيها وإغلاقها مسبقاً لحماية نزاهة البيانات وموثوقيتها للبيع
            if ($complaint->status !== 'pending') {
                throw ValidationException::withMessages([
                    'complaint' => ['هذه الشكوى تم معالجتها والبت فيها مسبقاً ولا يمكن تعديل الإجراء البنيوي لها.']
                ]);
            }

            // تنفيذ القرار الإداري بناءً على نوع الأكشن المرسل والمثبت بالـ Validation
            switch ($action) {
                case 'warning':
                    $complaint->status = 'completed';
                    $complaint->action_taken = 'warning';
                    break;

                case 'suspension':
                    $complaint->status = 'completed';
                    $complaint->action_taken = 'suspension';
                    // تطبيق الإيقاف الفوري المباشر على حساب السائق في جدول السائقين ليعمل النظام بتناغم تزامني
                    if ($complaint->driver_id) {
                        Driver::where('id', $complaint->driver_id)->update(['status' => 'Suspended']);
                    }
                    break;

                case 'dismiss':
                    $complaint->status = 'dismissed';
                    $complaint->action_taken = 'none';
                    break;
            }

            // توثيق بيانات الآدمن والوقت الفعلي والتفاصيل الإدارية للقرار
            $complaint->resolved_by = $adminId;
            $complaint->action_details = $actionDetails;
            $complaint->resolved_at = now();
            $complaint->save();

            // إعادة تحميل الكائن محمل ببيانات العلاقات المحدثة ليعود مباشرة للـ API Client
            return $complaint->fresh()->load(['submittedBy.user', 'driver.user', 'resolvedBy']);
        });
    }

    /**
     * إيقاف السائق مباشرة وبشكل مستقل عند الضرورة القصوى من قبل الإدارة
     */
    public function suspendDriver(int $driverId, int $adminId): Driver
    {
        $driver = Driver::findOrFail($driverId);
        $driver->status = 'Suspended';
        $driver->save();

        return $driver;
    }
}