<?php

namespace App\Services\Driver;

use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Services\Shared\EmailService;
use App\Services\Shared\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DriverRegisterService
{
    protected EmailService $emailService;
    protected OtpService $otpService;

    public function __construct(EmailService $emailService, OtpService $otpService)
    {
        $this->emailService = $emailService;
        $this->otpService = $otpService;
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
                'is_active'         => 0, // معلّق — يتفعل فقط عند موافقة الأدمن
            ]);

            // 2. إنشاء ملف السائق الأساسي
            Driver::create([
                'user_id' => $user->id,
                'gender'  => $data['gender'],
                'status'  => 'Offline',
            ]);

            // 3. تسجيل تفاصيل جهاز السائق
            $deviceName = $data['device_name'] ?? 'mobile_device';
            DB::table('user_devices')->updateOrInsert(
                ['user_id' => $user->id, 'device_name' => $deviceName],
                [
                    'fcm_token'      => $data['fcm_token'] ?? 'mock_fcm_token',
                    'platform'       => $data['platform'] ?? 'unknown',
                    'last_active_at' => Carbon::now()
                ]
            );

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
                    'LICENSE'         => ['file_url' => $data['doc_license_path']],
                    'VEHICLE_LOGBOOK' => ['file_url' => $data['doc_logbook_path']],
                    'INSURANCE'       => [
                        'file_url'               => $data['doc_insurance_path'],
                        'insurance_expiry_date'   => $data['insurance_expiry'],
                    ],
                ];

                foreach ($documents as $type => $fields) {
                    DriverDocument::create(array_merge([
                        'driver_id'   => $driver->id,
                        'vehicle_id'  => $vehicle->id,
                        'doc_type'    => $type,
                        'status'      => 'Pending',
                        'uploaded_at' => now(),
                    ], $fields));
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