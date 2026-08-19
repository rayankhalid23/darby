<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Admin\AdminAuditLog;
use App\Models\Driver\Driver;
use App\Models\User;
use App\Services\Admin\AdminAuditLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Admin $admin;
    protected User $supervisorUser;
    protected Admin $supervisor;
    protected User $driverUser;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. حساب مدير النظام (Admin - role_id = 1)
        $this->adminUser = User::create([
            'full_name'     => 'أحمد المدير العام',
            'email'         => 'admin.audit.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        $this->admin = Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        // 2. حساب مشرف عادي (Supervisor - role_id = 2)
        $this->supervisorUser = User::create([
            'full_name'     => 'سالم المشرف',
            'email'         => 'sup.audit.' . uniqid() . '@darby.test',
            'phone_number'  => '09' . rand(10000000, 99999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->supervisor = Admin::create([
            'user_id'    => $this->supervisorUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        // 3. حساب سائق للاختبار
        $this->driverUser = User::create([
            'full_name'     => 'الكابتن عبد السلام المهدوي',
            'email'         => 'driver.audit.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => '119880012345',
            'license_number' => 'LY-4421',
            'license_expiry' => '2026-12-31',
            'status'         => 'Pending',
        ]);
    }

    /**
     * 1. اختبار حظر وصول المشرف العادي لسجل التدقيق (403 Forbidden)
     */
    public function test_supervisor_cannot_access_audit_logs(): void
    {
        $response = $this->actingAs($this->supervisorUser, 'sanctum')
            ->getJson('/api/admin/admin-audit-logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'عذراً، هذا السجل مخصص للإدارة العامة فقط.',
            ]);
    }

    /**
     * 2. اختبار وصول الأدمن بنجاح لسجل التدقيق (200 OK)
     */
    public function test_admin_can_access_audit_logs(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/admin-audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ]);
    }

    /**
     * 3. اختبار تعديل بيانات السائق مباشرة من قبل المشرف مع التوثيق التلقائي في السجل
     * PUT /api/admin/drivers/{id}
     */
    public function test_update_driver_automatically_creates_audit_log_with_changes(): void
    {
        $oldPhone = $this->driverUser->phone_number;
        $newPhone = '092' . rand(1000000, 9999999);

        $payload = [
            'full_name'      => 'مفتاح الزنتاني',
            'phone_number'   => $newPhone,
            'license_number' => 'LY-4428',
            'reason'         => 'تصحيح بعد مطابقة الوثائق في المقابلة',
        ];

        $response = $this->actingAs($this->supervisorUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم تحديث بيانات السائق بنجاح.',
            ]);

        // التحقق من تحديث السائق في قاعدة البيانات
        $this->assertDatabaseHas('users', [
            'id'           => $this->driverUser->id,
            'full_name'    => 'مفتاح الزنتاني',
            'phone_number' => $newPhone,
        ]);
        $this->assertDatabaseHas('drivers', [
            'id'             => $this->driver->id,
            'license_number' => 'LY-4428',
        ]);

        // التحقق من إنشاء سطر التدقيق تلقائياً
        $auditLog = AdminAuditLog::where('entity_type', 'driver')
            ->where('entity_id', $this->driver->id)
            ->where('action', 'update_driver')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->supervisor->id, $auditLog->admin_id);
        $this->assertEquals('سالم المشرف', $auditLog->admin_name);
        $this->assertEquals('مشرف', $auditLog->admin_role);
        $this->assertEquals('تعديل بيانات سائق', $auditLog->action_label);
        $this->assertEquals('update', $auditLog->action_group);
        $this->assertEquals('تصحيح بعد مطابقة الوثائق في المقابلة', $auditLog->reason);

        // التحقق من مصفوفة التغييرات بالفروقات واللابل العربي
        $changes = $auditLog->changes;
        $this->assertNotEmpty($changes);

        $fieldsChanged = array_column($changes, 'field');
        $this->assertContains('full_name', $fieldsChanged);
        $this->assertContains('phone_number', $fieldsChanged);
        $this->assertContains('license_number', $fieldsChanged);

        $phoneChange = collect($changes)->firstWhere('field', 'phone_number');
        $this->assertEquals($oldPhone, $phoneChange['old_value']);
        $this->assertEquals($newPhone, $phoneChange['new_value']);
        $this->assertEquals('رقم الهاتف', $phoneChange['label']);
    }

    /**
     * 4. اختبار تسجيل قرار رفض السائق في السجل (Review Rejection)
     */
    public function test_driver_rejection_logs_decision_and_reason(): void
    {
        $response = $this->actingAs($this->supervisorUser, 'sanctum')
            ->postJson("/api/admin/drivers/{$this->driver->id}/review", [
                'status'           => 'Rejected',
                'rejection_reason' => 'صورة الرخصة غير واضحة',
            ]);

        $response->assertStatus(200);

        $auditLog = AdminAuditLog::where('entity_type', 'driver')
            ->where('entity_id', $this->driver->id)
            ->where('action', 'reject_driver')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('rejected', $auditLog->result);
        $this->assertEquals('صورة الرخصة غير واضحة', $auditLog->reason);
        $this->assertEquals('decision', $auditLog->action_group);
        $this->assertEquals('رفض سائق', $auditLog->action_label);
    }

    /**
     * 5. اختبار فلترة السجل حسب المشرف ونوع الكيان والتاريخ والبحث
     */
    public function test_audit_logs_filters_and_search(): void
    {
        $service = app(AdminAuditLogService::class);

        // إنشاء سجلات متعددة للاختبار
        $service->record(
            action: 'reject_driver',
            entityType: 'driver',
            entityId: 10,
            entityName: 'سائق تجريبي 1',
            result: 'rejected',
            reason: 'سبب الرفض الأول',
            adminId: $this->admin->id,
            adminName: 'أحمد المدير العام',
            adminRole: 'مدير النظام'
        );

        $service->record(
            action: 'approve_withdrawal',
            entityType: 'withdrawal',
            entityId: 5,
            entityName: 'سحب رصيد #5',
            result: 'approved',
            reason: 'موافقة على السحب بمبلغ 150 د.ل',
            adminId: $this->supervisor->id,
            adminName: 'سالم المشرف',
            adminRole: 'مشرف'
        );

        // فلترة بالـ entity_type = withdrawal
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/admin-audit-logs?entity_type=withdrawal');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('withdrawal', $item['entity_type']);
        }

        // فلترة بالـ action_group = decision
        $responseGroup = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/admin-audit-logs?action_group=decision');

        $responseGroup->assertStatus(200);
        $this->assertNotEmpty($responseGroup->json('data'));

        // فلترة بالبحث النصي
        $responseSearch = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/admin-audit-logs?search=سالم');

        $responseSearch->assertStatus(200);
        $this->assertTrue(collect($responseSearch->json('data'))->contains('admin_name', 'سالم المشرف'));
    }

    /**
     * 6. اختبار جلب تفاصيل سطر سجل واحد
     */
    public function test_show_single_audit_log_entry(): void
    {
        $service = app(AdminAuditLogService::class);
        $log = $service->record(
            action: 'approve_driver',
            entityType: 'driver',
            entityId: 25,
            entityName: 'الكابتن محمد',
            result: 'approved',
            adminId: $this->admin->id
        );

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/admin/admin-audit-logs/{$log->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.action', 'approve_driver')
            ->assertJsonPath('data.action_label', 'قبول وتفعيل سائق')
            ->assertJsonPath('data.entity_name', 'الكابتن محمد');
    }

    /**
     * 7. اختبار عدم قابلية السجل للتعديل أو الحذف (Immutability)
     */
    public function test_audit_logs_are_immutable(): void
    {
        $service = app(AdminAuditLogService::class);
        $log = $service->record(
            action: 'update_driver',
            entityType: 'driver',
            entityId: 99,
            entityName: 'سائق غير قابل للمسح',
            adminId: $this->admin->id
        );

        $this->expectException(\RuntimeException::class);
        $log->update(['reason' => 'محاولة تعديل غير مصرح بها']);
    }
}
