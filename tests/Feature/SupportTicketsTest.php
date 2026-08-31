<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\Invoice;
use App\Models\Shared\SupportTicket;

/**
 * اختبار نظام الدعم الفني (Support Tickets) الذي حلّ محل منطق الشكاوى القديم:
 * إنشاء التذاكر من الطرفين (ولي أمر/سائق) بفئاتها الثلاث، توجيه الأدمن حسب
 * القسم (تشغيل/طوارئ ← مالية)، تنفيذ التسوية المالية الفعلية عبر نظام
 * المحافظ الحالي، العقوبات التشغيلية، والإغلاق والتوثيق.
 */
class SupportTicketsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $opsAdminUser;
    protected User $financeAdminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;

    protected function setUp(): void
    {
        parent::setUp();

        // مشرف عمليات وطوارئ (role_id = 2) ومشرف مالي (role_id = 7)
        // بافتراض أن RolesAndPermissionsSeeder نُفِّذ على قاعدة بيانات الاختبار
        // بصلاحيات tickets.* المحدَّثة (خطوة تشغيلية بعد نشر هذا الكود).
        $this->opsAdminUser = User::create([
            'full_name'    => 'مشرف التشغيل',
            'email'        => 'ops.ticket.' . uniqid() . '@darby.test',
            'phone_number' => '090' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);
        DB::table('admins')->insert(['user_id' => $this->opsAdminUser->id, 'created_by' => $this->opsAdminUser->id]);

        $this->financeAdminUser = User::create([
            'full_name'    => 'المشرف المالي',
            'email'        => 'finance.ticket.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 7,
            'is_active'    => 1,
        ]);
        DB::table('admins')->insert(['user_id' => $this->financeAdminUser->id, 'created_by' => $this->financeAdminUser->id]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق التذاكر',
            'email'        => 'driver.ticket.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 4,
            'is_active'    => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر التذاكر',
            'email'        => 'parent.ticket.' . uniqid() . '@darby.test',
            'phone_number' => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);
        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);
    }

    // =========================================================
    // إنشاء التذاكر من جهة ولي الأمر
    // =========================================================

    public function test_parent_can_create_general_ticket(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/support-tickets', [
            'category'    => 'general',
            'description' => 'استفسار عام حول طريقة عمل التطبيق.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.category', 'general');
        $response->assertJsonPath('data.scope', 'operations');
        $this->assertDatabaseHas('support_tickets', [
            'user_id'      => $this->parentUser->id,
            'creator_role' => 'parent',
            'category'     => 'general',
            'scope'        => 'operations',
        ]);
    }

    public function test_parent_can_create_financial_ticket_linked_to_invoice(): void
    {
        $invoice = Invoice::create([
            'parent_id'      => $this->parentUser->id,
            'driver_id'      => $this->driver->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'amount'         => 300.00,
            'status'         => 'paid',
            'type'           => 'monthly',
            'due_date'       => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/support-tickets', [
            'category'                  => 'financial',
            'description'               => 'المبلغ المخصوم من الفاتورة غير صحيح.',
            'financial_reference_type'  => 'invoice',
            'financial_reference_id'    => $invoice->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.scope', 'financial');
        $this->assertDatabaseHas('support_tickets', [
            'user_id'            => $this->parentUser->id,
            'category'           => 'financial',
            'scope'              => 'financial',
            'referenceable_type' => Invoice::class,
            'referenceable_id'   => $invoice->id,
        ]);
    }

    public function test_parent_cannot_reference_invoice_belonging_to_another_parent(): void
    {
        $otherParentUser = User::create([
            'full_name'    => 'ولي أمر آخر',
            'email'        => 'other.ticket.' . uniqid() . '@darby.test',
            'phone_number' => '094' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);
        $invoice = Invoice::create([
            'parent_id'      => $otherParentUser->id,
            'driver_id'      => $this->driver->id,
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'amount'         => 300.00,
            'status'         => 'paid',
            'type'           => 'monthly',
            'due_date'       => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/support-tickets', [
            'category'                 => 'financial',
            'description'              => 'محاولة الوصول لفاتورة ليست لي.',
            'financial_reference_type' => 'invoice',
            'financial_reference_id'   => $invoice->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_parent_can_create_party_ticket_against_driver_without_trip(): void
    {
        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/support-tickets', [
            'category'        => 'party',
            'description'     => 'شكوى عامة على سلوك السائق دون تحديد رحلة معينة.',
            'target_user_id'  => $this->driverUser->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', [
            'user_id'         => $this->parentUser->id,
            'category'        => 'party',
            'target_role'     => 'driver',
            'target_user_id'  => $this->driverUser->id,
            'referenceable_type' => null,
        ]);
    }

    // =========================================================
    // إنشاء التذاكر من جهة السائق
    // =========================================================

    public function test_driver_can_create_party_ticket_against_parent(): void
    {
        $response = $this->actingAs($this->driverUser)->postJson('/api/driver/support-tickets', [
            'category'        => 'party',
            'description'     => 'ولي الأمر يتأخر دائماً عن موعد الاستلام.',
            'target_user_id'  => $this->parentUser->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', [
            'user_id'        => $this->driverUser->id,
            'creator_role'   => 'driver',
            'target_role'    => 'parent',
            'target_user_id' => $this->parentUser->id,
        ]);
    }

    // =========================================================
    // مسار الأدمن: التوجيه بين الأقسام
    // =========================================================

    public function test_operations_admin_sees_general_ticket_but_not_direct_financial_ticket(): void
    {
        $general = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'general', 'description' => 'وصف عام.',
            'status' => 'open', 'scope' => 'operations',
        ]);
        $financial = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'financial', 'description' => 'وصف مالي.',
            'status' => 'open', 'scope' => 'financial',
        ]);

        $response = $this->actingAs($this->opsAdminUser)->getJson('/api/admin/support-tickets/operations');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($general->id));
        $this->assertFalse($ids->contains($financial->id));
    }

    public function test_operations_admin_can_transfer_ticket_to_financial(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'party', 'target_role' => 'driver', 'target_user_id' => $this->driverUser->id,
            'description' => 'نزاع يحتاج تعويضاً مالياً.',
            'status' => 'open', 'scope' => 'operations',
        ]);

        $response = $this->actingAs($this->opsAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/transfer-to-financial", [
                'note' => 'يستوجب تعويض ولي الأمر عن الضرر الحاصل.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'scope' => 'financial']);
    }

    public function test_operations_admin_can_apply_penalty_to_hide_driver_from_search(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'party', 'target_role' => 'driver', 'target_user_id' => $this->driverUser->id,
            'description' => 'سلوك متكرر غير لائق من السائق.',
            'status' => 'open', 'scope' => 'operations',
        ]);

        $response = $this->actingAs($this->opsAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/apply-penalty", [
                'penalty_action' => 'hide_driver_from_search',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'hidden_from_search' => 1]);
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'penalty_action' => 'hide_driver_from_search']);
    }

    // =========================================================
    // مسار الأدمن المالي: التسوية الفعلية
    // =========================================================

    public function test_finance_admin_can_execute_credit_settlement_to_driver_wallet(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'financial', 'description' => 'تعويض مستحق للسائق.',
            'status' => 'open', 'scope' => 'financial',
        ]);

        $balanceBefore = $this->driver->balance;

        $response = $this->actingAs($this->financeAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/execute-settlement", [
                'direction'      => 'credit',
                'party_role'     => 'driver',
                'party_user_id'  => $this->driverUser->id,
                'amount'         => 50.00,
                'note'           => 'تعويض عن رحلة ملغاة بالخطأ.',
            ]);

        $response->assertStatus(200);
        $this->assertEquals($balanceBefore + 5000, $this->driver->fresh()->balance);
        $this->assertDatabaseHas('financial_ledger', [
            'type' => 'ticket_settlement_credit',
            'amount' => 5000,
        ]);
    }

    public function test_finance_admin_settlement_debit_fails_when_balance_insufficient(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'financial', 'description' => 'خصم مبلغ كبير جداً.',
            'status' => 'open', 'scope' => 'financial',
        ]);

        $response = $this->actingAs($this->financeAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/execute-settlement", [
                'direction'      => 'debit',
                'party_role'     => 'driver',
                'party_user_id'  => $this->driverUser->id,
                'amount'         => 999999.00,
            ]);

        $response->assertStatus(422);
    }

    // =========================================================
    // الإغلاق والتوثيق
    // =========================================================

    public function test_admin_can_close_ticket_with_resolution_note(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'general', 'description' => 'استفسار بسيط.',
            'status' => 'open', 'scope' => 'operations',
        ]);

        $response = $this->actingAs($this->opsAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/close", [
                'resolution_note' => 'تم الرد على الاستفسار وتوضيح آلية العمل.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id, 'status' => 'closed',
        ]);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_closed_ticket_rejects_new_reply_from_owner(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->parentUser->id, 'creator_role' => 'parent',
            'category' => 'general', 'description' => 'استفسار مغلق.',
            'status' => 'closed', 'scope' => 'operations', 'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/support-tickets/{$ticket->id}/reply", [
                'message' => 'أريد إضافة تفاصيل إضافية.',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_create_ticket_with_only_category_and_description(): void
    {
        $response = $this->actingAs($this->driverUser)->postJson('/api/driver/support-tickets', [
            'category'    => 'technical',
            'description' => 'تطبيق السائق يواجه صعوبة في تحديث موقع الـ GPS.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.category', 'technical');
        $this->assertDatabaseHas('support_tickets', [
            'user_id'      => $this->driverUser->id,
            'creator_role' => 'driver',
            'category'     => 'technical',
            'status'       => 'open',
        ]);
    }

    public function test_admin_can_update_status_and_add_resolution_note(): void
    {
        $ticket = SupportTicket::create([
            'user_id'      => $this->parentUser->id,
            'creator_role' => 'parent',
            'category'     => 'technical',
            'description'  => 'لا أستطيع رؤية تتبع الحافلة مباشرة.',
            'status'       => 'open',
            'scope'        => 'operations',
        ]);

        $response = $this->actingAs($this->opsAdminUser)
            ->postJson("/api/admin/support-tickets/{$ticket->id}/status", [
                'status'          => 'resolved',
                'resolution_note' => 'تم إعادة تنشيط صلاحيات الموقع وربط إحداثيات السائق بنجاح.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'resolved');
        $response->assertJsonPath('data.resolution_note', 'تم إعادة تنشيط صلاحيات الموقع وربط إحداثيات السائق بنجاح.');

        $this->assertDatabaseHas('support_tickets', [
            'id'              => $ticket->id,
            'status'          => 'resolved',
            'resolution_note' => 'تم إعادة تنشيط صلاحيات الموقع وربط إحداثيات السائق بنجاح.',
        ]);
    }

    public function test_driver_can_view_incoming_tickets_and_reply_to_resolve(): void
    {
        $ticket = SupportTicket::create([
            'user_id'        => $this->parentUser->id,
            'creator_role'   => 'parent',
            'category'       => 'driver',
            'target_role'    => 'driver',
            'target_user_id' => $this->driverUser->id,
            'description'    => 'السائق تأخر عن موعد الوصول 15 دقيقة.',
            'status'         => 'open',
            'scope'          => 'operations',
        ]);

        // السائق يستعرض التذاكر الواردة ضده
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/support-tickets?incoming=1');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ticket->id));

        // السائق يرسل رداً لتوضيح الموقف وحل المشكلة
        $replyResponse = $this->actingAs($this->driverUser)
            ->postJson("/api/driver/support-tickets/{$ticket->id}/reply", [
                'message' => 'نعتذر عن التأخير، كان هناك ازدحام مروري بسبب حادث في الطريق الرئيسي.',
            ]);

        $replyResponse->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticket->id,
            'is_admin'  => false,
            'message'   => 'نعتذر عن التأخير، كان هناك ازدحام مروري بسبب حادث في الطريق الرئيسي.',
        ]);
    }

    public function test_admin_can_view_all_tickets_list(): void
    {
        SupportTicket::create([
            'user_id'      => $this->parentUser->id,
            'creator_role' => 'parent',
            'category'     => 'general',
            'description'  => 'تذكرة استفسار عامة.',
            'status'       => 'open',
            'scope'        => 'operations',
        ]);

        $response = $this->actingAs($this->opsAdminUser)->getJson('/api/admin/support-tickets');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }
}
