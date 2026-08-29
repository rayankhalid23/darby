<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\User;
use App\Models\Role;
use App\Models\Admin\Admin;
use App\Constants\PermissionConstants;
use Illuminate\Support\Facades\Hash;

class AdminPermissionsAndRolesTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdminUser;
    protected User $financeUser;
    protected User $operationsUser;
    protected User $fleetUser;
    protected User $supportUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Super Admin
        $this->superAdminUser = User::factory()->create([
            'email'     => 'super_test_' . uniqid() . '@darby.ly',
            'role_id'   => 1,
            'is_active' => true,
        ]);
        Admin::create(['user_id' => $this->superAdminUser->id, 'created_by' => 1]);

        // 2. Finance Officer (Role 7)
        $this->financeUser = User::factory()->create([
            'email'     => 'finance_test_' . uniqid() . '@darby.ly',
            'role_id'   => 7,
            'is_active' => true,
        ]);
        Admin::create(['user_id' => $this->financeUser->id, 'created_by' => 1]);

        // 3. Operations Supervisor (Role 2)
        $this->operationsUser = User::factory()->create([
            'email'     => 'ops_test_' . uniqid() . '@darby.ly',
            'role_id'   => 2,
            'is_active' => true,
        ]);
        Admin::create(['user_id' => $this->operationsUser->id, 'created_by' => 1]);

        // 4. Fleet Supervisor (Role 5)
        $this->fleetUser = User::factory()->create([
            'email'     => 'fleet_test_' . uniqid() . '@darby.ly',
            'role_id'   => 5,
            'is_active' => true,
        ]);
        Admin::create(['user_id' => $this->fleetUser->id, 'created_by' => 1]);

        // 5. Support Supervisor (Role 6)
        $this->supportUser = User::factory()->create([
            'email'     => 'support_test_' . uniqid() . '@darby.ly',
            'role_id'   => 6,
            'is_active' => true,
        ]);
        Admin::create(['user_id' => $this->supportUser->id, 'created_by' => 1]);
    }

    /**
     * 1. اختبار جلب شجرة الأدوار والصلاحيات المتاحة للنظام
     */
    public function test_get_roles_and_permissions_tree(): void
    {
        $response = $this->actingAs($this->superAdminUser)->getJson('/api/admin/roles-permissions');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status',
            'success',
            'message',
            'data' => [
                'roles' => [
                    '*' => ['id', 'key', 'name', 'description', 'permissions']
                ],
                'permissions_tree' => [
                    '*' => ['group_key', 'group_name', 'permissions']
                ]
            ]
        ]);
    }

    /**
     * 2. اختبار صلاحيات المدير العام (Super Admin) - وصول كامل لكافة المسارات
     */
    public function test_super_admin_has_unrestricted_access(): void
    {
        $this->withoutExceptionHandling();

        // الوصول للرادار والعمليات
        $res1 = $this->actingAs($this->superAdminUser)->getJson('/api/admin/dashboard/stats');
        $res1->assertStatus(200);

        // الوصول للمالية
        $res2 = $this->actingAs($this->superAdminUser)->getJson('/api/admin/financial/summary');
        $res2->assertStatus(200);

        // الوصول لإدارة المشرفين
        $res3 = $this->actingAs($this->superAdminUser)->getJson('/api/admin/admins');
        $res3->assertStatus(200);
    }

    /**
     * 3. اختبار المشرف المالي: مسموح له بالمالية وممنوع من إدارة المشرفين أو توليد الرحلات
     */
    public function test_finance_officer_permissions_and_restrictions(): void
    {
        // مسموح: ملخص المالية
        $allowedRes = $this->actingAs($this->financeUser)->getJson('/api/admin/financial/summary');
        $allowedRes->assertStatus(200);

        // ممنوع: إدارة المشرفين (403 Forbidden)
        $forbiddenRes1 = $this->actingAs($this->financeUser)->getJson('/api/admin/admins');
        $forbiddenRes1->assertStatus(403);
        $forbiddenRes1->assertJsonPath('status', false);

        // ممنوع: توليد الرحلات اليومية (403 Forbidden)
        $forbiddenRes2 = $this->actingAs($this->financeUser)->postJson('/api/admin/trips/generate-daily');
        $forbiddenRes2->assertStatus(403);
    }

    /**
     * 4. اختبار مشرف العمليات: مسموح له بالرادار وممنوع من السحب والشحن المالي
     */
    public function test_operations_supervisor_permissions_and_restrictions(): void
    {
        // مسموح: رادار الرحلات الحية
        $allowedRes = $this->actingAs($this->operationsUser)->getJson('/api/admin/dashboard/active-trips');
        $allowedRes->assertStatus(200);

        // ممنوع: طلبات السحب المالي (403 Forbidden)
        $forbiddenRes = $this->actingAs($this->operationsUser)->getJson('/api/admin/financial/withdrawals');
        $forbiddenRes->assertStatus(403);
    }

    /**
     * 5. اختبار مشرف شؤون السائقين: مسموح له بقائمة السائقين وممنوع من المدارس
     */
    public function test_fleet_supervisor_permissions_and_restrictions(): void
    {
        // مسموح: استعراض السائقين
        $allowedRes = $this->actingAs($this->fleetUser)->getJson('/api/admin/drivers');
        $allowedRes->assertStatus(200);

        // ممنوع: إدارة المدارس (403 Forbidden)
        $forbiddenRes = $this->actingAs($this->fleetUser)->getJson('/api/admin/schools');
        $forbiddenRes->assertStatus(403);
    }

    /**
     * 6. اختبار مشرف الشكاوى: مسموح له بالشكاوى وممنوع من الإعدادات المالية
     */
    public function test_support_supervisor_permissions_and_restrictions(): void
    {
        // مسموح: الشكاوى
        $allowedRes = $this->actingAs($this->supportUser)->getJson('/api/admin/complaints');
        $allowedRes->assertStatus(200);

        // ممنوع: ضبط أسعار المنصة (403 Forbidden)
        $forbiddenRes = $this->actingAs($this->supportUser)->getJson('/api/admin/financial/pricing-settings');
        $forbiddenRes->assertStatus(403);
    }

    /**
     * 7. اختبار الصلاحيات المخصصة (Custom Permissions Overrides)
     */
    public function test_custom_permissions_override(): void
    {
        // مشرف العمليات في الأصل ممنوع من طلبات السحب
        $resBefore = $this->actingAs($this->operationsUser)->getJson('/api/admin/financial/withdrawals');
        $resBefore->assertStatus(403);

        // منح مشرف العمليات صلاحية استثنائية: financial.manage_withdrawals
        $this->operationsUser->update([
            'custom_permissions' => [PermissionConstants::FINANCIAL_WITHDRAWALS]
        ]);

        // الآن يستطيع الوصول بنجاح (200 OK)
        $resAfter = $this->actingAs($this->operationsUser->fresh())->getJson('/api/admin/financial/withdrawals');
        $resAfter->assertStatus(200);
    }

    /**
     * 8. اختبار إنشاء مشرف جديد مع تعيين دور وصلاحيات مخصصة له
     */
    public function test_super_admin_can_create_admin_with_role_and_permissions(): void
    {
        $payload = [
            'full_name'          => 'مشرف مالي جديد',
            'email'              => 'new_finance_' . uniqid() . '@darby.ly',
            'phone_number'       => '0919' . rand(100000, 999999),
            'role_id'            => 7, // finance_officer
            'custom_permissions' => [PermissionConstants::NOTIFICATIONS_BROADCAST],
            'is_active'          => 1,
        ];

        $response = $this->actingAs($this->superAdminUser)->postJson('/api/admin/admins', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.role_id', 7);
        $response->assertJsonPath('data.role_name', 'المشرف المالي ومسؤول الخزينة');
        $this->assertContains(PermissionConstants::NOTIFICATIONS_BROADCAST, $response->json('data.custom_permissions'));
    }
}
