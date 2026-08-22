<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverDocument;
use App\Models\Driver\Vehicle;
use App\Services\Shared\OtpService;

class DriverFullRegistrationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);
    }

    /**
     * اختبار دورة حياة إنشاء حساب السائق ورفع الوثائق والمركبة كاملة
     */
    public function test_full_driver_registration_and_profile_completion_lifecycle(): void
    {
        Storage::fake('public');

        $email = 'driver.full.' . uniqid() . '@darby.test';
        $phone = '091' . rand(1000000, 9999999);
        $password = 'Password123';
        $fullName = 'سائق تجريبي كامل البيانات';

        // 1. طلب رمز OTP
        $registerRes = $this->postJson('/api/v1/driver/register', [
            'full_name'    => $fullName,
            'email'        => $email,
            'phone_number' => $phone,
            'gender'       => 'male',
            'password'     => $password,
        ]);
        $registerRes->assertStatus(200);
        $registerRes->assertJsonPath('status', true);

        // وضع كود OTP معروف ومطابق للتحقق في الاختبار
        DB::table('otp_codes')->where('email', $email)->where('purpose', 'REGISTER')->update([
            'code_hash'  => bcrypt('123456'),
            'expires_at' => now()->addMinutes(10),
            'is_used'    => 0,
        ]);
        $otpCode = '123456';

        // 2. التحقق من OTP وإنشاء الحساب
        $verifyRes = $this->postJson('/api/v1/driver/verify-otp', [
            'email'        => $email,
            'otp'          => $otpCode,
            'full_name'    => $fullName,
            'phone_number' => $phone,
            'gender'       => 'male',
            'password'     => $password,
        ]);
        $verifyRes->assertStatus(201);
        $verifyRes->assertJsonPath('status', true);
        $token = $verifyRes->json('token');
        $userId = $verifyRes->json('user_id');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($userId);

        $user = User::find($userId);
        $this->assertNotNull($user);
        $this->assertEquals(4, $user->role_id);
        $this->assertEquals(0, $user->is_active);

        $driver = Driver::where('user_id', $userId)->first();
        $this->assertNotNull($driver);
        $this->assertEquals('Offline', $driver->status);

        // 3. استكمال ملف السائق ورفع بيانات المركبة والوثائق (Complete Profile)
        $nationalId = (string) rand(100000000000, 999999999999);
        $completeRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/driver/complete-profile/{$userId}", [
                'national_id'      => $nationalId,
                'license_number'   => 'DL-554433',
                'license_expiry'   => now()->addYears(3)->format('Y-m-d'),
                'insurance_expiry' => now()->addYear()->format('Y-m-d'),
                'plate_number'     => '5-99887',
                'brand'            => 'Toyota',
                'model'            => 'Hiace',
                'year'             => 2023,
                'color'            => 'Silver',
                'type'             => 'Van',
                'capacity_manual'  => 14,
                'has_ac'           => true,
                'vehicle_image'    => UploadedFile::fake()->image('van.webp'),
                'doc_license'      => UploadedFile::fake()->image('license.png'),
                'doc_logbook'      => UploadedFile::fake()->create('logbook.pdf', 500, 'application/pdf'),
                'doc_insurance'    => UploadedFile::fake()->create('insurance.pdf', 500, 'application/pdf'),
            ]);

        $completeRes->assertStatus(200);
        $completeRes->assertJsonPath('status', true);
        $completeRes->assertJsonPath('message', 'تم رفع البيانات بنجاح، بانتظار مراجعة الإدارة.');

        // التحقق من تحديث حالة السائق والبيانات
        $driver->refresh();
        $this->assertEquals('Pending', $driver->status);
        $this->assertEquals($nationalId, $driver->national_id);
        $this->assertEquals('DL-554433', $driver->license_number);

        // التحقق من إنشاء المركبة
        $vehicle = Vehicle::where('driver_id', $driver->id)->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals('5-99887', $vehicle->plate_number);
        $this->assertEquals('Pending', $vehicle->status);
        $this->assertNotEmpty($vehicle->vehicle_image_url);

        // التحقق من إنشاء المستندات الثلاثة
        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $driver->id,
            'doc_type'  => 'LICENSE',
        ]);
        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $driver->id,
            'doc_type'  => 'VEHICLE_LOGBOOK',
        ]);
        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $driver->id,
            'doc_type'  => 'INSURANCE',
        ]);
    }

    /**
     * اختبار توافق مسميات الحقول وصيغ الصور المتعددة القادمة من الواجهة الأمامية (Flutter)
     */
    public function test_complete_profile_accepts_frontend_aliases_and_webp_pdf_formats(): void
    {
        Storage::fake('public');

        $user = User::create([
            'full_name'     => 'سائق تجريبي فرونت اند',
            'email'         => 'driver.frontend.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'gender'  => 'Male',
            'status'  => 'Offline',
        ]);

        // إرسال الحقول بمسميات الفرونت (vehicle_photo, license_photo, logbook_photo, insurance_photo, vehicle_type)
        $response = $this->actingAs($user)
            ->postJson("/api/v1/driver/complete-profile/{$user->id}", [
                'national_id'      => (string) rand(100000000000, 999999999999),
                'license_number'   => 'DL-991122',
                'license_expiry'   => now()->addYears(2)->format('Y-m-d'),
                'insurance_expiry' => now()->addYear()->format('Y-m-d'),
                'plate_number'     => '1-55443',
                'brand'            => 'Hyundai',
                'model'            => 'H1',
                'year'             => 2021,
                'color'            => 'Black',
                'vehicle_type'     => 'Van',
                'capacity_manual'  => 12,
                'has_ac'           => 1,
                'vehicle_photo'    => UploadedFile::fake()->image('front_van.jpg'),
                'license_photo'    => UploadedFile::fake()->image('front_license.webp'),
                'logbook_photo'    => UploadedFile::fake()->image('front_logbook.png'),
                'insurance_photo'  => UploadedFile::fake()->create('front_insurance.pdf', 300, 'application/pdf'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $driver->refresh();
        $this->assertEquals('Pending', $driver->status);
        $this->assertEquals(1, $driver->vehicles()->count());
        $this->assertEquals(3, $driver->documents()->count());
    }

    /**
     * اختبار منع إكمال ملف مستخدم آخر (403)
     */
    public function test_cannot_complete_profile_for_another_user(): void
    {
        Storage::fake('public');

        $userA = User::create([
            'full_name'     => 'سائق أ',
            'email'         => 'driverA.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $userB = User::create([
            'full_name'     => 'سائق ب',
            'email'         => 'driverB.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $response = $this->actingAs($userA)
            ->postJson("/api/v1/driver/complete-profile/{$userB->id}", [
                'national_id'      => '123456789012',
                'license_number'   => 'DL-1234',
                'license_expiry'   => now()->addYear()->format('Y-m-d'),
                'insurance_expiry' => now()->addYear()->format('Y-m-d'),
                'plate_number'     => '1-1234',
                'brand'            => 'Toyota',
                'model'            => 'Coaster',
                'year'             => 2022,
                'color'            => 'White',
                'type'             => 'Bus',
                'capacity_manual'  => 24,
                'has_ac'           => true,
                'vehicle_image'    => UploadedFile::fake()->image('bus.jpg'),
                'doc_license'      => UploadedFile::fake()->image('lic.jpg'),
                'doc_logbook'      => UploadedFile::fake()->image('log.jpg'),
                'doc_insurance'    => UploadedFile::fake()->image('ins.jpg'),
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'غير مصرح لك بإكمال ملف مستخدم آخر.');
    }

    /**
     * اختبار فشل التحقق عند إرسال رقم وطني غير صالح أو صيغة ملف غير مقبولة
     */
    public function test_complete_profile_validation_errors(): void
    {
        Storage::fake('public');

        $user = User::create([
            'full_name'     => 'سائق تجريبي فحص التحقق',
            'email'         => 'driver.val.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        Driver::create([
            'user_id' => $user->id,
            'gender'  => 'Male',
            'status'  => 'Offline',
        ]);

        // إرسال بيانات خاطئة: رقم وطني قصير، تاريخ منتهي، ملف exe غير مسموح
        $response = $this->actingAs($user)
            ->postJson("/api/v1/driver/complete-profile/{$user->id}", [
                'national_id'      => '12345', // أقل من 12 رقم
                'license_number'   => 'DL-1234',
                'license_expiry'   => now()->subYear()->format('Y-m-d'), // في الماضي
                'insurance_expiry' => now()->subDay()->format('Y-m-d'),  // في الماضي
                'plate_number'     => '1-1234',
                'brand'            => 'Toyota',
                'model'            => 'Coaster',
                'year'             => 1995, // أقل من 2000
                'color'            => 'White',
                'type'             => 'Bus',
                'capacity_manual'  => 0, // أقل من 1
                'has_ac'           => true,
                'vehicle_image'    => UploadedFile::fake()->create('malware.exe', 100),
                'doc_license'      => UploadedFile::fake()->image('lic.jpg'),
                'doc_logbook'      => UploadedFile::fake()->image('log.jpg'),
                'doc_insurance'    => UploadedFile::fake()->image('ins.jpg'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'national_id',
            'license_expiry',
            'insurance_expiry',
            'year',
            'capacity_manual',
            'vehicle_image',
        ]);
    }
}
