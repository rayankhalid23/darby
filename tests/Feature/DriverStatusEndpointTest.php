<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverApproval;

class DriverStatusEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);
    }

    protected function makeDriverUser(string $status, bool $isActive): array
    {
        $user = User::create([
            'full_name'     => 'سائق اختبار الحالة',
            'email'         => 'driver.status.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => $isActive ? 1 : 0,
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'gender'  => 'Male',
            'status'  => $status,
        ]);

        return [$user, $driver];
    }

    /** Test 1: حالة Pending — is_active=false و rejection_reason=null */
    public function test_status_pending(): void
    {
        [$user] = $this->makeDriverUser('Pending', false);

        $response = $this->actingAs($user)->getJson('/api/v1/driver/status');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status'            => true,
            'is_active'         => false,
            'driver_status'     => 'Pending',
            'rejection_reason'  => null,
        ]);
    }

    /** Test 2: حالة Approved — is_active=true و rejection_reason=null */
    public function test_status_approved(): void
    {
        [$user] = $this->makeDriverUser('Approved', true);

        $response = $this->actingAs($user)->getJson('/api/v1/driver/status');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status'            => true,
            'is_active'         => true,
            'driver_status'     => 'Approved',
            'rejection_reason'  => null,
        ]);
    }

    /** Test 3: حالة Rejected — يظهر سبب الرفض من آخر سجل مراجعة */
    public function test_status_rejected_includes_reason(): void
    {
        [$user, $driver] = $this->makeDriverUser('Rejected', false);

        $adminUser = User::create([
            'full_name'     => 'أدمن اختبار الحالة',
            'email'         => 'admin.status.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        $admin = Admin::create(['user_id' => $adminUser->id, 'created_by' => $adminUser->id]);

        DriverApproval::create([
            'driver_id'        => $driver->id,
            'admin_id'         => $admin->id,
            'status'           => 'Rejected',
            'rejection_reason' => 'صورة رخصة القيادة غير واضحة.',
            'created_at'       => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/driver/status');

        $response->assertStatus(200);
        $response->assertExactJson([
            'status'            => true,
            'is_active'         => false,
            'driver_status'     => 'Rejected',
            'rejection_reason'  => 'صورة رخصة القيادة غير واضحة.',
        ]);
    }

    /** Test 4: لا يوجد ملف سائق مرتبط بالحساب */
    public function test_status_returns_404_when_no_driver_profile(): void
    {
        $user = User::create([
            'full_name'     => 'مستخدم بلا ملف سائق',
            'email'         => 'no.driver.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/driver/status');

        $response->assertStatus(404);
        $response->assertJsonPath('status', false);
    }
}
