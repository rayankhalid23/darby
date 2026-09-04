<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Parent\Address;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Models\Shared\RouteStop;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\LocationChangeRequest;
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\MasterEscrowVault;
use App\Services\Trip\DailyTripGenerationService;
use App\Services\Shared\FinancialLedgerService;
use App\Services\Shared\SubscriptionRequestService;
use Carbon\Carbon;

/**
 * اختبار شامل لميزة طلب ولي الأمر تغيير موقع استلام/تسليم طفله ليوم معين،
 * وموافقة/رفض السائق، وتحديث مسار رحلة اليوم، وفرض رسوم 5 د.ل واحتسابها عند التسوية أو الإلغاء.
 */
class LocationChangeRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected Address $address;
    protected Route $route;
    protected SubscriptionRequest $subReq;
    protected ActiveSubscription $activeSub;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name' => 'سائق الاختبار', 'email' => 'driver.loc.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 2, 'is_active' => 1,
        ]);
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id, 'national_id' => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999), 'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name' => 'ولي أمر الاختبار', 'email' => 'parent.loc.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 3, 'is_active' => 1,
        ]);
        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);

        $school = School::create([
            'name' => 'مدرسة الاختبار', 'address' => 'شارع الاختبار', 'lat' => 32.90, 'lng' => 13.20, 'status' => 'active',
        ]);

        $this->child = Child::create([
            'parent_id' => $this->parent->id, 'school_id' => $school->id, 'full_name' => 'طفل الاختبار',
            'birth_date' => '2018-05-10', 'gender' => 'male', 'grade' => 1, 'notification_radius' => 500,
        ]);

        // الموقع الجديد المحفوظ الذي سيطلب ولي الأمر التغيير إليه
        $this->address = Address::create([
            'parent_id' => $this->parentUser->id, 'label' => 'منزل الجدة', 'lat' => 32.95, 'lng' => 13.25,
        ]);

        $this->subReq = SubscriptionRequest::create([
            'parent_id' => $this->parent->id, 'driver_id' => $this->driver->id,
            'total_price' => 100, 'total_amount_after_discount' => 100, 'status' => SubscriptionRequest::STATUS_ACCEPTED,
            'children_count' => 1,
        ]);

        $vehicle = \App\Models\Driver\Vehicle::create([
            'driver_id' => $this->driver->id, 'plate_number' => '5-' . rand(1000, 9999),
            'brand' => 'Toyota', 'model' => 'Hiace', 'year' => 2022, 'color' => 'White',
            'type' => 'Van', 'capacity_manual' => 14, 'is_verified' => 1, 'status' => 'Active',
        ]);

        $this->route = Route::create([
            'driver_id' => $this->driver->id, 'vehicle_id' => $vehicle->id, 'subscription_request_id' => $this->subReq->id,
            'route_name' => 'مسار الاختبار', 'route_type' => 'Morning', 'shift_slot' => 'morning_go',
            'start_time' => '07:00:00', 'status' => 'Active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'subscription_request_id' => $this->subReq->id, 'route_id' => $this->route->id,
            'status' => 'active', 'child_id' => $this->child->id, 'driver_id' => $this->driver->id,
            'parent_id' => $this->parentUser->id,
            'pickup_lat' => 32.88, 'pickup_lng' => 13.19, 'pickup_label' => 'المنزل', 'pickup_time' => '07:00:00',
            'dropoff_lat' => 32.90, 'dropoff_lng' => 13.20, 'dropoff_label' => 'المدرسة', 'dropoff_time' => '14:00:00',
        ]);

        RouteStop::create([
            'route_id' => $this->route->id, 'stop_type' => RouteStop::TYPE_HOME, 'child_id' => $this->child->id,
            'lat' => 32.88, 'lng' => 13.19, 'label' => 'المنزل', 'sequence_order' => 1,
        ]);
        RouteStop::create([
            'route_id' => $this->route->id, 'stop_type' => RouteStop::TYPE_SCHOOL, 'school_id' => $school->id,
            'lat' => 32.90, 'lng' => 13.20, 'label' => 'المدرسة', 'sequence_order' => 2,
        ]);
    }

    public function test_parent_can_fetch_change_options(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/parent/location-change-requests/options');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.active_subscriptions.0.active_subscription_id', $this->activeSub->id);
        $response->assertJsonPath('data.addresses.0.id', $this->address->id);
    }

    /**
     * ينشئ عنواناً محفوظاً لولي الأمر على بُعد مسافة محددة (كم) شمال نقطة الاستلام الحالية.
     * درجة خط عرض واحدة = 111.19492664455873 كم في صيغة Haversine المستخدمة في النظام.
     */
    private function addressAtDistance(float $km, string $label = 'موقع اختبار'): Address
    {
        return Address::create([
            'parent_id' => $this->parentUser->id,
            'label'     => $label,
            'lat'       => 32.88 + ($km / 111.19492664455873),
            'lng'       => 13.19,
        ]);
    }

    private function setTierFees(float $under2, float $twoToSix, float $sixToTen, float $commissionRate = 8.00): void
    {
        \App\Models\Shared\PricingSetting::query()->update([
            'location_change_fee_under_2km' => $under2,
            'location_change_fee_2_to_6km'  => $twoToSix,
            'location_change_fee_6_to_10km' => $sixToTen,
            'platform_commission_rate'      => $commissionRate,
        ]);
    }

    public function test_parent_can_request_single_day_location_change_with_date_and_tiered_fee(): void
    {
        $this->setTierFees(5.00, 10.00, 15.00);
        $targetDate = now()->addDays(2)->toDateString();

        // العنوان الافتراضي في setUp يبعد 9.59 كم عن نقطة الاستلام => الشريحة الثالثة (6–10 كم)
        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'change_date'            => $targetDate,
                'address_id'             => $this->address->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.change_date', $targetDate);
        $response->assertJsonPath('data.distance_km', 9.59);
        $response->assertJsonPath('data.fee_tier', '6_to_10km');
        $response->assertJsonPath('data.fee_amount', 15);
        $response->assertJsonPath('data.fee_breakdown.platform_commission', 1.2);
        $response->assertJsonPath('data.fee_breakdown.driver_net_fee', 13.8);

        $this->assertDatabaseHas('location_change_requests', [
            'active_subscription_id'     => $this->activeSub->id,
            'point_type'                 => 'pickup',
            'change_date'                => $targetDate,
            'is_single_day'              => 1,
            'distance_km'                => 9.59,
            'fee_tier'                   => '6_to_10km',
            'fee_amount'                 => 15.00,
            'commission_rate'            => 8.00,
            'platform_commission_amount' => 1.20,
            'driver_net_fee'             => 13.80,
            'status'                     => LocationChangeRequest::STATUS_PENDING,
            'new_address_id'             => $this->address->id,
        ]);
    }

    public static function tierProvider(): array
    {
        return [
            'أقل من 2 كم'          => [1.0, 'under_2km', 7.00],
            'حد 2 كم بالضبط'        => [2.0, '2_to_6km', 12.00],
            'داخل 2–6 كم'          => [4.0, '2_to_6km', 12.00],
            'حد 6 كم بالضبط'        => [6.0, '2_to_6km', 12.00],
            'داخل 6–10 كم'         => [8.0, '6_to_10km', 20.00],
            'حد 10 كم بالضبط'       => [10.0, '6_to_10km', 20.00],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tierProvider')]
    public function test_fee_tier_is_selected_by_distance_between_new_location_and_home(float $km, string $expectedTier, float $expectedFee): void
    {
        $this->setTierFees(7.00, 12.00, 20.00);
        $address = $this->addressAtDistance($km);

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests/preview', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $address->id,
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta($km, $response->json('data.distance_km'), 0.001);
        $response->assertJsonPath('data.fee_tier', $expectedTier);
        $this->assertEqualsWithDelta($expectedFee, $response->json('data.fee_breakdown.gross_fee'), 0.001);
    }

    public function test_request_beyond_max_distance_is_rejected_and_nothing_is_saved(): void
    {
        $this->setTierFees(5.00, 10.00, 15.00);
        $address = $this->addressAtDistance(12.0, 'موقع بعيد جداً');

        $before = LocationChangeRequest::count();

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $address->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertSame($before, LocationChangeRequest::count());
    }

    public function test_preview_returns_trip_info_and_price_without_creating_a_request(): void
    {
        $this->setTierFees(5.00, 10.00, 15.00);
        $address = $this->addressAtDistance(4.0);
        $before  = LocationChangeRequest::count();

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests/preview', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $address->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.active_subscription_id', $this->activeSub->id);
        $response->assertJsonPath('data.trip.child.id', $this->child->id);
        $response->assertJsonPath('data.trip.driver.id', $this->driver->id);
        $response->assertJsonPath('data.trip.route.id', $this->route->id);
        $response->assertJsonPath('data.current_location.label', 'المنزل');
        $this->assertEqualsWithDelta(4.0, $response->json('data.distance_km'), 0.001);
        $response->assertJsonPath('data.fee_tier', '2_to_6km');
        $this->assertEqualsWithDelta(10.0, $response->json('data.fee_breakdown.gross_fee'), 0.001);
        $response->assertJsonStructure(['data' => ['trip' => ['pricing' => ['trip_price', 'total_amount_after_discount']]]]);

        // المعاينة لا تنشئ أي سجل
        $this->assertSame($before, LocationChangeRequest::count());
    }

    public function test_fee_breakdown_always_sums_back_to_gross_fee(): void
    {
        // رسم ونسبة يُنتجان كسوراً غير مستقيمة (33.33 * 8.5% = 2.83305)
        $this->setTierFees(5.00, 33.33, 15.00, 8.50);
        $address = $this->addressAtDistance(4.0);

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $address->id,
            ]);

        $response->assertStatus(201);
        $data = $response->json('data.fee_breakdown');

        $this->assertEquals(33.33, $data['gross_fee']);
        $this->assertEquals(2.83, $data['platform_commission']);
        $this->assertEquals(30.50, $data['driver_net_fee']);
        $this->assertEquals(
            round($data['gross_fee'], 2),
            round($data['platform_commission'] + $data['driver_net_fee'], 2),
            'مجموع العمولة وصافي السائق يجب أن يساوي الرسم الإجمالي بالضبط.'
        );
    }

    public function test_driver_listing_shows_fee_net_of_platform_commission(): void
    {
        $this->setTierFees(5.00, 10.00, 15.00);
        $address = $this->addressAtDistance(4.0);

        $this->actingAs($this->parentUser)->postJson('/api/parent/location-change-requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'address_id'             => $address->id,
        ])->assertStatus(201);

        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/location-change-requests?status=pending');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(10.0, $response->json('data.0.fee_amount'), 0.001);
        $this->assertEqualsWithDelta(8.0, $response->json('data.0.fee_breakdown.commission_rate'), 0.001);
        $this->assertEqualsWithDelta(0.8, $response->json('data.0.fee_breakdown.platform_commission'), 0.001);
        $this->assertEqualsWithDelta(9.2, $response->json('data.0.fee_breakdown.driver_net_fee'), 0.001);
    }

    public function test_admin_can_configure_the_three_tier_fees(): void
    {
        $adminUser = User::create([
            'full_name' => 'أدمن الاختبار', 'email' => 'admin.loc.' . uniqid() . '@darby.test',
            'phone_number' => '093' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 1, 'is_active' => 1,
        ]);

        $response = $this->actingAs($adminUser)
            ->postJson('/api/admin/financial/pricing-settings', [
                'discount_one_child'            => 0,
                'discount_two_children'         => 10,
                'discount_three_plus_children'  => 15,
                'platform_commission_rate'      => 8,
                'price_per_km_ac'               => 1,
                'price_per_km_non_ac'           => 0.5,
                'location_change_fee_under_2km' => 6.50,
                'location_change_fee_2_to_6km'  => 11.25,
                'location_change_fee_6_to_10km' => 18.00,
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(6.5, $response->json('data.location_change_fee_under_2km'), 0.001);
        $this->assertEqualsWithDelta(11.25, $response->json('data.location_change_fee_2_to_6km'), 0.001);
        $this->assertEqualsWithDelta(18.0, $response->json('data.location_change_fee_6_to_10km'), 0.001);
        $this->assertEqualsWithDelta(10, $response->json('data.max_location_change_distance_km'), 0.001);

        // القيمة المضبوطة من الأدمن هي التي تُطبَّق فعلياً على طلب ولي الأمر
        $address = $this->addressAtDistance(1.0);
        $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests/preview', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $address->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.fee_breakdown.gross_fee', 6.5);
    }

    public function test_parent_cannot_submit_duplicate_pending_request_for_same_point_and_date(): void
    {
        $targetDate = now()->addDays(1)->toDateString();

        $this->actingAs($this->parentUser)->postJson('/api/parent/location-change-requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'change_date'            => $targetDate,
            'address_id'             => $this->address->id,
        ])->assertStatus(201);

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/location-change-requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'change_date'            => $targetDate,
            'lat'                    => 32.91,
            'lng'                    => 13.21,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_driver_approval_for_single_day_updates_only_trip_stops_and_preserves_master(): void
    {
        $targetDate = now()->addDays(1)->toDateString();

        // 1. إنشاء رحلة اليوم مسبقاً
        $trip = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => $this->route->id,
            'trip_type'            => 'Morning',
            'shift_slot'           => 'morning_go',
            'status'               => 'pending',
            'scheduled_at'         => now(),
            'scheduled_start_time' => '07:00:00',
            'trip_date'            => $targetDate,
        ]);

        $tripStop = TripStop::create([
            'trip_id'        => $trip->id,
            'stop_type'      => TripStop::TYPE_HOME,
            'child_id'       => $this->child->id,
            'lat'            => 32.88,
            'lng'            => 13.19,
            'label'          => 'المنزل',
            'sequence_order' => 1,
            'status'         => TripStop::STATUS_PENDING,
        ]);

        // 2. إنشاء طلب تغيير موقع لليوم المحدد
        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'pickup',
            'change_date'            => $targetDate,
            'is_single_day'          => true,
            'new_address_id'         => $this->address->id,
            'new_lat'                => $this->address->lat,
            'new_lng'                => $this->address->lng,
            'new_label'              => $this->address->label,
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'approved');

        // محطة رحلة اليوم تم تحديثها إلى منزل الجدة
        $this->assertDatabaseHas('trip_stops', [
            'id'    => $tripStop->id,
            'label' => 'منزل الجدة',
            'lat'   => $this->address->lat,
            'lng'   => $this->address->lng,
        ]);

        // المسار المرجعي والاشتراك النشط بقيا كما هما بدون تغيير دائم
        $this->assertDatabaseHas('active_subscriptions', [
            'id'           => $this->activeSub->id,
            'pickup_label' => 'المنزل',
        ]);
        $this->assertDatabaseHas('route_stops', [
            'route_id'  => $this->route->id,
            'child_id'  => $this->child->id,
            'stop_type' => RouteStop::TYPE_HOME,
            'label'     => 'المنزل',
        ]);
    }

    public function test_daily_trip_generation_applies_approved_single_day_location_change(): void
    {
        $targetDate = now()->addDays(3)->toDateString();

        // إنشاء طلب تغيير موقع معتمد مسبقاً لهذا التاريخ
        LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'pickup',
            'change_date'            => $targetDate,
            'is_single_day'          => true,
            'new_address_id'         => $this->address->id,
            'new_lat'                => $this->address->lat,
            'new_lng'                => $this->address->lng,
            'new_label'              => 'منزل الجدة المؤقت',
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_APPROVED,
            'responded_at'           => now(),
        ]);

        // توليد الرحلة اليومية بواسطة DailyTripGenerationService
        $tripGenService = app(DailyTripGenerationService::class);
        $trip = $tripGenService->generateForRoute($this->route, Carbon::parse($targetDate));

        $this->assertNotNull($trip);
        $this->assertDatabaseHas('trip_stops', [
            'trip_id'   => $trip->id,
            'child_id'  => $this->child->id,
            'stop_type' => TripStop::TYPE_HOME,
            'label'     => 'منزل الجدة المؤقت',
            'lat'       => $this->address->lat,
            'lng'       => $this->address->lng,
        ]);
    }

    public function test_subscription_settlement_includes_location_change_fees(): void
    {
        // إضافة تغييرين معتمدين (2 * 5 = 10 د.ل)
        LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'pickup',
            'change_date'            => now()->toDateString(),
            'is_single_day'          => true,
            'new_lat'                => 32.95, 'new_lng' => 13.25, 'new_label' => 'الموقع 1',
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_APPROVED,
        ]);

        LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'dropoff',
            'change_date'            => now()->addDay()->toDateString(),
            'is_single_day'          => true,
            'new_lat'                => 32.96, 'new_lng' => 13.26, 'new_label' => 'الموقع 2',
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_APPROVED,
        ]);

        $ledgerService = app(FinancialLedgerService::class);
        $settlement = $ledgerService->settleMonthlySubscription($this->subReq);

        $this->assertEquals(2, $settlement['location_changes_count']);
        $this->assertEquals(10.00, $settlement['location_changes_fees']);
        // final_settled_amount يشمل رسوم التغييرات
        $this->assertGreaterThanOrEqual(10.00, $settlement['final_settled_amount']);

        // ⚠️ حارس انحدار: كشف المقاصة تقرير للقراءة فقط ولا يعلّم أي رسم كأنه
        // حُصِّل. كانت هذه الدالة تكتب is_settled = true دون تحويل قرش لأحد،
        // فيضيع الإيراد ويُسجَّل كأنه قُبض. التحصيل الفعلي صار لحظة موافقة
        // السائق في LocationChangeService.
        $this->assertDatabaseHas('location_change_requests', [
            'active_subscription_id' => $this->activeSub->id,
            'is_settled'             => 0,
        ]);
    }

    /**
     * رسم تغيير الموقع يُخصم من محفظة ولي الأمر لحظة موافقة السائق،
     * ويُوزَّع فوراً بين صافي السائق وعمولة المنصة.
     */
    public function test_location_change_fee_is_collected_on_driver_approval(): void
    {
        $this->parent->deposit(10000); // 100 د.ل

        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'pickup',
            'change_date'            => now()->addDay()->toDateString(),
            'is_single_day'          => true,
            'new_lat'                => 32.95, 'new_lng' => 13.25, 'new_label' => 'الموقع الجديد',
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_PENDING,
        ]);

        $parentBefore = (int) $this->parent->fresh()->balance;
        $driverBefore = (int) $this->driver->fresh()->balance;
        $revenueBefore = (int) MasterEscrowVault::getVault()->platform_revenue_pool;

        app(\App\Services\Shared\LocationChangeService::class)
            ->respondToChange($this->driverUser->id, $changeRequest->id, true);

        // 5 د.ل = 500 قرش، عمولة 8٪ = 40 قرشاً، صافي السائق 460 قرشاً
        $this->assertEquals($parentBefore - 500, (int) $this->parent->fresh()->balance);
        $this->assertEquals($driverBefore + 460, (int) $this->driver->fresh()->balance);
        $this->assertEquals($revenueBefore + 40, (int) MasterEscrowVault::getVault()->platform_revenue_pool);

        $this->assertDatabaseHas('location_change_requests', [
            'id'         => $changeRequest->id,
            'is_settled' => 1,
        ]);
    }

    public function test_cancellation_refund_deducts_approved_location_change_fees(): void
    {
        // إيداع مبدئي في الخزينة ومحفظة ولي الأمر ومحفظة السائق
        $vault = MasterEscrowVault::getVault();
        $vault->increment('parents_escrow_pool', 10000); // 100 د.ل = 10000 قرش

        // إنشاء قيد مالي محجوز للاشتراك بقيمة 100 د.ل
        PlatformFinance::create([
            'subscription_request_id'    => $this->subReq->id,
            'active_subscription_id'     => $this->activeSub->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 100.00,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => 8.00,
            'driver_net_amount'          => 92.00,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        // ولي أمر قام بتغيير عنوان معتمد (5 د.ل)
        LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id,
            'child_id'               => $this->child->id,
            'parent_id'              => $this->parentUser->id,
            'driver_id'              => $this->driver->id,
            'point_type'             => 'pickup',
            'change_date'            => now()->toDateString(),
            'is_single_day'          => true,
            'new_lat'                => 32.95, 'new_lng' => 13.25, 'new_label' => 'الموقع 1',
            'fee_amount'             => 5.00,
            'status'                 => LocationChangeRequest::STATUS_APPROVED,
            'is_settled'             => false,
        ]);

        $subService = app(SubscriptionRequestService::class);
        $result = $subService->refundHeldFundsOnCancellation($this->subReq->id, 'parent');

        $this->assertNotNull($result);
        // تم خصم 5 د.ل من استرجاع ولي الأمر (100 - 5 = 95 د.ل)
        $this->assertEquals(95.00, $result['refund_amount']);
        $this->assertEquals(5.00, $result['compensation_fee']);
        $this->assertEquals(1, $result['location_changes_count']);
        $this->assertEquals(5.00, $result['location_changes_fees']);
    }

    public function test_driver_can_reject_change_with_reason_and_data_is_not_modified(): void
    {
        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'point_type' => 'pickup', 'new_address_id' => $this->address->id,
            'new_lat' => $this->address->lat, 'new_lng' => $this->address->lng, 'new_label' => $this->address->label,
            'fee_amount' => 5.00,
            'status' => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status'           => 'rejected',
                'rejection_reason' => 'المسار الجديد بعيد جداً عن باقي الأطفال.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('location_change_requests', [
            'id' => $changeRequest->id, 'status' => LocationChangeRequest::STATUS_REJECTED,
            'rejection_reason' => 'المسار الجديد بعيد جداً عن باقي الأطفال.',
        ]);

        // لم يتغيّر شيء في الاشتراك النشط
        $this->assertDatabaseHas('active_subscriptions', [
            'id' => $this->activeSub->id, 'pickup_label' => 'المنزل',
        ]);
    }

    public function test_driver_cannot_respond_to_another_drivers_request(): void
    {
        $otherDriverUser = User::create([
            'full_name' => 'سائق آخر', 'email' => 'driver.other.' . uniqid() . '@darby.test',
            'phone_number' => '095' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 2, 'is_active' => 1,
        ]);
        Driver::create([
            'user_id' => $otherDriverUser->id, 'national_id' => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999), 'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'point_type' => 'pickup', 'new_lat' => 32.95, 'new_lng' => 13.25, 'new_label' => 'منزل الجدة',
            'fee_amount' => 5.00,
            'status' => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($otherDriverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status' => 'approved',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('location_change_requests', [
            'id' => $changeRequest->id, 'status' => LocationChangeRequest::STATUS_PENDING,
        ]);
    }
}
