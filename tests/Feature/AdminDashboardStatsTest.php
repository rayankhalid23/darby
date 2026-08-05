<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Contract;
use App\Models\Shared\Trip;

class AdminDashboardStatsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إنشاء مستخدم بصلاحيات مسؤول (Admin)
        $this->adminUser = User::create([
            'full_name'    => 'مدير النظام للاحصائيات',
            'email'        => 'admin.stats.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 1, // Admin
            'is_active'    => 1,
        ]);
    }

    /**
     * اختبار دالة إحصائيات لوحة التحكم الرئيسية GET /api/admin/dashboard/stats
     * للتحقق من إرجاع الإحصائيات السبعة المطلوبة بالشكل الصحيح
     */
    public function test_admin_can_fetch_all_seven_dashboard_statistics(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // التحقق من وجود الهيكل البياني الكامل للإحصائيات السبعة
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_users'               => ['label', 'value', 'raw', 'change', 'trend'],
                'active_drivers'            => ['label', 'value', 'raw', 'change', 'trend'],
                'total_parents'             => ['label', 'value', 'raw', 'change', 'trend'],
                'subscribed_children'       => ['label', 'value', 'raw', 'change', 'trend'],
                'daily_subscriptions'       => ['label', 'value', 'raw', 'change', 'trend'],
                'monthly_subscriptions'     => ['label', 'value', 'raw', 'change', 'trend'],
                'drivers_with_active_trips' => ['label', 'value', 'raw', 'change', 'trend'],
            ],
            'generated_at'
        ]);

        // التحقق من القيم والأوصاف اللفظية
        $data = $response->json('data');
        $this->assertEquals('إجمالي المستخدمين', $data['total_users']['label']);
        $this->assertEquals('إجمالي السائقين المفعلين', $data['active_drivers']['label']);
        $this->assertEquals('إجمالي أولياء الأمور', $data['total_parents']['label']);
        $this->assertEquals('إجمالي الأطفال المشتركين في الرحلات', $data['subscribed_children']['label']);
        $this->assertEquals('إجمالي الاشتراكات اليومية', $data['daily_subscriptions']['label']);
        $this->assertEquals('إجمالي الاشتراكات الشهرية', $data['monthly_subscriptions']['label']);
        $this->assertEquals('إجمالي السائقين الذين لديهم رحلات حالياً', $data['drivers_with_active_trips']['label']);

        // التحقق من أن القيم الأرقام غير سالبة (>= 0)
        $this->assertGreaterThanOrEqual(0, $data['total_users']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['active_drivers']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['total_parents']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['subscribed_children']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['daily_subscriptions']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['monthly_subscriptions']['raw']);
        $this->assertGreaterThanOrEqual(0, $data['drivers_with_active_trips']['raw']);
    }
}
