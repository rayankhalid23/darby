<?php

namespace Tests\Feature;

use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\SubscriptionRequest;
use App\Models\User;
use App\Services\Driver\WithdrawalService;
use App\Services\Parent\WalletRechargeService;
use App\Services\Shared\FinancialLedgerService;
use App\Services\Shared\SubscriptionRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * حارس السلامة المالية للنظام.
 *
 * ⚠️ هذا الاختبار هو ما كان غائباً: لم يكن هناك أي اختبار يتحقق من أن مجموع
 * الحركات المالية يساوي صفراً بعد دورة كاملة. غيابه هو ما سمح بأن يعيش في
 * الكود مساران يخلقان المال من العدم (استرجاع كامل بعد صرف جزئي، وحل نزاع
 * بلا خصم من أي حوض) وحوضٌ لا يتحرك مع محافظ السائقين.
 *
 * القاعدة المطبَّقة هنا: **لا يُخلق مال ولا يُفقد**. مجموع (المحافظ + الأحواض)
 * قبل أي عملية داخلية = مجموعه بعدها. المال يدخل فقط بالشحن ويخرج فقط بالسحب.
 */
class FinancialSolvencyInvariantTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        PricingSetting::query()->delete();
        PricingSetting::create([
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق حارس السلامة',
            'email'         => 'driver.solv.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(3)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر حارس السلامة',
            'email'         => 'parent.solv.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);
    }

    /**
     * إجمالي المال داخل المنظومة.
     *
     * ملاحظة على `driver_available_pool`: هو **مرآة** لمجموع محافظ السائقين لا
     * حوض يحمل مالاً مستقلاً عنها، فلا يُجمع هنا وإلا حُسب نفس المبلغ مرتين.
     * دوره أن يكون رقماً مستقلاً تُقارن به المحافظ في فحص السلامة المالية.
     */
    private function totalMoneyInSystem(): int
    {
        $vault = MasterEscrowVault::getVault()->fresh();

        return (int) $this->parent->fresh()->balance
            + (int) $this->driver->fresh()->balance
            + (int) $vault->parents_escrow_pool
            + (int) $vault->driver_pending_pool
            + (int) ($vault->pending_withdrawal_pool ?? 0)
            + (int) $vault->platform_revenue_pool
            + (int) $vault->penalty_pool;
    }

    /**
     * دورة كاملة: حجز اشتراك ← صرف حصص رحلات ← إلغاء واسترجاع المتبقي.
     * لا يجوز أن يتغيّر إجمالي المال في المنظومة عبر أي من هذه الخطوات.
     */
    public function test_subscription_hold_settle_and_refund_conserve_total_money(): void
    {
        $this->parent->deposit(20000); // 200 د.ل — الشحن هو المدخل الوحيد للمال

        $totalAfterFunding = $this->totalMoneyInSystem();

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
        ]);

        // 1) حجز الأمانة: المال ينتقل من المحفظة إلى الحوض ولا يتغيّر الإجمالي.
        $vault = MasterEscrowVault::getVault();
        $this->parent->withdraw(10000);
        $vault->increment('parents_escrow_pool', 10000);

        $finance = PlatformFinance::create([
            'subscription_request_id'    => $request->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 100.00,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => 8.00,
            'driver_net_amount'          => 92.00,
            'expected_trips_count'       => 10,
            'settled_trips_count'        => 0,
            'settled_amount'             => 0,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        $this->assertEquals($totalAfterFunding, $this->totalMoneyInSystem(), 'الحجز غيّر إجمالي المال في المنظومة.');

        // 2) صرف حصص 3 رحلات: من الأمانات إلى محفظة السائق + إيراد المنصة.
        for ($i = 0; $i < 3; $i++) {
            $shareCents      = 1000;                       // 10 د.ل للرحلة
            $commissionCents = (int) round($shareCents * 0.08);
            $driverNetCents  = $shareCents - $commissionCents;

            $vault->decrement('parents_escrow_pool', $shareCents);
            $vault->increment('platform_revenue_pool', $commissionCents);
            $this->driver->deposit($driverNetCents);
            $vault->increment('driver_available_pool', $driverNetCents);
        }

        $finance->update(['settled_trips_count' => 3, 'settled_amount' => 30.00]);

        $this->assertEquals($totalAfterFunding, $this->totalMoneyInSystem(), 'تسوية الرحلات غيّرت إجمالي المال في المنظومة.');

        // 3) الإلغاء: يُسترجع المتبقي (70 د.ل) فقط، لا كامل الاشتراك.
        $escrowBeforeRefund = (int) MasterEscrowVault::getVault()->fresh()->parents_escrow_pool;

        $result = app(SubscriptionRequestService::class)
            ->refundHeldFundsOnCancellation($request->id, 'system');

        $this->assertEquals(70.00, $result['refund_amount']);
        $this->assertEquals($totalAfterFunding, $this->totalMoneyInSystem(), 'الاسترجاع خلق أو أتلف مالاً في المنظومة.');

        // حوض الأمانات فرغ من حصة هذا الاشتراك بالضبط (7000 قرش) لا أكثر.
        $this->assertEquals(
            $escrowBeforeRefund - 7000,
            (int) MasterEscrowVault::getVault()->fresh()->parents_escrow_pool
        );
    }

    /**
     * حوض أرصدة السائقين المتاحة مرآة لمجموع محافظهم — قبل السحب وبعده وبعد رفضه.
     */
    public function test_driver_pool_mirrors_wallets_through_the_withdrawal_cycle(): void
    {
        $ledger = app(FinancialLedgerService::class);
        $vault  = MasterEscrowVault::getVault();

        $this->driver->deposit(10000); // 100 د.ل
        $vault->increment('driver_available_pool', 10000);

        $poolBefore    = (int) $vault->fresh()->driver_available_pool;
        $pendingBefore = (int) ($vault->fresh()->pending_withdrawal_pool ?? 0);
        $walletBefore  = (int) $this->driver->fresh()->balance;
        $totalBefore   = $this->totalMoneyInSystem();

        $withdrawal = app(WithdrawalService::class)
            ->requestWithdrawal($this->driver->id, 50.00);

        // الطلب يخرج المال من المحفظة والحوض معاً إلى حوض السحوبات المعلّقة.
        $this->assertEquals($walletBefore - 5000, (int) $this->driver->fresh()->balance);
        $this->assertEquals($poolBefore - 5000, (int) $vault->fresh()->driver_available_pool);
        $this->assertEquals($pendingBefore + 5000, (int) $vault->fresh()->pending_withdrawal_pool);
        $this->assertEquals($totalBefore, $this->totalMoneyInSystem(), 'طلب السحب غيّر إجمالي المال في المنظومة.');

        // الرفض يعيد المال إلى المحفظة والحوض كما كانا بالضبط.
        app(WithdrawalService::class)
            ->rejectWithdrawal($withdrawal->id, (int) DB::table('admins')->value('id'), 'بيانات الحساب غير مكتملة');

        $this->assertEquals($walletBefore, (int) $this->driver->fresh()->balance);
        $this->assertEquals($poolBefore, (int) $vault->fresh()->driver_available_pool);
        $this->assertEquals($pendingBefore, (int) $vault->fresh()->pending_withdrawal_pool);
        $this->assertEquals($totalBefore, $this->totalMoneyInSystem(), 'رفض السحب غيّر إجمالي المال في المنظومة.');
    }

    /**
     * الموافقة على السحب تُخرج المال من المنظومة مرة واحدة فقط.
     */
    public function test_approved_withdrawal_leaves_the_system_exactly_once(): void
    {
        $vault = MasterEscrowVault::getVault();

        $this->driver->deposit(10000);
        $vault->increment('driver_available_pool', 10000);

        $totalBefore = $this->totalMoneyInSystem();

        $pendingBefore = (int) ($vault->fresh()->pending_withdrawal_pool ?? 0);

        $withdrawal = app(WithdrawalService::class)
            ->requestWithdrawal($this->driver->id, 50.00);

        $adminId = (int) DB::table('admins')->value('id');
        app(WithdrawalService::class)->approveWithdrawal($withdrawal->id, $adminId);

        // 5000 قرش خرجت إلى الحساب البنكي — ولا شيء غيرها.
        $this->assertEquals($totalBefore - 5000, $this->totalMoneyInSystem());

        // وحوض السحوبات المعلّقة عاد إلى ما كان عليه: لم يبقَ فيه مال معلّق.
        $this->assertEquals($pendingBefore, (int) $vault->fresh()->pending_withdrawal_pool);
    }

    /**
     * شحن المحفظة لا يمسّ حوض الأمانات — المال في المحفظة لم يُحجز مقابل أي خدمة بعد.
     */
    public function test_wallet_recharge_does_not_touch_the_escrow_pool(): void
    {
        $escrowBefore = (int) MasterEscrowVault::getVault()->parents_escrow_pool;

        $service = app(WalletRechargeService::class);
        $session = $service->initiateRecharge($this->parentUser->id, 75.00, 'sadad');
        $service->processMockPayment($session['session_token']);

        $this->assertEquals(7500, (int) $this->parent->fresh()->balance);
        $this->assertEquals(
            $escrowBefore,
            (int) MasterEscrowVault::getVault()->fresh()->parents_escrow_pool,
            'الشحن زاد حوض الأمانات، وهو ما يجعل فحص السلامة المالية مستحيل التحقق.'
        );
    }
}
