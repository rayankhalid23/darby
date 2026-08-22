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

class DriverAbandonRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق تجريبي للإلغاء',
            'email'         => 'driver.abandon.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id,
            'gender'  => 'Male',
            'status'  => 'Offline',
        ]);
    }

    /** Test 1: إلغاء التسجيل عبر DELETE بعد التحقق من OTP مباشرة (مع user_id) */
    public function test_can_abandon_registration_via_delete_after_otp(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->deleteJson('/api/v1/driver/abandon-registration', [
                'user_id' => $this->driverUser->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => true,
            'message' => 'تم إلغاء طلب التسجيل وحذف الحساب غير المكتمل بنجاح.',
        ]);

        $this->assertDatabaseMissing('users', ['id' => $this->driverUser->id]);
        $this->assertDatabaseMissing('drivers', ['id' => $this->driver->id]);
    }

    /** Test 2: إلغاء التسجيل عبر POST cancel-registration (بجسم فارغ اعتماداً على التوكن) */
    public function test_can_abandon_registration_via_post_cancel_empty_body(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/cancel-registration', []);

        $response->assertStatus(200);
        $response->assertJson([
            'status'  => true,
            'message' => 'تم إلغاء طلب التسجيل وحذف الحساب غير المكتمل بنجاح.',
        ]);

        $this->assertDatabaseMissing('users', ['id' => $this->driverUser->id]);
        $this->assertDatabaseMissing('drivers', ['id' => $this->driver->id]);
    }

    /** Test 2b: التحقق من خطأ الـ Validation عند إرسال user_id غير رقمي */
    public function test_abandon_registration_validation_fails_on_invalid_user_id(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->deleteJson('/api/v1/driver/abandon-registration', [
                'user_id' => 'not_an_integer',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'بيانات الإلغاء غير صالحة.');
        $this->assertEquals('معرّف المستخدم يجب أن يكون رقماً صحيحاً.', $response->json('errors.user_id.0'));
    }

    /** Test 3: حذف الملفات المرفوعة عند إلغاء التسجيل بعد رفع الوثائق جزئياً */
    public function test_abandon_registration_cleans_up_uploaded_files_and_vehicles(): void
    {
        Storage::fake('public');

        $vehiclePath = 'drivers/vehicles/test_vehicle.jpg';
        $docPath = 'drivers/documents/test_license.jpg';
        Storage::disk('public')->put($vehiclePath, 'fake vehicle image');
        Storage::disk('public')->put($docPath, 'fake doc image');

        $vehicle = Vehicle::create([
            'driver_id'         => $this->driver->id,
            'plate_number'      => 'ABC-1234',
            'brand'             => 'Toyota',
            'model'             => 'Hiace',
            'year'              => 2022,
            'color'             => 'White',
            'type'              => 'Van',
            'capacity_manual'   => 14,
            'vehicle_image_url' => 'storage/' . $vehiclePath,
            'status'            => 'Pending',
            'is_verified'       => 0,
        ]);

        $doc = DriverDocument::create([
            'driver_id'   => $this->driver->id,
            'vehicle_id'  => $vehicle->id,
            'doc_type'    => 'LICENSE',
            'file_url'    => 'storage/' . $docPath,
            'status'      => 'Pending',
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->deleteJson('/api/v1/driver/abandon-registration');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // تأكيد حذف الملفات من الديسك
        Storage::disk('public')->assertMissing($vehiclePath);
        Storage::disk('public')->assertMissing($docPath);

        // تأكيد حذف البيانات من الجداول
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertDatabaseMissing('driver_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('drivers', ['id' => $this->driver->id]);
        $this->assertDatabaseMissing('users', ['id' => $this->driverUser->id]);
    }

    /** Test 4: رفض محاولة إلغاء حساب مستخدم آخر */
    public function test_cannot_abandon_another_users_registration(): void
    {
        $otherUser = User::create([
            'full_name'     => 'سائق آخر',
            'email'         => 'other.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->deleteJson('/api/v1/driver/abandon-registration', [
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'غير مصرح لك بإلغاء تسجيل هذا الحساب.');

        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('users', ['id' => $this->driverUser->id]);
    }

    /** Test 5: غير المصرح له يرجع 401 */
    public function test_guest_cannot_abandon_registration(): void
    {
        $this->deleteJson('/api/v1/driver/abandon-registration')
            ->assertStatus(401);

        $this->postJson('/api/v1/driver/cancel-registration')
            ->assertStatus(401);
    }
}
