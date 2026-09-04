<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Services\Trip\TripLifecycleService;

class SingleDaySubscriptionFinancialLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected School $school;
    protected int $addressId;
    protected Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // Pricing Settings
        PricingSetting::firstOrCreate([], [
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
        ]);

        // 1. Driver User & Model
        $this->driverUser = User::create([
            'full_name'     => 'سائق دورة الحياة المالية',
            'email'         => 'driver.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'          => $this->driverUser->id,
            'national_id'      => 'NAT' . rand(100000, 999999),
            'license_number'   => 'LIC' . rand(100000, 999999),
            'license_expiry'   => now()->addYears(3)->format('Y-m-d'),
            'status'           => 'Approved',
            'current_lat'      => 32.8872,
            'current_lng'      => 13.1932,
            'morning_go'       => 1,
            'morning_return'   => 1,
            'afternoon_go'     => 1,
            'afternoon_return' => 1,
        ]);

        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2023,
            'color'           => 'أبيض',
            'plate_number'    => 'FIN-' . rand(1000, 9999),
            'capacity_manual' => 12,
            'capacity_ai'     => 12,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver->id,
                'slot'           => $slot,
                'total_seats'    => 12,
                'reserved_seats' => 0,
            ]);
        }

        // 2. Parent User & Model
        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر دورة الحياة المالية',
            'email'         => 'parent.fin.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('secret123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // 3. School
        $this->school = School::create([
            'name'    => 'مدرسة داربي المالية',
            'address' => 'طريق الشط، طرابلس',
            'lat'     => 32.8950,
            'lng'     => 13.1950,
            'status'  => 'active',
        ]);

        // 4. Address
        $this->addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'المنزل الرئيسي',
            'lat'        => 32.8800,
            'lng'        => 13.1800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Child
        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'school_id' => $this->school->id,
            'full_name' => 'أحمد طفل الاشتراك اليومي',
            'birth_date'=> '2017-04-12',
            'gender'    => 'male',
            'grade'     => 2,
        ]);
    }

    /**
     * 1. منع إرسال طلب اشتراك يومي إذا كان رصيد ولي الأمر غير كافٍ وإرجاع رسالة خطأ واضحة
     */
    public function test_parent_cannot_create_single_day_request_if_wallet_balance_is_insufficient(): void
    {
        // الرصيد 0 أو غير كافٍ
        $this->assertEquals(0, $this->parent->balance);

        $payload = [
            'driver_id'   => $this->driver->id,
            'total_price' => 25.00,
            'children'    => [
                [
                    'child_id'          => $this->child->id,
                    'subscription_type' => 'single_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addDays(2)->format('Y-m-d'),
                    'distance_km'       => 5.0,
                    'trip_price'        => 25.0,
                    'price_per_child'   => 25.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/requests', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('رصيد المحفظة غير كافٍ', $response->json('message'));

        $this->assertDatabaseMissing('requests', [
            'parent_id' => $this->parent->id,
            'driver_id' => $this->driver->id,
        ]);
    }

    /**
     * 2. السماح بإرسال طلب الاشتراك اليومي إذا كان الرصيد كافياً مع عدم حجز أو خصم أي مبلغ في هذه المرحلة
     */
    public function test_parent_can_create_single_day_request_with_sufficient_balance_without_deduction(): void
    {
        // شحن رصيد ولي الأمر بـ 50 دينار (5000 قرش)
        $this->parent->deposit(5000);
        $this->assertEquals(5000, $this->parent->fresh()->balance);

        $payload = [
            'driver_id'   => $this->driver->id,
            'total_price' => 25.00,
            'children'    => [
                [
                    'child_id'          => $this->child->id,
                    'subscription_type' => 'single_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addDays(2)->format('Y-m-d'),
                    'distance_km'       => 5.0,
                    'trip_price'        => 25.0,
                    'price_per_child'   => 25.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/requests', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // التأكد من أن الرصيد لم يُخصم ولم يُحجز بعد (لا يزال 5000 قرش = 50 د.ل)
        $this->assertEquals(5000, $this->parent->fresh()->balance);

        // التأكد من عدم وجود أي سجل محجوز في مالية المنصة بعد
        $this->assertDatabaseMissing('platform_finances', [
            'parent_id' => $this->parent->id,
        ]);
    }

    /**
     * 3. عند قبول السائق للطلب يتم خصم المبلغ من ولي الأمر وحجزه في الأمانات وسجلات مالية المنصة
     */
    public function test_driver_accepting_single_day_request_deducts_and_holds_funds_in_escrow_and_platform_finance(): void
    {
        // شحن رصيد ولي الأمر بـ 50 د.ل (5000 قرش)
        $this->parent->deposit(5000);
        $escrowBefore = (int) MasterEscrowVault::getVault()->parents_escrow_pool;

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 25.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 25.00,
            'status'                      => SubscriptionRequest::STATUS_PENDING,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $request->id,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->addDays(2)->format('Y-m-d'),
            'end_date'                    => now()->addDays(2)->format('Y-m-d'),
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 25.00,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 25.00,
            'driver_net_price'            => 23.00,
        ]);

        // قبول السائق للطلب
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/requests/{$request->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // تم خصم 25 د.ل (2500 قرش) من محفظة ولي الأمر -> المتبقي 25 د.ل (2500 قرش)
        $this->assertEquals(2500, $this->parent->fresh()->balance);

        // تم زيادة مسبح الأمانات في الخزينة المركزية بـ 2500 قرش.
        // الفحص بالفارق لا بالقيمة المطلقة: الخزينة سجل واحد مشترك يحمل رصيد
        // النظام كله، فتأكيد قيمة مطلقة يفشل مع أي حالة قائمة في قاعدة البيانات.
        $vault = MasterEscrowVault::getVault();
        $this->assertEquals($escrowBefore + 2500, (int) $vault->fresh()->parents_escrow_pool);

        // تم إنشاء سجل مالية المنصة بحالة held وتوزيع القيم الصحيحة
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id'    => $request->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 25.00,
            'platform_commission_amount' => 2.00, // 8% من 25
            'driver_net_amount'          => 23.00,
            'status'                     => 'held',
        ]);
    }

    /**
     * 4. عند اكتمال الرحلة يتم تحويل مستحقات السائق واقتطاع عمولة المنصة وتسجيلها في مالية المنصة
     */
    public function test_completing_trip_settles_driver_payout_and_platform_commission(): void
    {
        $this->parent->deposit(5000);

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 25.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 25.00,
            'status'                      => SubscriptionRequest::STATUS_PENDING,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $request->id,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->toDateString(),
            'end_date'                    => now()->toDateString(),
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 25.00,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 25.00,
            'driver_net_price'            => 23.00,
        ]);

        // قبول السائق للطلب -> المبلغ الآن محجوز في الأمانات
        $this->actingAs($this->driverUser)
            ->putJson("/api/driver/requests/{$request->id}/status", ['status' => 'accepted']);

        // 📌 الخزينة المركزية صف مفرد دائم في قاعدة البيانات وقد يحمل أرصدة سابقة حقيقية،
        // لذا نقيس الفرق قبل/بعد بدل القيم المطلقة حتى لا يرتبط الاختبار بحالة القاعدة.
        $revenueBefore = (int) MasterEscrowVault::getVault()->platform_revenue_pool;
        $escrowBefore  = (int) MasterEscrowVault::getVault()->parents_escrow_pool;
        $driverBalanceBefore = (int) $this->driver->fresh()->balance;

        // إنشاء وبدء رحلة للسائق مرتبطة فعلياً بالطفل صاحب الاشتراك
        // (محطة الرحلة هي ما يربط التسوية المالية بالاشتراك الصحيح دون غيره)
        $trip = Trip::create([
            'driver_id'    => $this->driver->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        \App\Models\Shared\TripStop::create([
            'trip_id'        => $trip->id,
            'child_id'       => $this->child->id,
            'stop_type'      => 'home',
            'lat'            => 32.88,
            'lng'            => 13.19,
            'label'          => 'منزل الطفل',
            'sequence_order' => 1,
            'status'         => \App\Models\Shared\TripStop::STATUS_DELIVERED_HOME,
        ]);

        // إنهاء الرحلة عبر TripLifecycleService
        $tripService = app(TripLifecycleService::class);
        $result = $tripService->completeTrip($trip->id);

        $this->assertEquals('success', $result['status']);

        // 💡 التسوية صارت تناسبية لكل رحلة: الاشتراك (25 د.ل) يغطي اتجاهين (trip_direction = both)
        // أي رحلتين، وقد نُفّذت رحلة واحدة فقط — فتُصرف حصتها وحدها (12.50 د.ل) وتبقى حصة
        // رحلة العودة محجوزة في الأمانات. سابقاً كان يُصرف المبلغ كاملاً على أول رحلة.
        $this->assertEquals($driverBalanceBefore + 1150, (int) $this->driver->fresh()->balance);

        // عمولة المنصة تناسبية أيضاً: 8% من 12.50 د.ل = 1.00 د.ل = 100 قرش
        $vault = MasterEscrowVault::getVault();
        $this->assertEquals($revenueBefore + 100, (int) $vault->platform_revenue_pool);
        // يخرج من مسبح الأمانات حصة رحلة واحدة فقط (1250 قرش) لا المبلغ كاملاً
        $this->assertEquals($escrowBefore - 1250, (int) $vault->parents_escrow_pool);

        // الأمانة تبقى مفتوحة (held) حتى تُنفَّذ كل الرحلات المدفوعة
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $request->id,
            'status'                  => 'held',
            'settled_trips_count'     => 1,
            'expected_trips_count'    => 2,
        ]);
    }

    /**
     * 5. عند إلغاء الاشتراك قبل تحرك السائق الفعلي يُعاد كامل المبلغ فوراً لمحفظة ولي الأمر (100%)
     */
    public function test_cancelling_subscription_before_driver_movement_refunds_100_percent_to_parent(): void
    {
        $this->parent->deposit(5000);
        $escrowBefore = (int) MasterEscrowVault::getVault()->parents_escrow_pool;

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 25.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 25.00,
            'status'                      => SubscriptionRequest::STATUS_PENDING,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $request->id,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->addDays(2)->format('Y-m-d'),
            'end_date'                    => now()->addDays(2)->format('Y-m-d'),
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 25.00,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 25.00,
            'driver_net_price'            => 23.00,
        ]);

        // السائق يقبل -> تم حجز 25 د.ل
        $this->actingAs($this->driverUser)
            ->putJson("/api/driver/requests/{$request->id}/status", ['status' => 'accepted']);

        $activeSub = ActiveSubscription::where('subscription_request_id', $request->id)->first();
        $this->assertNotNull($activeSub);

        // ولي الأمر يلغي الاشتراك قبل تحرك السائق
        $cancelRes = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$activeSub->id}/cancel");

        $cancelRes->assertStatus(200);

        // تم استرجاع كامل المبلغ (2500 قرش) إلى محفظة ولي الأمر -> عادت 5000 قرش (50 د.ل)
        $this->assertEquals(5000, $this->parent->fresh()->balance);

        // سجل مالية المنصة بحالة refunded
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $request->id,
            'status'                  => 'refunded',
            'refunded_amount'         => 25.00,
            'compensation_fee'        => 0.00,
        ]);

        // الحجز خرج من الأمانات كما دخل — فحص بالفارق لأن الخزينة سجل مشترك.
        $vault = MasterEscrowVault::getVault();
        $this->assertEquals($escrowBefore, (int) $vault->fresh()->parents_escrow_pool);
    }

    /**
     * 6. عند إلغاء الرحلة بعد تحرك السائق الفعلي يتم خصم مبلغ رمزي (3 د.ل) كتعويض وقود للسائق واقتطاع عمولة المنصة منه وإرجاع الباقي لولي الأمر
     */
    public function test_cancelling_subscription_after_driver_movement_deducts_nominal_fuel_compensation_and_refunds_remaining(): void
    {
        $this->parent->deposit(5000); // 50 د.ل

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 25.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 25.00,
            'status'                      => SubscriptionRequest::STATUS_PENDING,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $request->id,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->toDateString(),
            'end_date'                    => now()->toDateString(),
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 25.00,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 25.00,
            'driver_net_price'            => 23.00,
        ]);

        // قبول السائق للطلب -> خصم 25 د.ل ونقلها للأمانات (المتبقي لولي الأمر: 25 د.ل = 2500 قرش)
        $this->actingAs($this->driverUser)
            ->putJson("/api/driver/requests/{$request->id}/status", ['status' => 'accepted']);

        $activeSub = ActiveSubscription::where('subscription_request_id', $request->id)->first();

        // 📌 قياس الفرق بدل القيم المطلقة: الخزينة صف مفرد دائم قد يحمل أرصدة سابقة حقيقية.
        $revenueBefore = (int) MasterEscrowVault::getVault()->platform_revenue_pool;
        $escrowBefore  = (int) MasterEscrowVault::getVault()->parents_escrow_pool;
        $driverBalanceBefore = (int) $this->driver->fresh()->balance;

        // بدء رحلة فعلية جارية اليوم للسائق (تحرك السائق بالفعل)
        Trip::create([
            'driver_id'    => $this->driver->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        // ولي الأمر يلغي الاشتراك بعد انطلاق الرحلة
        $cancelRes = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$activeSub->id}/cancel");

        $cancelRes->assertStatus(200);

        // الحسابات:
        // المبلغ الإجمالي: 25 د.ل
        // رسم تعويض المشوار والوقود: 3 د.ل
        // عمولة المنصة على الرسم (8% من 3 د.ل): 0.24 د.ل (24 قرش)
        // صافي تعويض السائق: 3 - 0.24 = 2.76 د.ل (276 قرش)
        // المسترجع لولي الأمر: 25 - 3 = 22.00 د.ل (2200 قرش)
        // رصيد ولي الأمر النهائي: 2500 + 2200 = 4700 قرش (47 د.ل)
        $this->assertEquals(4700, $this->parent->fresh()->balance);

        // رصيد السائق يحتوي على صافي تعويض الوقود (276 قرش = 2.76 د.ل)
        $this->assertEquals($driverBalanceBefore + 276, (int) $this->driver->fresh()->balance);

        // مسبح إيرادات المنصة يحتوي على عمولة التعويض (24 قرش = 0.24 د.ل)
        $vault = MasterEscrowVault::getVault();
        $this->assertEquals($revenueBefore + 24, (int) $vault->platform_revenue_pool);
        // مبلغ الاشتراك المحجوز (2500 قرش) يخرج بالكامل من الأمانات عند الإلغاء
        $this->assertEquals($escrowBefore - 2500, (int) $vault->parents_escrow_pool);

        // سجل مالية المنصة يوثق التعويض والاسترجاع الجزئي.
        //
        // ملاحظة على الدلالة: platform_commission_amount و driver_net_amount
        // يحملان **خطة** الاشتراك كما اتُّفق عليها عند القبول (25 د.ل ← عمولة
        // 2.00 وصافي 23.00) ولا يُكتب فوقهما. كان مسار الإلغاء يستبدلهما بقيم
        // التعويض وحدها، فتضيع الخطة ويضيع معها ما صُرف فعلاً عن رحلات منفّذة
        // في الاشتراكات الشهرية. المصروف الفعلي يُقرأ من compensation_fee و
        // settled_amount ومن جدول platform_finance_trip_settlements.
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id'    => $request->id,
            'status'                     => 'partially_refunded',
            'compensation_fee'           => 3.00,
            'refunded_amount'            => 22.00,
            'platform_commission_amount' => 2.00,  // الخطة، سليمة كما هي
            'driver_net_amount'          => 23.00, // الخطة، سليمة كما هي
        ]);
    }

    /**
     * ⚠️ حارس انحدار للخلل الأخطر: إلغاء اشتراك نُفّذ جزء من رحلاته يجب أن
     * يسترجع **المتبقي في الأمانة فقط**، لا كامل قيمة الاشتراك.
     *
     * سجل PlatformFinance يبقى بحالة `held` طوال التسوية الجزئية، وكان مسار
     * الاسترجاع يعتمد على total_amount ويتجاهل settled_amount، فيدفع النظام
     * ١٢٥٪ من قيمة الاشتراك: السائق قبض حصص الرحلات المنفّذة وولي الأمر
     * استرجع ١٠٠٪، والفرق يُستنزف من أمانات أولياء أمور آخرين.
     */
    public function test_cancelling_partially_settled_subscription_refunds_only_the_remaining_escrow(): void
    {
        $this->parent->deposit(5000);

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
        ]);

        // أمانة اشتراك من 10 رحلات، صُرفت حصص 4 منها (40 د.ل) والباقي 60 د.ل
        $finance = PlatformFinance::create([
            'subscription_request_id'    => $request->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 100.00,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => 8.00,
            'driver_net_amount'          => 92.00,
            'expected_trips_count'       => 10,
            'settled_trips_count'        => 4,
            'settled_amount'             => 40.00,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        $vault = MasterEscrowVault::getVault();
        $vault->increment('parents_escrow_pool', 6000); // المتبقي فعلاً في الحوض
        $escrowBefore  = (int) $vault->fresh()->parents_escrow_pool;
        $balanceBefore = (int) $this->parent->fresh()->balance;

        app(\App\Services\Shared\SubscriptionRequestService::class)
            ->refundHeldFundsOnCancellation($request->id, 'system');

        // يُسترجع 60 د.ل (6000 قرش) — وليس 100 د.ل
        $this->assertEquals($balanceBefore + 6000, (int) $this->parent->fresh()->balance);
        $this->assertEquals($escrowBefore - 6000, (int) MasterEscrowVault::getVault()->parents_escrow_pool);

        $this->assertDatabaseHas('platform_finances', [
            'id'              => $finance->id,
            'status'          => 'partially_refunded',
            'refunded_amount' => 60.00,
            'settled_amount'  => 40.00,
        ]);
    }

    /**
     * ⚠️ حارس انحدار: اشتراك صُرفت كل حصصه لا يُسترجع منه شيء، ولا يُخصم من
     * حوض الأمانات مبلغ لم يعد فيه.
     */
    public function test_cancelling_fully_settled_subscription_refunds_nothing(): void
    {
        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
        ]);

        PlatformFinance::create([
            'subscription_request_id'    => $request->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 100.00,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => 8.00,
            'driver_net_amount'          => 92.00,
            'expected_trips_count'       => 10,
            'settled_trips_count'        => 10,
            'settled_amount'             => 100.00,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        $balanceBefore = (int) $this->parent->fresh()->balance;
        $escrowBefore  = (int) MasterEscrowVault::getVault()->parents_escrow_pool;

        $result = app(\App\Services\Shared\SubscriptionRequestService::class)
            ->refundHeldFundsOnCancellation($request->id, 'parent');

        $this->assertEquals('nothing_to_refund', $result['status']);
        $this->assertEquals(0.0, $result['refund_amount']);
        $this->assertEquals($balanceBefore, (int) $this->parent->fresh()->balance);
        $this->assertEquals($escrowBefore, (int) MasterEscrowVault::getVault()->parents_escrow_pool);
    }

    /**
     * 7. عند إلغاء السائق للاشتراك يُعاد كامل المبلغ لولي الأمر (100%)
     */
    public function test_driver_cancelling_subscription_refunds_100_percent_to_parent(): void
    {
        $this->parent->deposit(5000);

        $request = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'total_price'                 => 25.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 25.00,
            'status'                      => SubscriptionRequest::STATUS_PENDING,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $request->id,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->addDays(2)->format('Y-m-d'),
            'end_date'                    => now()->addDays(2)->format('Y-m-d'),
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 25.00,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 25.00,
            'driver_net_price'            => 23.00,
        ]);

        // السائق يقبل -> تم حجز 25 د.ل (رصيد ولي الأمر المتبقي: 2500 قرش)
        $this->actingAs($this->driverUser)
            ->putJson("/api/driver/requests/{$request->id}/status", ['status' => 'accepted']);

        $activeSub = ActiveSubscription::where('subscription_request_id', $request->id)->first();

        // السائق يلغي الاشتراك
        $cancelRes = $this->actingAs($this->driverUser)
            ->postJson("/api/driver/active-subscriptions/{$activeSub->id}/cancel", [
                'reason' => 'عطل طارئ في المركبة.',
            ]);

        $cancelRes->assertStatus(200);

        // تم استرجاع كامل المبلغ لولي الأمر (عادت 5000 قرش = 50 د.ل)
        $this->assertEquals(5000, $this->parent->fresh()->balance);

        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $request->id,
            'status'                  => 'refunded',
            'refunded_amount'         => 25.00,
        ]);
    }
}
