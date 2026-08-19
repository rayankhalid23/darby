<?php

namespace App\Services\Driver;

use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Services\Shared\EmailService;
use App\Services\Shared\OtpService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DriverRegisterService
{
    protected EmailService $emailService;
    protected OtpService $otpService;
    protected NotificationService $notificationService;

    public function __construct(EmailService $emailService, OtpService $otpService, NotificationService $notificationService)
    {
        $this->emailService = $emailService;
        $this->otpService = $otpService;
        $this->notificationService = $notificationService;
    }

    /**
     * الخطوة 1: إرسال الـ OTP قبل إنشاء الحساب لحماية قاعدة البيانات من السجلات الوهمية
     */
    public function sendVerificationOtp(array $data): bool
    {
        // توليد الرمز وربطه بالبريد الإلكتروني في الـ Cache أو جدول الـ OTP المؤقت
        $otpCode = $this->otpService->generate($data['email'], 'REGISTER');

        // إرسال البريد
        $this->emailService->sendOtp(
            $data['email'], 
            $data['full_name'], 
            $otpCode, 
            4, // role_id الخاص بالسائق
            $data['gender']
        );

        return true;
    }

    /**
     * الخطوة 2: يتم استدعاء هذه الدالة فقط "بعد" نجاح التحقق من الـ OTP في الـ Controller
     */
    public function registerAccountAfterOtp(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // 1. إنشاء المستخدم وتفعيله مباشرة لأن الـ OTP تم التحقق منه
            $user = User::create([
                'full_name'         => $data['full_name'],
                'email'             => $data['email'],
                'phone_number'      => $data['phone_number'],
                'alternative_phone' => $data['alternative_phone'] ?? null,
                'password_hash'     => Hash::make($data['password']),
                'role_id'           => 4,
                'is_active'         => 0, // مفعل مباشرة
            ]);

            // 2. إنشاء ملف السائق الأساسي
            Driver::create([
                'user_id' => $user->id,
                'gender'  => $data['gender'],
                'status'  => 'Offline',
            ]);

            // 3. تسجيل جهاز السائق فقط إذا تم إرسال fcm_token حقيقي (fcm_token فريد عالمياً في الجدول،
            //    ولا معنى لإدراج توكن وهمي؛ التسجيل الرسمي للجهاز يتم عبر POST /api/user/device-token بعد تسجيل الدخول)
            if (!empty($data['fcm_token'])) {
                DB::table('user_devices')->updateOrInsert(
                    ['fcm_token' => $data['fcm_token']],
                    [
                        'user_id'        => $user->id,
                        'device_id'      => $data['device_id'] ?? null,
                        'device_name'    => $data['device_name'] ?? 'mobile_device',
                        'platform'       => $data['platform'] ?? 'unknown',
                        'is_active'      => true,
                        'last_active_at' => Carbon::now(),
                        'created_at'     => Carbon::now(),
                        'updated_at'     => Carbon::now(),
                    ]
                );
            }

            return $user;
        });
    }

    /**
     * المرحلة الثانية: إكمال بيانات السائق (المركبة والوثائق) كما هي
     */
    public function completeProfile(int $userId, array $data): Driver
    {
        return DB::transaction(function () use ($userId, $data) {
            try {
                $user = User::findOrFail($userId);
                $driver = $user->driver;

                if (!$driver) {
                    throw new Exception("لم يتم العثور على ملف السائق.");
                }

                // 1. تحديث بيانات السائق
                $driver->update([
                    'national_id'    => $data['national_id'],
                    'license_number' => $data['license_number'],
                    'license_expiry' => $data['license_expiry'],
                    'status'         => 'Pending',
                ]);

                // 2. إنشاء المركبة
                $vehicle = Vehicle::create([
                    'driver_id'         => $driver->id,
                    'plate_number'      => $data['plate_number'],
                    'brand'             => $data['brand'],
                    'model'             => $data['model'],
                    'year'              => $data['year'],
                    'color'             => $data['color'],
                    'type'              => $data['type'],
                    'capacity_manual'   => $data['capacity_manual'],
                    'vehicle_image_url' => $data['vehicle_image_path'],
                    'has_ac'            => $data['has_ac'],
                    'status'            => 'Pending',
                    'is_verified'       => 0
                ]);

                // 3. إدخال المستندات
                $documents = [
                    'LICENSE'         => $data['doc_license_path'],
                    'VEHICLE_LOGBOOK' => $data['doc_logbook_path'],
                    'INSURANCE'       => $data['doc_insurance_path'],
                    'CRIMINAL_RECORD' => $data['doc_criminal_record_path'],
                ];

                foreach ($documents as $type => $path) {
                    DriverDocument::create([
                        'driver_id'   => $driver->id,
                        'vehicle_id'  => $vehicle->id,
                        'doc_type'    => $type,
                        'file_url'    => $path,
                        'status'      => 'Pending',
                        'uploaded_at' => now(),
                    ]);
                }

                try {
                    $admins = \App\Models\User::whereIn('role_id', [1, 2])->get();
                    // withPush: false — إشعارات الأدمن عبر DB + polling فقط، بلا Firebase Push.
                    $this->notificationService->sendToUsers($admins, 'new_driver_registered', [
                        'title'       => 'تسجيل سائق جديد 🚐',
                        'message'     => "قام السائق ({$user->full_name}) بإكمال بياناته وبانتظار المراجعة.",
                        'driver_name' => $user->full_name,
                        'entity_id'   => (string) $driver->id,
                    ], withPush: false);
                } catch (\Throwable $e) {
                    Log::warning("فشل إرسال إشعار الأدمن عن تسجيل السائق الجديد #{$driver->id}: " . $e->getMessage());
                }

                // الكود الجديد البديل لمنع استعلام الحذف الناعم المنهار
return $driver->load([
    'user',
    'vehicles' => function($query) {
        $query->withoutGlobalScopes(); // 🚀 تخطي أي شروط حذف ناعم مبرمجة تلقائياً
    },
    'documents'
]);

            } catch (Exception $e) {
                Log::error("Error completing driver profile: " . $e->getMessage());
                throw $e;
            }
        });
    }
}