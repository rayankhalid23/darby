<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\PaymentMethod;
use App\Models\Driver\DriverRechargeRequest;
use App\Models\Shared\RechargeRequest;

class FinancialWalletAndRechargeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected PaymentMethod $paymentMethodMock;
    protected PaymentMethod $paymentMethodManual;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->adminUser = User::create([
            'full_name'     => 'مدير المالية التجريبي',
            'email'         => 'admin.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '090' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق الشحن التجريبي',
            'email'         => 'driver.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر الشحن التجريبي',
            'email'         => 'parent.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // وسيلة دفع محاكاة إلكترونية (سداد)
        $this->paymentMethodMock = PaymentMethod::create([
            'name_ar'         => 'سداد التجريبي',
            'name_en'         => 'Sadad Mock Test',
            'code'            => 'sadad_test_' . uniqid(),
            'target_audience' => 'both',
            'processing_type' => 'instant_simulation',
            'min_amount'      => 1.00,
            'max_amount'      => 5000.00,
            'is_active'       => true,
            'sort_order'      => 1,
        ]);

        // وسيلة دفع تحويل يدوي (المصرف التجاري)
        $this->paymentMethodManual = PaymentMethod::create([
            'name_ar'         => 'تحويل مصرفي تجريبي',
            'name_en'         => 'Bank Transfer Test',
            'code'            => 'bank_test_' . uniqid(),
            'target_audience' => 'both',
            'processing_type' => 'manual_proof',
            'account_name'    => 'شركة دربي للتقنية',
            'account_number'  => '020-998877',
            'iban'            => 'LY00NCBL0200998877',
            'min_amount'      => 10.00,
            'max_amount'      => 50000.00,
            'is_active'       => true,
            'sort_order'      => 2,
        ]);
    }

    // =========================================================
    // 1. إدارة طرق الدفع (لوحة الأدمن)
    // =========================================================

    public function test_admin_can_list_and_create_payment_methods(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/payment-methods');
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $newCode = 'test_pay_' . uniqid();
        $createResponse = $this->actingAs($this->adminUser)->postJson('/api/admin/payment-methods', [
            'name_ar'         => 'بطاقة تداول تجريبية',
            'name_en'         => 'Tadawul Test Card',
            'code'            => $newCode,
            'target_audience' => 'parent',
            'processing_type' => 'instant_simulation',
            'min_amount'      => 5.00,
            'max_amount'      => 10000.00,
            'instructions_ar' => 'الدفع الإلكتروني التجريبي.',
            'is_active'       => true,
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonPath('status', true);
        $createResponse->assertJsonPath('data.code', $newCode);

        $this->assertDatabaseHas('payment_methods', ['code' => $newCode]);
    }

    public function test_admin_can_toggle_payment_method_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patchJson("/api/admin/payment-methods/{$this->paymentMethodMock->id}/toggle-status");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_active', false);

        $this->assertEquals(false, $this->paymentMethodMock->fresh()->is_active);
    }

    // =========================================================
    // 2. دورة شحن ولي الأمر (المحاكاة اللحظية الفورية)
    // =========================================================

    public function test_parent_can_fetch_active_payment_methods(): void
    {
        $response = $this->actingAs($this->parentUser)->getJson('/api/parent/wallet/payment-methods');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_parent_can_initiate_and_complete_mock_recharge_instantly(): void
    {
        $initialBalance = $this->parent->fresh()->balance / 100;

        // 1. بدء جلسة الشحن
        $initiateResponse = $this->actingAs($this->parentUser)->postJson('/api/parent/wallet/recharge/initiate', [
            'amount'            => 150.00,
            'payment_method_id' => $this->paymentMethodMock->id,
        ]);

        $initiateResponse->assertStatus(201);
        $initiateResponse->assertJsonPath('success', true);
        $sessionToken = $initiateResponse->json('data.session_token');
        $this->assertNotEmpty($sessionToken);

        // 2. تنفيذ المحاكاة الفورية (Mock Pay)
        $payResponse = $this->actingAs($this->parentUser)->postJson('/api/parent/wallet/recharge/mock-pay', [
            'session_token' => $sessionToken,
            'card_number'   => '4111222233334444',
            'card_holder'   => 'Taha Al-Ghamoudi',
            'expiry_date'   => '12/28',
            'cvv'           => '123',
        ]);

        $payResponse->assertStatus(200);
        $payResponse->assertJsonPath('success', true);
        $this->assertEquals(150.00, (float) $payResponse->json('data.amount'));

        // التحقق من تحديث رصيد ولي الأمر في قاعدة البيانات
        $newBalance = $this->parent->fresh()->balance / 100;
        $this->assertEquals($initialBalance + 150.00, $newBalance);

        // التحقق من إنشاء الفاتورة
        $this->assertDatabaseHas('invoices', [
            'parent_id' => $this->parentUser->id,
            'amount'    => 150.00,
            'status'    => 'paid',
        ]);
    }

    // =========================================================
    // 3. دورة شحن السائق وموافقة الأدمن (النظام اليدوي المحكوم)
    // =========================================================

    public function test_driver_can_submit_manual_recharge_request(): void
    {
        $refNumber = 'TRF-TEST-' . rand(100000, 999999);

        $response = $this->actingAs($this->driverUser)->postJson('/api/driver/wallet/recharge-request', [
            'amount'            => 300.00,
            'payment_method_id' => $this->paymentMethodManual->id,
            'reference_number'  => $refNumber,
            'notes'             => 'تحويل عبر تطبيق المصرف التجاري.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertEquals(300.00, (float) $response->json('data.amount'));

        $this->assertDatabaseHas('driver_recharge_requests', [
            'driver_id'        => $this->driver->id,
            'reference_number' => $refNumber,
            'status'           => 'pending',
        ]);
    }

    public function test_admin_can_approve_driver_recharge_and_credit_wallet(): void
    {
        $initialDriverBalance = $this->driver->fresh()->balance / 100;

        $recharge = DriverRechargeRequest::create([
            'driver_id'         => $this->driver->id,
            'payment_method_id' => $this->paymentMethodManual->id,
            'amount'            => 250.00,
            'reference_number'  => 'REF-APP-' . uniqid(),
            'status'            => DriverRechargeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/driver-recharges/{$recharge->id}/approve", [
                'notes' => 'تم التأكد من وصول الحوالة في الحساب البنكي.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'approved');

        // التحقق من زيادة رصيد السائق في المحفظة
        $newDriverBalance = $this->driver->fresh()->balance / 100;
        $this->assertEquals($initialDriverBalance + 250.00, $newDriverBalance);

        $this->assertDatabaseHas('driver_recharge_requests', [
            'id'       => $recharge->id,
            'status'   => 'approved',
            'admin_id' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_reject_driver_recharge_with_reason(): void
    {
        $initialDriverBalance = $this->driver->fresh()->balance / 100;

        $recharge = DriverRechargeRequest::create([
            'driver_id'         => $this->driver->id,
            'payment_method_id' => $this->paymentMethodManual->id,
            'amount'            => 100.00,
            'reference_number'  => 'REF-REJ-' . uniqid(),
            'status'            => DriverRechargeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/driver-recharges/{$recharge->id}/reject", [
                'rejection_reason' => 'رقم الحوالة غير مطابق لكشف الحساب المصرفي.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.status', 'rejected');
        $response->assertJsonPath('data.rejection_reason', 'رقم الحوالة غير مطابق لكشف الحساب المصرفي.');

        // التأكد أن رصيد السائق لم يتغير
        $newDriverBalance = $this->driver->fresh()->balance / 100;
        $this->assertEquals($initialDriverBalance, $newDriverBalance);

        $this->assertDatabaseHas('driver_recharge_requests', [
            'id'               => $recharge->id,
            'status'           => 'rejected',
            'rejection_reason' => 'رقم الحوالة غير مطابق لكشف الحساب المصرفي.',
        ]);
    }
}
