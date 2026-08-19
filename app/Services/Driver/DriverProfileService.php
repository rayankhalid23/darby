<?php

namespace App\Services\Driver;

use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Models\Driver\DriverApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use App\Services\Shared\EmailService;
use Exception;

class DriverProfileService
{
    protected EmailService $emailService;

    // حقن خدمة الإيميل للتعامل مع روابط التحقق الآمنة
    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * تحديث بيانات السائق الفورية وتجميد الحساسة للمراجعة
     */
    public function updateDriverProfile(int $userId, array $data): array
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            $driver = $user->driver;

            if (!$driver) {
                throw new Exception("لم يتم العثور على ملف السائق الخاص بهذا المستخدم.");
            }

            // الحقول التي يتم تحديثها فوراً وبشكل مباشر دون موافقة الأدمن
            $userUpdateData = [];

            // هندسة معالجة ورفع ملف الصورة الشخصية فوراً إن وُجدت
            if (request()->hasFile('avatar_url')) {
                // إزالة الملف القديم من السيرفر للحفاظ على المساحة إن وُجد
                if (!empty($user->avatar_url)) {
                    $oldPath = str_replace('storage/', '', $user->avatar_url);
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
                }

                // رفع وتخزين الصورة الجديدة داخل مجلد drivers/avatars على القرص العام public
                $path = request()->file('avatar_url')->store('drivers/avatars', 'public');
                $userUpdateData['avatar_url'] = 'storage/' . $path;
            }

            if (array_key_exists('alternative_phone', $data)) {
                $userUpdateData['alternative_phone'] = $data['alternative_phone'];
            }
            
            if (!empty($data['password'])) {
                $userUpdateData['password_hash'] = Hash::make($data['password']);
            }

            if (!empty($userUpdateData)) {
                $user->update($userUpdateData);
            }

            // الحقول الحساسة التي تتطلب موافقة الأدمن (الاسم والهاتف الأساسي والبريد الإلكتروني)
            $pendingChanges = [];
            $oldValues = [];

            if (isset($data['full_name']) && $data['full_name'] !== $user->full_name) {
                $pendingChanges['full_name'] = $data['full_name'];
                $oldValues['full_name'] = $user->full_name;
            }
            if (isset($data['phone_number']) && $data['phone_number'] !== $user->phone_number) {
                $pendingChanges['phone_number'] = $data['phone_number'];
                $oldValues['phone_number'] = $user->phone_number;
            }

            $requiresApproval = false;

            // هندسة حماية الحساب: التعديل الآمن لبريد السائق الإلكتروني بدون إضافة أعمدة
            if (!empty($data['email']) && strtolower($data['email']) !== strtolower($user->email)) {
                if (User::where('email', $data['email'])->where('id', '!=', $userId)->exists()) {
                    throw new Exception("عذراً، البريد الإلكتروني الجديد مستخدم بالفعل في حساب آخر.");
                }

                $pendingChanges['email'] = $data['email'];
                $oldValues['email'] = $user->email;
                $requiresApproval = true;

                \Illuminate\Support\Facades\Cache::put("driver_email_change_{$user->id}", $data['email'], now()->addMinutes(30));
                \Illuminate\Support\Facades\Cache::put("driver_email_change_status_{$user->id}", 'pending', now()->addMinutes(30));

                // تمرير الإيميل الجديد داخل الروابط الموقّعة لقراءته عند الضغط عليه
                $approveUrl = URL::temporarySignedRoute('api.driver.email.approve', now()->addMinutes(30), [
                    'id' => $user->id,
                    'new_email' => $data['email']
                ]);
                $rejectUrl  = URL::temporarySignedRoute('api.driver.email.reject', now()->addMinutes(30), [
                    'id' => $user->id
                ]);

                $this->emailService->sendDriverEmailChangeLink(
                    $data['email'], 
                    $user->full_name, 
                    $approveUrl, 
                    $rejectUrl, 
                    $driver->gender
                );    
            }

            // إذا كان هناك أي تعديلات معلقة (اسم، هاتف، أو بريد إلكتروني)
            if (!empty($pendingChanges)) {
                $requiresApproval = true;

                DB::table('driver_profile_changes')->insert([
                    'driver_id'  => $driver->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($pendingChanges),
                    'status'     => 'Pending',
                    'created_at' => now()
                ]);
            }

            if (array_key_exists('gender', $data)) {
                $driver->update(['gender' => $data['gender']]);
            }

