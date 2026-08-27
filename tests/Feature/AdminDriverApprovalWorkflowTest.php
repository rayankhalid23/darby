<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;

class AdminDriverApprovalWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected DriverDocument $doc1;
    protected DriverDocument $doc2;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'SuperAdmin', 'display_name' => 'مدير النظام'],
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام تجريبي',
            'email'         => 'admin.approval.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        // سائق جديد معلق بانتظار الموافقة بعد إكمال التسجيل
        $this->driverUser = User::create([
            'full_name'     => 'سائق جديد قيد المراجعة',
            'email'         => 'driver.pending.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0, // غير مفعل حتى الآن
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'gender'         => 'Male',
            'status'         => 'Pending', // حالة السائق معلقة
            'national_id'    => (string) rand(100000000000, 999999999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => '5-' . rand(10000, 99999),
            'brand'           => 'Toyota',
            'model'           => 'Coaster',
            'year'            => 2023,
            'color'           => 'White',
            'type'            => 'Bus',
            'capacity_manual' => 24,
            'has_ac'          => true,
            'status'          => 'Pending', // المركبة معلقة
            'is_verified'     => 0,
        ]);

        $this->doc1 = DriverDocument::create([
            'driver_id'   => $this->driver->id,
            'vehicle_id'  => $this->vehicle->id,
            'doc_type'    => 'LICENSE',
            'file_url'    => 'storage/drivers/documents/lic.jpg',
            'status'      => 'Pending', // الوثيقة معلقة
            'uploaded_at' => now(),
        ]);

        $this->doc2 = DriverDocument::create([
            'driver_id'             => $this->driver->id,
            'vehicle_id'            => $this->vehicle->id,
            'doc_type'              => 'INSURANCE',
            'file_url'              => 'storage/drivers/documents/ins.jpg',
            'insurance_expiry_date' => now()->addYears(1)->format('Y-m-d'),
            'status'                => 'Pending', // الوثيقة معلقة
            'uploaded_at'           => now(),
        ]);
    }

    /**
     * اختبار موافقة الأدمن على السائق وتفعيل الحساب والمركبة وجميع الوثائق الرسمية
     */
    public function test_admin_approving_driver_activates_user_driver_vehicle_and_all_documents(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/{$this->driver->id}/review", [
                'status' => 'Approved',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // 1. التحقق من تفعيل المستخدم (User)
        $this->driverUser->refresh();
        $this->assertEquals(1, $this->driverUser->is_active);

        // 2. التحقق من اعتماد السائق (Driver)
        $this->driver->refresh();
        $this->assertEquals('Approved', $this->driver->status);

        // 3. التحقق من تفعيل وتأكيد المركبة (Vehicle)
        $this->vehicle->refresh();
        $this->assertEquals('Active', $this->vehicle->status);
        $this->assertEquals(1, $this->vehicle->is_verified);

        // 4. التحقق من اعتماد جميع الوثائق (DriverDocuments -> Verified)
        $this->doc1->refresh();
        $this->doc2->refresh();
        $this->assertEquals('Verified', $this->doc1->status);
        $this->assertEquals('Verified', $this->doc2->status);

        $this->assertDatabaseMissing('driver_documents', [
            'driver_id' => $this->driver->id,
            'status'    => 'Pending',
        ]);
    }

    /**
     * اختبار رفض الأدمن لطلب السائق وتحديث الحالات بشكل سليم
     */
    public function test_admin_rejecting_driver_updates_statuses_properly(): void
    {
        $rejectionReason = 'الوثائق غير مطابقة للشروط.';

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/{$this->driver->id}/review", [
                'status'           => 'Rejected',
                'rejection_reason' => $rejectionReason,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // 1. السائق يصبح مرفوضاً
        $this->driver->refresh();
        $this->assertEquals('Rejected', $this->driver->status);

        // 2. المركبة غير مؤكدة
        $this->vehicle->refresh();
        $this->assertEquals(0, $this->vehicle->is_verified);

        // 3. الوثائق تصبح Rejected
        $this->doc1->refresh();
        $this->assertEquals('Rejected', $this->doc1->status);
    }
}
