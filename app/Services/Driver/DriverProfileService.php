<?php

namespace App\Services\Driver;

use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
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
                'message'           => $requiresApproval 
                    ? "تم تحديث البيانات الفورية، وباقي التعديلات الحساسة بانتظار الاعتماد/التأكيد." 
                    : "تم تحديث الملف الشخصي بنجاح."
            ];
        });
    }

   
    /**
     * [ 2 ] تحديث وتجديد البيانات القانونية والوثائق الرسمية للسائق
     */
    public function updateLegalDocuments(int $userId, array $data): array
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            $driver = $user->driver;

            if (!$driver) {
                throw new Exception("لم يتم العثور على ملف السائق.");
            }

            $newLegalData = [
                'national_id'    => $data['national_id'],
                'license_number' => $data['license_number'],
                'license_expiry' => $data['license_expiry'],
            ];

            $oldLegalData = [
                'national_id'    => $driver->national_id,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry,
            ];

            DB::table('driver_profile_changes')->insert([
                'driver_id'  => $driver->id,
                'old_values' => json_encode($oldLegalData),
                'new_values' => json_encode($newLegalData),
                'status'     => 'Pending',
                'created_at' => now()
            ]);

            $documents = [
                'LICENSE'         => $data['doc_license_path'],
                'VEHICLE_LOGBOOK' => $data['doc_logbook_path'],
                'INSURANCE'       => $data['doc_insurance_path'],
                'CRIMINAL_RECORD' => $data['doc_criminal_record_path'],
            ];

            $activeVehicle = $driver->vehicles()->where('status', 'Active')->first() 
                ?? $driver->vehicles()->latest()->first();

            foreach ($documents as $type => $path) {
                DriverDocument::create([
                    'driver_id'   => $driver->id,
                    'vehicle_id'  => $activeVehicle ? $activeVehicle->id : null,
                    'doc_type'    => $type,
                    'file_url'    => $path,
                    'status'      => 'Pending',
                    'uploaded_at' => now(),
                ]);
            }

            return [
                'status'  => 'success',
                'message' => 'تم رفع وثائق التجديد والبيانات القانونية بنجاح، وهي الآن تحت مراجعة الإدارة.'
            ];
        });
    }

    /**
     * [ 3 ] تحديث بيانات المركبة الحالية
     */
    public function updateVehicleDetails(int $userId, int $vehicleId, array $data): Vehicle
    {
        return DB::transaction(function () use ($userId, $vehicleId, $data) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            $driver = $user->driver;

            $vehicle = Vehicle::where('id', $vehicleId)->where('driver_id', $driver->id)->firstOrFail();

            if (isset($data['has_ac'])) {
                $vehicle->has_ac = $data['has_ac'];
            }

            $vehicle->plate_number    = $data['plate_number'] ?? $vehicle->plate_number;
            $vehicle->brand           = $data['brand'] ?? $vehicle->brand;
            $vehicle->model           = $data['model'] ?? $vehicle->model;
            $vehicle->year            = $data['year'] ?? $vehicle->year;
            $vehicle->color           = $data['color'] ?? $vehicle->color;
            $vehicle->type            = $data['type'] ?? $vehicle->type;
            $vehicle->capacity_manual = $data['capacity_manual'] ?? $vehicle->capacity_manual;
            
            if (isset($data['vehicle_image_path'])) {
                $vehicle->vehicle_image_url = $data['vehicle_image_path'];
            }

            $vehicle->status      = 'Pending';
            $vehicle->is_verified = 0;
            $vehicle->save();

            return $vehicle->fresh();
        });
    }

    /**
     * دالة اعتماد البريد الجديد للسائق عبر الرابط الموقّع المفتوح من الإيميل
     */
    public function approveEmailChange(int $userId): bool
    {
        return DB::transaction(function () use ($userId) {
            $user = User::where('id', $userId)->where('role_id', 4)->firstOrFail();
            
            // جلب الإيميل الجديد من معلمات الرابط الموقّع مباشرة
            $newEmail = request()->query('new_email');

            if (empty($newEmail)) {
                throw new Exception("رابط التأكيد غير صالح أو لا يحتوي على البريد الجديد.");
            }

            $user->email = $newEmail;
            return $user->save();
        });
    }

    /**
     * دالة إلغاء طلب تعديل البريد
     */
    public function rejectEmailChange(int $userId): bool
    {
        // بما أنه لا توجد أعمدة، الدالة تعود بـ true فقط لإظهار صفحة نجاح الإلغاء للسائق
        return true;
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