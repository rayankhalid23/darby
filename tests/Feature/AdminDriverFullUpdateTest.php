<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin\Admin;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;

class AdminDriverFullUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $supervisorUser;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'full_name'     => 'أدمن اختبار التعديل الشامل',
            'email'         => 'admin.fullupdate.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        Admin::create(['user_id' => $this->adminUser->id, 'created_by' => $this->adminUser->id]);

        $this->supervisorUser = User::create([
            'full_name'     => 'مشرف اختبار التعديل الشامل',
            'email'         => 'supervisor.fullupdate.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        Admin::create(['user_id' => $this->supervisorUser->id, 'created_by' => $this->adminUser->id]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق اختبار التعديل الشامل',
            'email'         => 'driver.fullupdate.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC-OLD',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'status'         => 'Approved',
        ]);
        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => 'OLD-1234',
            'brand'           => 'Toyota',
            'model'           => 'Hiace',
            'year'            => 2018,
            'color'           => 'أبيض',
            'type'            => 'Van',
            'capacity_manual' => 12,
            'has_ac'          => false,
            'is_verified'     => true,
            'status'          => 'Active',
        ]);
    }

    /** Test 1: تعديل البيانات الشخصية + المركبة + وثيقة التأمين معاً في طلب واحد */
    public function test_admin_can_update_personal_vehicle_and_document_data_together(): void
    {
        $payload = [
            'full_name'         => 'محمد الطرابلسي المُحدَّث',
            'plate_number'      => 'NEW-9999',
            'brand'             => 'Hyundai',
            'capacity_manual'   => 14,
            'has_ac'            => true,
            'insurance_expiry'  => now()->addYear()->format('Y-m-d'),
            'doc_insurance'     => UploadedFile::fake()->image('insurance.jpg'),
            'reason'            => 'تحديث شامل بعد زيارة ميدانية',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'id'        => $this->driverUser->id,
            'full_name' => 'محمد الطرابلسي المُحدَّث',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id'              => $this->vehicle->id,
            'plate_number'    => 'NEW-9999',
            'brand'           => 'Hyundai',
            'capacity_manual' => 14,
            'has_ac'          => true,
        ]);

        $doc = DriverDocument::where('driver_id', $this->driver->id)->where('doc_type', 'INSURANCE')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('Pending', $doc->status);
        $this->assertNotNull($doc->file_url);
        $this->assertNull($doc->expiry_notified_milestone);

        $response->assertJsonPath('data.vehicles.0.plate_number', 'NEW-9999');
        $response->assertJsonPath('data.vehicles.0.brand', 'Hyundai');
    }

    /** Test 2: تعديل يستهدف مركبة محددة عبر vehicle_id عندما يملك السائق أكثر من مركبة */
    public function test_admin_can_target_specific_vehicle_by_id(): void
    {
        $secondVehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => 'SECOND-001',
            'brand'           => 'Kia',
            'model'           => 'Bongo',
            'year'            => 2019,
            'color'           => 'أزرق',
            'type'            => 'Van',
            'capacity_manual' => 8,
            'is_verified'     => false,
            'status'          => 'Pending',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", [
                'vehicle_id' => $secondVehicle->id,
                'color'      => 'أحمر',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('vehicles', ['id' => $secondVehicle->id, 'color' => 'أحمر']);
        $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id, 'color' => 'أبيض']); // لم يتغير
    }

    /** Test 3: تعديل تاريخ انتهاء الرخصة يُعيد ضبط عداد التذكيرات ويُلغي علامة "منتهية" على مستند الرخصة */
    public function test_updating_license_expiry_resets_notified_milestone_and_unexpires_document(): void
    {
        $this->driver->update(['license_expiry_notified_milestone' => 0]);
        DriverDocument::create([
            'driver_id' => $this->driver->id,
            'doc_type'  => 'LICENSE',
            'file_url'  => 'storage/drivers/documents/old-license.jpg',
            'status'    => 'Expired',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", [
                'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            ]);

        $response->assertStatus(200);

        $this->assertNull($this->driver->fresh()->license_expiry_notified_milestone);
        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $this->driver->id,
            'doc_type'  => 'LICENSE',
            'status'    => 'Pending',
        ]);
    }

    /** Test 4: طلب تعديل مركبة لسائق لا يملك أي مركبة مسجلة يفشل بوضوح */
    public function test_fails_clearly_when_driver_has_no_vehicle_to_update(): void
    {
        $bareDriverUser = User::create([
            'full_name'     => 'سائق بلا مركبة',
            'email'         => 'driver.novehicle.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);
        $bareDriver = Driver::create([
            'user_id' => $bareDriverUser->id,
            'status'  => 'Pending',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$bareDriver->id}", [
                'brand' => 'Toyota',
            ]);

        $response->assertStatus(500);
        $response->assertJsonPath('status', false);
    }

    /** Test 5: المشرف (role_id=2) يقدر أيضاً يعدّل البيانات الشخصية + المركبة + الوثائق معاً، تماماً مثل مدير النظام */
    public function test_supervisor_can_also_update_personal_vehicle_and_document_data(): void
    {
        $payload = [
            'phone_number'      => '093' . rand(1000000, 9999999),
            'plate_number'      => 'SUP-7777',
            'capacity_manual'   => 16,
            'insurance_expiry'  => now()->addYear()->format('Y-m-d'),
            'doc_insurance'     => UploadedFile::fake()->image('insurance-supervisor.jpg'),
        ];

        $response = $this->actingAs($this->supervisorUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $this->assertDatabaseHas('vehicles', [
            'id'              => $this->vehicle->id,
            'plate_number'    => 'SUP-7777',
            'capacity_manual' => 16,
        ]);

        $doc = DriverDocument::where('driver_id', $this->driver->id)->where('doc_type', 'INSURANCE')->first();
        $this->assertNotNull($doc);
        $this->assertEquals(now()->addYear()->format('Y-m-d'), $doc->insurance_expiry_date);
    }
}