            return [
                'driver'            => $driver->fresh(['user']),
                'requires_approval' => $requiresApproval,
                'is_email_changed'  => !empty($pendingChanges['email']),
                'pending_email'     => $pendingChanges['email'] ?? null,
                'message'           => $requiresApproval 
                    ? "تم تحديث البيانات الفورية، وباقي التعديلات الحساسة بانتظار الاعتماد/التأكيد." 
                    : "تم تحديث الملف الشخصي بنجاح."
            ];
        });
    }

    /**
     * دالة اعتماد البريد الجديد للسائق عبر الرابط الموقّع المفتوح من الإيميل
     */
    public function approveEmailChange(int $userId): bool
    {
        return DB::transaction(function () use ($userId) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            
            $newEmail = request()->query('new_email') ?? \Illuminate\Support\Facades\Cache::get("driver_email_change_{$userId}");

            if (empty($newEmail)) {
                \Illuminate\Support\Facades\Cache::put("driver_email_change_status_{$userId}", 'expired', now()->addMinutes(15));
                throw new Exception("رابط التأكيد غير صالح أو لا يحتوي على البريد الجديد.");
            }

            $user->email = $newEmail;
            $user->save();

            \Illuminate\Support\Facades\Cache::forget("driver_email_change_{$userId}");
            \Illuminate\Support\Facades\Cache::put("driver_email_change_status_{$userId}", 'verified', now()->addMinutes(15));

            return true;
        });
    }

    /**
     * دالة إلغاء طلب تعديل البريد من الرابط
     */
    public function rejectEmailChange(int $userId): bool
    {
        \Illuminate\Support\Facades\Cache::forget("driver_email_change_{$userId}");
        \Illuminate\Support\Facades\Cache::put("driver_email_change_status_{$userId}", 'rejected', now()->addMinutes(15));
        return true;
    }

    public function cancelEmailChange(int $userId): bool
    {
        \Illuminate\Support\Facades\Cache::forget("driver_email_change_{$userId}");
        \Illuminate\Support\Facades\Cache::forget("driver_email_change_status_{$userId}");
        return true;
    }

    public function resendEmailChange(int $userId): bool
    {
        $cacheKey = "driver_email_change_{$userId}";
        if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            throw new Exception("لا يوجد طلب معلق لتعديل البريد الإلكتروني لإعادة إرساله.");
        }

        $newEmail = \Illuminate\Support\Facades\Cache::get($cacheKey);
        $user = User::findOrFail($userId);
        $driver = $user->driver;

        $approveUrl = URL::temporarySignedRoute('api.driver.email.approve', now()->addMinutes(30), [
            'id' => $user->id,
            'new_email' => $newEmail
        ]);
        $rejectUrl  = URL::temporarySignedRoute('api.driver.email.reject', now()->addMinutes(30), [
            'id' => $user->id
        ]);

        $this->emailService->sendDriverEmailChangeLink(
            $newEmail, 
            $user->full_name, 
            $approveUrl, 
            $rejectUrl, 
            $driver?->gender
        );

        return true;
    }

    /**
     * جلب حالة اعتماد حساب السائق الحالية (للاستخدام أثناء انتظار مراجعة الإدارة)
     */
    public function getDriverStatus(int $userId): array
    {
        $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
        $driver = $user->driver;

        if (!$driver) {
            throw new Exception("لم يتم العثور على ملف السائق.");
        }

        $rejectionReason = null;
        if ($driver->status === 'Rejected') {
            $latestApproval = DriverApproval::where('driver_id', $driver->id)
                ->latest('created_at')
                ->first();
            $rejectionReason = $latestApproval->rejection_reason ?? null;
        }

        return [
            'is_active'        => (bool) $user->is_active,
            'driver_status'    => $driver->status,
            'rejection_reason' => $rejectionReason,
        ];
    }

    /**
     * تحديث البيانات القانونية والوثائق الرسمية للسائق
     */
    public function updateLegalDocuments(int $userId, array $data): array
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            $driver = $user->driver;

            if (!$driver) {
                throw new Exception("لم يتم العثور على ملف السائق.");
            }

            $driverUpdate = [];
            foreach (['national_id', 'license_number', 'license_expiry'] as $field) {
                if (array_key_exists($field, $data)) {
                    $driverUpdate[$field] = $data[$field];
                }
            }

            if (array_key_exists('license_expiry', $data)) {
                // تجديد تاريخ انتهاء الرخصة يُعيد ضبط عدّاد التذكيرات ويُلغي علامة "منتهية" إن وُجدت
                $driverUpdate['license_expiry_notified_milestone'] = null;

                DriverDocument::where('driver_id', $driver->id)
                    ->where('doc_type', 'LICENSE')
                    ->where('status', 'Expired')
                    ->update(['status' => 'Pending']);
            }

            if (!empty($driverUpdate)) {
                $driver->update($driverUpdate);
            }

            $docMap = [
                'doc_license_path'   => 'LICENSE',
                'doc_logbook_path'   => 'VEHICLE_LOGBOOK',
                'doc_insurance_path' => 'INSURANCE',
            ];

            foreach ($docMap as $pathKey => $docType) {
                if (!empty($data[$pathKey])) {
                    $updateFields = [
                        'file_url'    => $data[$pathKey],
                        'status'      => 'Pending',
                        'uploaded_at' => now(),
                    ];

                    if ($docType === 'INSURANCE') {
                        // رفع صورة تأمين جديدة يُعيد ضبط عدّاد تذكيرات هذه الوثيقة تحديداً
                        $updateFields['expiry_notified_milestone'] = null;
                        if (array_key_exists('insurance_expiry', $data)) {
                            $updateFields['insurance_expiry_date'] = $data['insurance_expiry'];
                        }
                    }

                    DriverDocument::updateOrCreate(
                        ['driver_id' => $driver->id, 'doc_type' => $docType],
                        $updateFields
                    );
                }
            }

            // السماح بتحديث تاريخ انتهاء التأمين بشكل مستقل دون رفع صورة جديدة
            if (array_key_exists('insurance_expiry', $data) && empty($data['doc_insurance_path'])) {
                DriverDocument::where('driver_id', $driver->id)
                    ->where('doc_type', 'INSURANCE')
                    ->update([
                        'insurance_expiry_date'     => $data['insurance_expiry'],
                        'expiry_notified_milestone' => null,
                        'status'                    => 'Pending',
                    ]);
            }

            return [
                'message' => 'تم تحديث البيانات القانونية والوثائق بنجاح، وهي بانتظار مراجعة الإدارة.'
            ];
        });
    }

    /**
     * تحديث بيانات مركبة محددة مع التحقق من ملكيتها للسائق
     */
    public function updateVehicleDetails(int $userId, int $vehicleId, array $data): Vehicle
    {
        return DB::transaction(function () use ($userId, $vehicleId, $data) {
            $driver = Driver::where('user_id', $userId)->first();

            if (!$driver) {
                throw new Exception("لم يتم العثور على ملف السائق.");
            }

            $vehicle = Vehicle::where('driver_id', $driver->id)->where('id', $vehicleId)->first();

            if (!$vehicle) {
                throw new Exception("المركبة غير موجودة أو لا تخص هذا السائق.");
            }

            $updateData = [];
            foreach (['has_ac', 'plate_number', 'brand', 'model', 'year', 'color', 'type', 'capacity_manual'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (array_key_exists('vehicle_image_path', $data)) {
                $updateData['vehicle_image_url'] = $data['vehicle_image_path'];
            }

            if (!empty($updateData)) {
                $vehicle->update($updateData);
            }

            return $vehicle->fresh();
        });
    }

   /**
     * إرجاع قائمة مختصرة لجميع سيارات السائق
     */
   public function getDriverVehiclesSummary(int $userId)
   {
       // 1. جلب سجل السائق المرتبط بهذا المستخدم
       $driver = Driver::where('user_id', $userId)->first();

       // في حال لم يتم العثور على ملف سائق لهذا المستخدم
       if (!$driver) {
           return [];
       }

       // 2. البحث برقم السائق ($driver->id) الصحيح بدلاً من ($userId)
       return Vehicle::where('driver_id', $driver->id)
           ->select(['id', 'brand', 'model', 'vehicle_image_url'])
           ->get()
           ->map(function ($vehicle) {
               return [
                   'id'        => $vehicle->id,
                   'name'      => $vehicle->brand,
                   'model'     => $vehicle->model,
                   'image_url' => $vehicle->vehicle_image_url ? asset($vehicle->vehicle_image_url) : null,
               ];
           });
   }

   /**
     * جلب تفاصيل سيارة محددة مع التأكد من ملكيتها للسائق الحالي
     */
   public function getVehicleDetails(int $userId, int $vehicleId)
   {
       // 1. جلب ملف السائق أولاً للحصول على driver_id الصحيح
       $driver = Driver::where('user_id', $userId)->first();

       if (!$driver) {
           return null;
       }

       // 2. البحث برقم السائق ($driver->id) بدلاً من رقم المستخدم ($userId)
       $vehicle = Vehicle::where('driver_id', $driver->id)
           ->where('id', $vehicleId)
           ->first();

       if (!$vehicle) {
           return null;
       }

       // 3. تحويل مسار الصورة إلى URL كامل
       $vehicle->vehicle_image_url = $vehicle->vehicle_image_url ? asset($vehicle->vehicle_image_url) : null;

       return $vehicle;
   }
}