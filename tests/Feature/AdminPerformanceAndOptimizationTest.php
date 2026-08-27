<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Driver\Driver;

class AdminPerformanceAndOptimizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'SuperAdmin', 'display_name' => 'مدير النظام'],
            ['id' => 2, 'name' => 'Admin', 'display_name' => 'مشرف'],
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام السريع',
            'email'         => 'admin.perf.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        $this->admin = Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    /**
     * 1. اختبار سرعة واستجابة GET /api/admin/profile
     */
    public function test_admin_profile_returns_fast_response(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/profile');

        $duration = microtime(true) - $startTime;

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.full_name', $this->adminUser->full_name);

        // التأكد من أن الاستجابة سريعة جداً (أقل من ثانية واحدة)
        $this->assertLessThan(1.5, $duration, "Profile endpoint took too long: {$duration}s");
    }

    /**
     * 2. اختبار سرعة واستجابة GET /api/admin/drivers مع الترقيم والفلترة
     */
    public function test_admin_drivers_list_returns_paginated_response(): void
    {
        // إنشاء بعض السائقين التجريبيين
        for ($i = 0; $i < 3; $i++) {
            $user = User::create([
                'full_name'     => 'سائق تجريبي سريع ' . $i,
                'email'         => 'driver.perf.' . uniqid() . '@darby.test',
                'phone_number'  => '091' . rand(1000000, 9999999),
                'password_hash' => bcrypt('password123'),
                'role_id'       => 4,
                'is_active'     => 1,
            ]);

            Driver::create([
                'user_id'        => $user->id,
                'gender'         => 'male',
                'status'         => 'Approved',
                'national_id'    => (string) rand(100000000000, 999999999999),
                'license_number' => 'LIC' . rand(100000, 999999),
                'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            ]);
        }

        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/drivers?per_page=10');

        $duration = microtime(true) - $startTime;

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('data', $response->json());

        // التأكد من سرعة الاستجابة
        $this->assertLessThan(2.0, $duration, "Drivers endpoint took too long: {$duration}s");
    }

    /**
     * 3. اختبار إحصائيات الداشبورد المخزنة مؤقتاً GET /api/admin/dashboard/stats
     */
    public function test_admin_dashboard_stats_cached_fast_response(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/dashboard/stats');

        $duration = microtime(true) - $startTime;

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('total_users', $response->json('data'));

        $this->assertLessThan(1.5, $duration, "Dashboard stats took too long: {$duration}s");
    }
}
