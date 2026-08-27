<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;

class DriverVehicleUpdateAndAdminReviewLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'SuperAdmin', 'display_name' => 'مدير النظام'],
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام تجريبي',
            'email'         => 'admin.vehicle.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        \App\Models\Admin\Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق تجريبي تعديل المركبة',
            'email'         => 'driver.vehicle.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'gender'         => 'Male',
            'status'         => 'Approved',
            'national_id'    => (string) rand(100000000000, 999999999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id'         => $this->driver->id,
            'plate_number'      => '5-11223',
            'brand'             => 'Toyota',
            'model'             => 'Hiace',
            'year'              => 2020,
            'color'             => 'White',
            'type'              => 'Van',
            'capacity_manual'   => 14,
            'has_ac'            => true,
            'vehicle_image_url' => 'storage/drivers/vehicles/old_van.jpg',
            'status'            => 'Active',
            'is_verified'       => 1,
        ]);
    }

    /**
     * 1. اختبار قيام السائق بطلب تعديل مركبته، عرض الطلب للأدمن، والموافقة عليه وتطبيقه فوراً
     */
    public function test_driver_submits_vehicle_update_and_admin_approves_it_successfully(): void
    {
        $newPlate = '6-99887';
        $newColor = 'Silver';
        $newYear  = 2023;

        // 1. السائق يرسل طلب تعديل بيانات المركبة
        $updateRes = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/profile/vehicles/{$this->vehicle->id}", [
                'plate_number'  => $newPlate,
                'color'         => $newColor,
                'year'          => $newYear,
                'vehicle_photo' => UploadedFile::fake()->image('new_vehicle.jpg'),
            ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('status', true);

        // التحقق من تسجيل طلب التعديل في driver_profile_changes بالحالة Pending
        $change = DB::table('driver_profile_changes')
            ->where('driver_id', $this->driver->id)
            ->where('status', 'Pending')
            ->latest('id')
            ->first();

        $this->assertNotNull($change);
        $newValues = json_decode($change->new_values, true);
        $this->assertEquals($newPlate, $newValues['plate_number']);
        $this->assertEquals($newColor, $newValues['color']);
        $this->assertEquals($newYear, $newValues['year']);

        // 2. الأدمن يعرض قائمة التعديلات المعلقة
        $listRes = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/drivers/pending-changes');
        $listRes->assertStatus(200);
        $listRes->assertJsonPath('status', true);

        // 3. الأدمن يعرض تفاصيل طلب التعديل المحدد
        $showRes = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/drivers/pending-changes/{$change->id}");
        $showRes->assertStatus(200);
        $showRes->assertJsonPath('status', true);
        $showRes->assertJsonPath('data.driver_id', $this->driver->id);

        // 4. الأدمن يوافق على طلب التعديل
        $reviewRes = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/pending-changes/{$change->id}/review", [
                'decision' => 'Approved',
            ]);

        $reviewRes->assertStatus(200);
        $reviewRes->assertJsonPath('status', true);

        // 5. التحقق من تطبيق التعديلات فعلياً على سجل المركبة في جدول vehicles
        $this->vehicle->refresh();
        $this->assertEquals($newPlate, $this->vehicle->plate_number);
        $this->assertEquals($newColor, $this->vehicle->color);
        $this->assertEquals($newYear, $this->vehicle->year);
        $this->assertEquals('Active', $this->vehicle->status);
        $this->assertEquals(1, $this->vehicle->is_verified);
        $this->assertNotEmpty($this->vehicle->vehicle_image_url);

        // التحقق من تحول حالة طلب التعديل إلى Approved
        $this->assertDatabaseHas('driver_profile_changes', [
            'id'     => $change->id,
            'status' => 'Approved',
        ]);
    }

    /**
     * 2. اختبار قيام السائق بطلب تعديل مركبته، والأدمن يرفضه مع تسجيل سبب الرفض
     */
    public function test_driver_submits_vehicle_update_and_admin_rejects_it_with_reason(): void
    {
        $originalPlate = $this->vehicle->plate_number;
        $invalidPlate  = 'INVALID-PLATE';

        // 1. السائق يرسل طلب تعديل
        $updateRes = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/profile/vehicles/{$this->vehicle->id}", [
                'plate_number' => $invalidPlate,
            ]);
        $updateRes->assertStatus(200);

        $change = DB::table('driver_profile_changes')
            ->where('driver_id', $this->driver->id)
            ->where('status', 'Pending')
            ->latest('id')
            ->first();

        $this->assertNotNull($change);

        // 2. الأدمن يرفض طلب التعديل مع سبب
        $rejectionReason = 'رقم اللوحة غير متطابق مع الكتيب المرفوع.';
        $reviewRes = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/pending-changes/{$change->id}/review", [
                'decision'         => 'Rejected',
                'rejection_reason' => $rejectionReason,
            ]);

        $reviewRes->assertStatus(200);
        $reviewRes->assertJsonPath('status', true);

        // 3. التحقق من بقاء بيانات المركبة الأصلية كما هي
        $this->vehicle->refresh();
        $this->assertEquals($originalPlate, $this->vehicle->plate_number);

        // 4. التحقق من تسجيل الرفض وسببه في جدول driver_profile_changes
        $this->assertDatabaseHas('driver_profile_changes', [
            'id'               => $change->id,
            'status'           => 'Rejected',
            'rejection_reason' => $rejectionReason,
        ]);
    }
}
