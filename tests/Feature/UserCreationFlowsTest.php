<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Models\Shared\OtpCode;
use Carbon\Carbon;

/**
 * 🧪 اختبار شامل لدورات إنشاء وتسجيل: المشرف، ولي الأمر، والسائق
 * والتحقق من كافة القيود الإجبارية والاختيارية والرسائل المنطقية وقواعد البيانات
 */
class UserCreationFlowsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // إنشاء مدير نظام لتنفيذ عمليات إضافة المشرفين
        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام التنفيذي',
            'email'         => 'superadmin.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    // =========================================================================
    // 1️⃣ اختبارات إنشاء المشرف (Supervisor Creation) — POST /api/admin/admins
    // =========================================================================

    /** 1.1: فشل عند ترك الحقول الإجبارية فارغة */
    public function test_supervisor_creation_fails_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', []);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonValidationErrors(['full_name', 'email', 'phone_number']);
        
        $errors = $response->json('errors');
        $this->assertStringContainsString('الاسم الكامل مطلوب', $errors['full_name'][0]);
        $this->assertStringContainsString('البريد الإلكتروني', $errors['email'][0]);
        $this->assertStringContainsString('رقم الهاتف مطلوب', $errors['phone_number'][0]);
    }

    /** 1.2: فشل عند إدخال اسم أقل من 3 مقاطع */
    public function test_supervisor_creation_fails_with_short_name_less_than_3_words(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'أحمد محمود', // كلمتان فقط
            'email'        => 'sup.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['full_name']);
        $this->assertStringContainsString('الاسم الثلاثي', $response->json('errors.full_name.0'));
    }

    /** 1.3: فشل عند إدخال رقم هاتف لا يبدأ بـ 09 أو ليس 10 أرقام */
    public function test_supervisor_creation_fails_with_invalid_phone_format(): void
    {
        // رقم 9 أرقام فقط ولا يبدأ بـ 09
        $response = $this->actingAs($this->adminUser)->postJson('/api/admin/admins', [
            'full_name'    => 'أحمد محمود الفرجاني',
            'email'        => 'sup.' . uniqid() . '@darby.test',
            'phone_number' => '021123456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone_number']);
    }

    /** 1.4: نجاح إضافة مشرف مع كافة الحقول الإجبارية والاختيارية (مع صورة وكلمة مرور) */
    public function test_supervisor_creation_success_with_all_fields(): void
    {
        Storage::fake('public');

        $email = 'supervisor.full.' . uniqid() . '@darby.test';
        $phone = '091' . rand(1000000, 9999999);

        $response = $this->actingAs($this->adminUser)->post('/api/admin/admins', [
            'full_name'    => 'خالد عبد السلام الورفلي',
            'email'        => $email,
            'phone_number' => $phone,
            'password'     => 'SuperSecret123',
            'avatar'       => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            'is_active'    => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.full_name', 'خالد عبد السلام الورفلي');
        $response->assertJsonPath('data.role_name', DB::table('roles')->where('id', 2)->value('display_name'));

        // التحقق من قاعدة البيانات
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals(2, $user->role_id);
        $this->assertNotNull(Admin::where('user_id', $user->id)->first());
    }

    // =========================================================================
    // 2️⃣ اختبارات إنشاء حساب ولي الأمر (Parent Creation) — Step 1 & Step 2
    // =========================================================================

    /** 2.1: إرسال كود OTP لولي أمر جديد */
    public function test_parent_send_otp_success_and_fails_if_email_exists(): void
    {
        $newEmail = 'parent.new.' . uniqid() . '@darby.test';

        // نجاح طلب الـ OTP
        $response = $this->postJson('/api/parent/send-otp', [
            'email' => $newEmail,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم إرسال كود التحقق بنجاح إلى بريدك الإلكتروني.');

        // التأكد من تسجيل الـ OTP في قاعدة البيانات
        $this->assertDatabaseHas('otp_codes', [
            'email'   => $newEmail,
            'purpose' => 'REGISTER',
        ]);

        // فشل عند تكرار طلب OTP لإيميل مسجل بالفعل في جدول users
        $existingParent = User::create([
            'full_name'     => 'ولي أمر مسجل مسبقاً',
            'email'         => 'registered.parent.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('secret123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $failResponse = $this->postJson('/api/parent/send-otp', [
            'email' => $existingParent->email,
        ]);

        $failResponse->assertStatus(400);
        $failResponse->assertJsonPath('error_code', 'EMAIL_ALREADY_EXISTS');
    }

    /** 2.2: فشل تسجيل ولي الأمر عند عدم مطابقة كلمة المرور أو وجود رموز خاصة */
    public function test_parent_register_fails_with_invalid_password_format_or_mismatch(): void
    {
        $email = 'parent.validation.' . uniqid() . '@darby.test';

        // إنشاء OTP صالح مسبقاً
        OtpCode::create([
            'email'      => $email,
            'code_hash'  => Hash::make('123456'),
            'purpose'    => 'REGISTER',
            'expires_at' => Carbon::now()->addMinutes(10),
            'is_used'    => false,
        ]);

        // تجربة كلمة مرور تحتوي على رمز خاص (ممنوع في قواعد الـ regex لولي الأمر)
        $response = $this->postJson('/api/parent/register', [
            'full_name'             => 'طارق منصور القماطي',
            'email'                 => $email,
            'phone_number'          => '091' . rand(1000000, 9999999),
            'password'              => 'Password@123', // يحتوي على @
            'password_confirmation' => 'Password@123',
            'otp'                   => 123456,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
        $this->assertStringContainsString('يُمنع استخدام الرموز الخاصة', $response->json('errors.password.0'));
    }

    /** 2.3: نجاح تسجيل ولي الأمر بالكامل وتوليد التوكن وإنشاء بروفايل في جدول parents */
    public function test_parent_register_success_with_all_optional_fields(): void
    {
        Storage::fake('public');

        $email = 'parent.success.' . uniqid() . '@darby.test';
        $phone = '091' . rand(1000000, 9999999);

        // زرع كود OTP
        OtpCode::create([
            'email'      => $email,
            'code_hash'  => Hash::make('654321'),
            'purpose'    => 'REGISTER',
            'expires_at' => Carbon::now()->addMinutes(10),
            'is_used'    => false,
        ]);

        $response = $this->post('/api/parent/register', [
            'full_name'             => 'صالح عبد الرحيم التاورغي',
            'email'                 => $email,
            'phone_number'          => $phone,
            'alternative_phone'     => '0921112233',
            'password'              => 'StrongPassword123',
            'password_confirmation' => 'StrongPassword123',
            'otp'                   => 654321,
            'avatar'                => UploadedFile::fake()->image('parent_avatar.jpg'),
            'device_name'           => 'iPhone 15 Pro',
            'platform'              => 'ios',
            'fcm_token'             => 'fcm_parent_unique_token_' . uniqid(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals('صالح عبد الرحيم التاورغي', $response->json('data.full_name'));

        // التحقق من قاعدة البيانات
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals(3, $user->role_id);
        
        $parent = ParentModel::where('user_id', $user->id)->first();
        $this->assertNotNull($parent);
        $this->assertEquals(1, $parent->is_trusted);
    }

    // =========================================================================
    // 3️⃣ اختبارات إنشاء السائق (Driver Creation) — 3 مراحل متتالية
    // =========================================================================

    /** 3.1: المرحلة 1 - طلب تسجيل حساب السائق */
    public function test_driver_register_account_validation_and_success(): void
    {
        // فشل عند عدم تحديد الجنس أو رقم هاتف غير صحيح
        $failResponse = $this->postJson('/api/v1/driver/register', [
            'full_name'    => 'عمر سالم', // اسم قصير
            'email'        => 'invalid-email',
            'phone_number' => '12345',
            'password'     => '123',
        ]);

        $failResponse->assertStatus(422);
        $failResponse->assertJsonValidationErrors(['full_name', 'email', 'phone_number', 'gender', 'password']);

        // نجاح طلب التسجيل للسائق
        $driverEmail = 'driver.reg.' . uniqid() . '@darby.test';
        $successResponse = $this->postJson('/api/v1/driver/register', [
            'full_name'         => 'عمر سالم مصطفى السويحلي',
            'email'             => $driverEmail,
            'phone_number'      => '091' . rand(1000000, 9999999),
            'gender'            => 'male',
            'password'          => 'DriverSecret123',
            'alternative_phone' => '0922223344',
            'platform'          => 'android',
        ]);

        $successResponse->assertStatus(200);
        $successResponse->assertJsonPath('status', true);
        $this->assertDatabaseHas('otp_codes', ['email' => $driverEmail, 'purpose' => 'REGISTER']);
    }

    /** 3.2: المرحلة 2 - تأكيد رمز OTP وإنشاء المستخدم وتوليد التوكن */
    public function test_driver_verify_otp_and_account_creation(): void
    {
        $driverEmail = 'driver.verify.' . uniqid() . '@darby.test';
        $driverPhone = '091' . rand(1000000, 9999999);

        // تجهيز OTP
        OtpCode::create([
            'email'      => $driverEmail,
            'code_hash'  => Hash::make('888999'),
            'purpose'    => 'REGISTER',
            'expires_at' => Carbon::now()->addMinutes(10),
            'is_used'    => false,
        ]);

        $response = $this->postJson('/api/v1/driver/verify-otp', [
            'email'             => $driverEmail,
            'otp'               => 888999,
            'full_name'         => 'مفتاح إبراهيم الزنتاني',
            'phone_number'      => $driverPhone,
            'gender'            => 'male',
            'password'          => 'DriverPass123',
            'alternative_phone' => '0923334455',
            'platform'          => 'android',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEmpty($response->json('user_id'));

        // التحقق من الحساب المنشأ
        $user = User::where('email', $driverEmail)->first();
        $this->assertNotNull($user);
        $this->assertEquals(4, $user->role_id);
    }

    /** 3.3: المرحلة 3 - إكمال ملف السائق ورفع الوثائق وبيانات المركبة */
    public function test_driver_complete_profile_with_vehicle_and_documents(): void
    {
        Storage::fake('public');

        // إنشاء مستخدم سائق موثق
        $driverUser = User::create([
            'full_name'     => 'رمضان عبد القادر المجبري',
            'email'         => 'driver.complete.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('DriverPass123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'gender'  => 'male',
            'status'  => 'Offline',
        ]);

        $payload = [
            'national_id'       => (string) rand(100000000000, 999999999999), // 12 رقماً بالضبط
            'license_number'    => 'DL-' . rand(10000, 99999),
            'license_expiry'    => Carbon::now()->addYears(2)->toDateString(),
            'insurance_expiry'  => Carbon::now()->addYear()->toDateString(),
            'stamp_expiry'                => Carbon::now()->addYear()->toDateString(),
            'technical_inspection_expiry' => Carbon::now()->addYear()->toDateString(),
            'plate_number'      => '5-' . rand(10000, 99999),
            'brand'             => 'Toyota',
            'model'             => 'Coaster',
            'year'              => 2022,
            'color'             => 'أبيض وأزرق',
            'type'              => 'Bus',
            'capacity_manual'   => 22,
            'has_ac'            => 1,
            'vehicle_image'     => UploadedFile::fake()->image('bus.jpg'),
            'doc_license'       => UploadedFile::fake()->image('license.jpg'),
            'doc_logbook'       => UploadedFile::fake()->image('logbook.jpg'),
            'doc_insurance'     => UploadedFile::fake()->image('insurance.jpg'),
            'doc_booklet_page'         => UploadedFile::fake()->image('booklet.jpg'),
            'doc_stamp'                => UploadedFile::fake()->image('stamp.jpg'),
            'doc_technical_inspection' => UploadedFile::fake()->image('technical.jpg'),
        ];

        $response = $this->actingAs($driverUser)
            ->post("/api/v1/driver/complete-profile/{$driverUser->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('message', 'تم رفع البيانات بنجاح، بانتظار مراجعة الإدارة.');

        // التحقق من جدول السائقين drivers
        $driver = Driver::where('user_id', $driverUser->id)->first();
        $this->assertNotNull($driver);
        $this->assertEquals('Pending', $driver->status);
        $this->assertEquals($payload['national_id'], $driver->national_id);

        // التحقق من إنشاء المركبة في جدول vehicles
        $vehicle = Vehicle::where('driver_id', $driver->id)->first();
        $this->assertNotNull($vehicle);
        $this->assertEquals($payload['plate_number'], $vehicle->plate_number);
        $this->assertEquals(22, $vehicle->capacity_manual);

        // التحقق من حفظ الوثائق الرسمية في driver_documents
        $docsCount = DriverDocument::where('driver_id', $driver->id)->count();
        $this->assertGreaterThanOrEqual(3, $docsCount);
    }
}
