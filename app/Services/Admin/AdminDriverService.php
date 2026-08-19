<?php

namespace App\Services\Admin;

use App\Models\Driver\Driver;
use App\Models\Driver\DriverApproval;
use App\Services\Notification\NotificationService;
use App\Services\Admin\AdminAuditLogService;
use App\Services\Shared\EmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class AdminDriverService
{
    protected EmailService $emailService;
    protected AdminAuditLogService $auditLogService;
    protected NotificationService $notificationService;

    public function __construct(EmailService $emailService, AdminAuditLogService $auditLogService, NotificationService $notificationService)
    {
        $this->emailService = $emailService;
        $this->auditLogService = $auditLogService;
        $this->notificationService = $notificationService;
    }

    /**
     * جلب طلبات اشتراك السائقين المعلقة فقط حصرياً للآدمن
     */
    public function getDriversList(array $filters): LengthAwarePaginator
    {
        // القيم الصحيحة للـ enum في قاعدة البيانات
        $validStatuses = ['Pending', 'Approved', 'Suspended', 'Rejected', 'Offline', 'ON_TRIP'];

        // 1. بدء الاستعلام مع كسر حجب جدول المستخدمين (Users) المرتبطين بالسائقين
        $query = Driver::with(['user' => function($q) {
            $q->withTrashed()->withoutGlobalScopes();
        }]);

        // 2. ✅ فلترة الحالة: إذا أرسل الأدمن status يُطبَّق، وإلا يُجلب جميع السائقين
        if (!empty($filters['status']) && in_array($filters['status'], $validStatuses, true)) {
            $query->where('status', $filters['status']);
        }
        // ملاحظة: لا يوجد where افتراضي — الأدمن يرى الكل بدون فلتر

        // 3. فلترة البحث النصي (تُفعّل فقط إذا كتب الآدمن نصاً في خانة البحث)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->withTrashed()->withoutGlobalScopes()
                  ->where(function ($sub) use ($search) {
                      $sub->where('full_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        // 4. ترتيب تنازلي (الأحدث أولاً) مع الـ Pagination — per_page يحدده الأدمن أو افتراضي 15
        $perPage = isset($filters['per_page']) && is_numeric($filters['per_page'])
            ? (int) min($filters['per_page'], 100)  // حد أقصى 100 لمنع استنزاف الذاكرة
            : 15;

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }
   

    /**
     * 2. جلب التفاصيل العميقة لسائق معين لمراجعته من قبل الإدارة
     */
   /**
     * جلب تفاصيل سائق محدد بالكامل دون أي حجب صامت أو قيود معالجة الوثائق
     */
    public function getDriverDetails(int $id): Driver
    {
        // 1. كسر حجب السائق بالكامل أولاً بشكل منفصل
        $driver = Driver::withoutGlobalScopes()->where('id', $id)->first();

        if (!$driver) {
            throw new \Exception("عذراً، السائق المطلوب غير موجود في النظام نهائياً.");
        }

        // 2. تحميل الحساب الشخصي بشكل آمن ومحمي من الحجب الصامت
        $driver->load([
            'user' => function($q) {
                $q->withTrashed()->withoutGlobalScopes(); 
            }
        ]);

        // 3. تحميل المركبات بشكل آمن وتخطي القيود
        if (method_exists($driver, 'vehicles')) {
            $driver->load(['vehicles' => function($q) {
                $q->withoutGlobalScopes();
            }]);
        }

        // 4. تحميل الوثائق بشكل آمن مع فك حجب القيود الصامتة
        if (method_exists($driver, 'documents')) {
            $driver->load(['documents' => function($q) {
                $q->withoutGlobalScopes();
            }]);

            // 🚀 هندسة ذكية: إصلاح مشكلة الـ null في الحقول والروابط مباشرة هنا لضمان قيم حقيقية وروابط كاملة
            $driver->documents->transform(function ($doc) {
                $doc->document_type = $doc->doc_type ?? $doc->document_type;
                $doc->document_url = $doc->file_url ? url($doc->file_url) : ($doc->document_url ?? null);
                return $doc;
            });
        }

        return $driver;
    }

    /**
     * 3. معالجة طلب السائق (تفعيل وقبول أو رفض مسبب) للإنشاء أول مرة
     */
    public function reviewDriverRequest(int $driverId, array $data, int $adminId): Driver
    {
        return DB::transaction(function () use ($driverId, $data, $adminId) {
            
            $driver = Driver::with('user')->lockForUpdate()->findOrFail($driverId);
            
            $status = $data['status']; // Approved أو Rejected
            $rejectionReason = $data['rejection_reason'] ?? null;

            $driver->update([
                'status' => $status
            ]);

            if ($status === 'Approved') {
                $driver->user->update([
                    'is_active' => true
                ]);
            }

            DriverApproval::create([
                'driver_id'        => $driver->id,
                'admin_id'         => $adminId,
                'status'           => $status,
                'rejection_reason' => $rejectionReason,
                'created_at'       => now()
            ]);

            // 📝 تسجيل إجراء القرار في سجل تدقيق المشرفين
            $this->auditLogService->record(
                action: $status === 'Approved' ? 'approve_driver' : 'reject_driver',
                entityType: 'driver',
                entityId: $driver->id,
                entityName: $driver->user->full_name,
                result: $status === 'Approved' ? 'approved' : 'rejected',
                reason: $rejectionReason,
                changes: [],
                adminId: $adminId
            );

            $this->emailService->sendDriverReviewResult(
                $driver->user->email,
                $driver->user->full_name,
                $status,
                $rejectionReason,
                $driver->gender
            );

            return $driver;
        });
    }

    /**
     * 3-ب. تعديل بيانات السائق مباشرة من قبل المشرف / الأدمن مع التوثيق في سجل التدقيق
     */
    public function updateDriver(int $driverId, array $data, ?int $adminId = null): Driver
    {
        return DB::transaction(function () use ($driverId, $data, $adminId) {
            $driver = Driver::with('user')->lockForUpdate()->findOrFail($driverId);
            $user = $driver->user;

            // أخذ لقطة من القيم القديمة لحساب الفروقات
            $oldSnapshot = [
                'full_name'      => $user->full_name,
                'phone_number'   => $user->phone_number,
                'is_active'      => (bool) $user->is_active,
                'national_id'    => $driver->national_id,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry ? \Carbon\Carbon::parse($driver->license_expiry)->format('Y-m-d') : null,
                'status'         => $driver->status,
            ];

            // تحضير بيانات تحديث المستخدم
            $userUpdates = [];
            if (isset($data['full_name'])) {
                $userUpdates['full_name'] = $data['full_name'];
            }
            if (isset($data['phone_number'])) {
                $userUpdates['phone_number'] = $data['phone_number'];
            }
            if (isset($data['is_active'])) {
                $userUpdates['is_active'] = (bool) $data['is_active'];
            }

            if (!empty($userUpdates)) {
                $user->update($userUpdates);
            }

            // تحضير بيانات تحديث السائق
            $driverUpdates = [];
            if (isset($data['national_id'])) {
                $driverUpdates['national_id'] = $data['national_id'];
            }
            if (isset($data['license_number'])) {
                $driverUpdates['license_number'] = $data['license_number'];
            }
            if (isset($data['license_expiry'])) {
                $driverUpdates['license_expiry'] = $data['license_expiry'];
            }
            if (isset($data['status'])) {
                $driverUpdates['status'] = ucfirst(strtolower($data['status']));
                if ($driverUpdates['status'] === 'Active') {
                    $driverUpdates['status'] = 'Approved';
                }
            }

            if (!empty($driverUpdates)) {
                $driver->update($driverUpdates);
            }

            $driver->refresh()->load('user');

            // لقطة القيم الجديدة
            $newSnapshot = [
                'full_name'      => $driver->user->full_name,
                'phone_number'   => $driver->user->phone_number,
                'is_active'      => (bool) $driver->user->is_active,
                'national_id'    => $driver->national_id,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry ? \Carbon\Carbon::parse($driver->license_expiry)->format('Y-m-d') : null,
                'status'         => $driver->status,
            ];

            // حساب التغييرات مع التسميات العربية
            $changes = $this->auditLogService->diff($oldSnapshot, $newSnapshot);

            $reason = $data['reason'] ?? 'تعديل بيانات السائق من قبل الإدارة';

            // تسجيل العملية في سجل التدقيق
            $this->auditLogService->record(
                action: 'update_driver',
                entityType: 'driver',
                entityId: $driver->id,
                entityName: $driver->user->full_name,
                result: null,
                reason: $reason,
                changes: $changes,
                adminId: $adminId
            );

            return $driver;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 🚀 الدوال المضافة الخاصة بإدارة التعديلات اللاحقة
    |--------------------------------------------------------------------------
    |*/

    /**
     * 4. عرض كافة طلبات التعديلات المعلقة بانتظار موافقة الآدمن
     */
    public function getPendingChangesList(): LengthAwarePaginator
    {
        return DB::table('driver_profile_changes')
            ->join('drivers', 'driver_profile_changes.driver_id', '=', 'drivers.id')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->select(
                'driver_profile_changes.*', 
                'users.full_name as driver_name', 
                'users.phone_number as driver_phone'
            )
            ->where('driver_profile_changes.status', 'Pending')
            ->orderBy('driver_profile_changes.created_at', 'asc')
            ->paginate(15);
    }

    /**
     * 5. عرض تفصيلي لطلب تعديل محدد
     */
    public function getPendingChangeDetails(int $changeId): object
    {
        $change = DB::table('driver_profile_changes')->where('id', $changeId)->first();
        
        if (!$change) {
            throw new Exception('طلب التعديل هذا غير موجود أو تم معالجته مسبقاً.');
        }

        // جلب السائق الحالي للمقارنة
        $change->driver = Driver::with(['user', 'vehicles'])->find($change->driver_id);
        $change->new_values_decoded = json_decode($change->new_values, true);

        return $change;
    }

    /**
     * 6. الموافقة أو الرفض على طلب التعديل المعلق مع تطبيق التغييرات وإرسال إشعار داخلي فوراً
     */
    public function reviewProfileChangeRequest(int $changeId, string $decision, ?string $rejectionReason, int $adminId): bool
    {
        return DB::transaction(function () use ($changeId, $decision, $rejectionReason, $adminId) {
            
            // جلب سجل التعديل وقفله
            $change = DB::table('driver_profile_changes')->lockForUpdate()->where('id', $changeId)->first();
            
            if (!$change || $change->status !== 'Pending') {
                throw new Exception('لا يمكن معالجة هذا الطلب، قد يكون مقبولاً أو مرفوضاً مسبقاً.');
            }

            $driver = Driver::with('user')->findOrFail($change->driver_id);

            if ($decision === 'Approved') {
                $newValues = json_decode($change->new_values, true);

                // أ) فرز وتحديث حقول جدول الـ users إن وجدت
                $userFields = ['full_name', 'phone_number', 'alternative_phone', 'avatar_url'];
                $userDataToUpdate = array_intersect_key($newValues, array_flip($userFields));
                if (!empty($userDataToUpdate)) {
                    $driver->user->update($userDataToUpdate);
                }

                // ب) فرز وتحديث حقول جدول الـ drivers الحساسة
                $driverFields = ['national_id', 'license_number', 'license_expiry'];
                $driverDataToUpdate = array_intersect_key($newValues, array_flip($driverFields));
                if (!empty($driverDataToUpdate)) {
                    $driver->update($driverDataToUpdate);
                }

                // ج) فرز وتحديث بيانات المركبة المرتبطة إن وجدت
                $vehicleFields = ['plate_number', 'brand', 'model', 'year', 'color', 'type', 'capacity_manual', 'vehicle_image_path', 'has_ac'];
                $vehicleDataToUpdate = array_intersect_key($newValues, array_flip($vehicleFields));
                if (!empty($vehicleDataToUpdate)) {
                    // تحديث السيارة الحالية النشطة للسائق
                    $driver->vehicles()->where('is_verified', true)->update($vehicleDataToUpdate);
                }

                // د) تحديث حالة الطلب إلى Approved
                DB::table('driver_profile_changes')->where('id', $changeId)->update([
                    'status'    => 'Approved',
                    'action_at' => now()
                ]);

                // 🚀 هـ) إرسال إشعار القبول عبر NotificationService (database + push)
                try {
                    $this->notificationService->sendToUser($driver->user, 'driver_account_approved', [
                        'title'       => '🎉 تم قبول تحديث بياناتك',
                        'message'     => 'مرحباً بك كابتن، تمت الموافقة على تعديل بيانات ملفك الشخصي وتطبيقها بنجاح.',
                        'entity_type' => 'driver_profile_change',
                        'entity_id'   => (string) $changeId,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Failed sending approval notification: " . $e->getMessage());
                }

            } else {
                // أ) في حال الرفض: تحديث حالة الطلب وإثبات سبب الرفض
                DB::table('driver_profile_changes')->where('id', $changeId)->update([
                    'status'           => 'Rejected',
                    'rejection_reason' => $rejectionReason,
                    'action_at'        => now()
                ]);

                // 🚀 ب) إرسال إشعار الرفض عبر NotificationService (database + push)
                try {
                    $this->notificationService->sendToUser($driver->user, 'driver_account_rejected', [
                        'title'       => '📋 مراجعة تحديث البيانات',
                        'message'     => "نأسف لإبلاغك برفض طلب تعديل البيانات المرفق بملفك الشخصي بسبب: {$rejectionReason}",
                        'entity_type' => 'driver_profile_change',
                        'entity_id'   => (string) $changeId,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Failed sending rejection notification: " . $e->getMessage());
                }
            }

            // 📝 تسجيل إجراء مراجعة تعديل بيانات السائق في سجل تدقيق المشرفين
            $this->auditLogService->record(
                action: $decision === 'Approved' ? 'approve_driver_change' : 'reject_driver_change',
                entityType: 'driver_change',
                entityId: $changeId,
                entityName: $driver->user->full_name,
                result: $decision === 'Approved' ? 'approved' : 'rejected',
                reason: $rejectionReason,
                changes: $decision === 'Approved' ? $this->auditLogService->diff([], $newValues ?? []) : [],
                adminId: $adminId
            );

            return true;
        });
    }
}