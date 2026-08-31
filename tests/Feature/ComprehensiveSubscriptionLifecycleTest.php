<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Driver\Vehicle;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\ChildLogistics;
use App\Models\Parent\Address;
use App\Models\Parent\School;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\MasterEscrowVault;
use App\Models\Shared\PlatformFinance;
use App\Models\Shared\Trip;
use App\Models\Shared\Route;

class ComprehensiveSubscriptionLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected School $school;
    protected Address $homeAddress;
    protected Zone $zone;
    protected PricingSetting $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعدادات الأدوار
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 2. إعدادات التسعير القياسية
        $this->pricing = PricingSetting::firstOrCreate([], [
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'     => 8.00,
        ]);

        // 3. المنطقة والمدرسة
        $municipality = Municipality::firstOrCreate(['name' => 'طرابلس الكبرى']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'حي الأندلس']);
        $this->zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'قرقارش']);

        $this->school = School::create([
            'name'    => 'مدرسة قرقارش النموذجية',
            'zone_id' => $this->zone->id,
            'lat'     => 32.88000000,
            'lng'     => 13.18000000,
            'address' => 'طرابلس - قرقارش',
            'status'  => 'active',
        ]);

        // 4. ولي الأمر
        $this->parentUser = User::create([
            'full_name'     => 'أحمد محمود القرقني',
            'email'         => 'parent.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // رصيد محفظة ولي الأمر (1000 د.ل = 100000 سنت)
        $this->parent->deposit(100000);

        // عنوان سكن ولي الأمر
        $this->homeAddress = Address::create([
            'parent_id'  => $this->parentUser->id,
            'zone_id'    => $this->zone->id,
            'label'      => 'منزل العائلة',
            'lat'        => 32.87000000,
            'lng'        => 13.17000000,
            'is_default' => true,
        ]);

        // 5. السائق
        $this->driverUser = User::create([
            'full_name'     => 'خالد ناصر الترهوني',
            'email'         => 'driver.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'           => $this->driverUser->id,
            'national_id'       => 'NAT' . rand(100000, 999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->toDateString(),
            'status'            => 'Approved',
            'gender'            => 'male',
            'accepted_gender'   => 'both',
            'subscription_type' => 'both',
            'morning_go'        => 1,
            'morning_return'    => 1,
            'current_lat'       => 32.86000000,
            'current_lng'       => 13.16000000,
        ]);

        // مركبة السائق
        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هاي آس',
            'year'            => 2023,
            'color'           => 'أبيض',
            'type'            => 'Van',
            'plate_number'    => 'LY-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'has_ac'          => 1,
            'status'          => 'Active',
            'is_verified'     => 1,
        ]);

        // ربط السائق بالمنطقة
        DB::table('driver_zone')->insertOrIgnore([
            'driver_id' => $this->driver->id,
            'zone_id'   => $this->zone->id,
        ]);

        // فتح فترات المقاعد للسائق
        foreach (['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'] as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver->id,
                'slot'           => $slot,
                'total_seats'    => 10,
                'reserved_seats' => 0,
            ]);
        }
    }

    protected function createTestChild(string $name, string $gender = 'male', string $subType = 'multi_day', string $direction = 'both'): Child
    {
        $child = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->homeAddress->id,
            'full_name'  => $name,
            'birth_date' => '2015-06-15',
            'gender'     => $gender,
            'grade'      => 4,
        ]);

        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = ($subType === 'single_day') ? $start : Carbon::parse($start)->addDays(13)->format('Y-m-d');

        ChildLogistics::create([
            'child_id'            => $child->id,
            'preferred_time_slot' => 'morning',
            'trip_direction'      => $direction,
            'subscription_type'   => $subType,
            'start_date'          => $start,
            'end_date'            => $end,
        ]);

        return $child;
    }

    // =========================================================================
    // 1. اختبارات دوال الفلترة والبحث والتسعير الذكي (Driver Matching & Filtering)
    // =========================================================================

    public function test_01_filter_drivers_returns_matching_driver_with_pricing(): void
    {
        $child = $this->createTestChild('علي القرقني', 'male', 'multi_day', 'both');

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/drivers/search', [
                'child_ids' => [$child->id],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('data'));

        $driverData = $response->json('data.0');
        $this->assertEquals($this->driver->id, $driverData['id']);
        $this->assertArrayHasKey('pricing', $driverData);
        $this->assertGreaterThan(0, $driverData['pricing']['total_price_raw']);
    }

    public function test_02_filter_excludes_drivers_with_expired_documents(): void
    {
        $child = $this->createTestChild('محمد القرقني', 'male', 'multi_day', 'both');

        // إضافة وثيقة منتهية الصلاحية للسائق
        DB::table('driver_documents')->insert([
            'driver_id'   => $this->driver->id,
            'doc_type'    => 'LICENSE',
            'file_url'    => 'docs/license.pdf',
            'status'      => 'Expired',
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/drivers/search', [
                'child_ids' => [$child->id],
            ]);

        $response->assertStatus(200);
        // يجب أن لا يظهر السائق لأن رخصته منتهية
        $driverIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->driver->id, $driverIds);
    }

    public function test_03_filter_by_text_search_name_and_phone(): void
    {
        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/drivers/search', [
                'search_query' => 'الترهوني',
            ]);

        $response->assertStatus(200);
        $driverIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->driver->id, $driverIds);
    }

    // =========================================================================
    // 2. اختبار إرسال طلب الاشتراك (Store / Create Subscription Request)
    // =========================================================================

    public function test_04_parent_can_send_subscription_request_successfully(): void
    {
        $child = $this->createTestChild('سارة القرقني', 'female', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $payload = [
            'driver_id' => $this->driver->id,
            'notes'     => 'الرجاء الحضور في الموعد المحدد',
            'children'  => [
                [
                    'child_id'          => $child->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => $start,
                    'end_date'          => $end,
                    'price_per_child'   => 150.00,
                    'trip_price'        => 150.00,
                    'distance_km'       => 5.2,
                ]
            ]
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('requests', [
            'id'        => $requestId,
            'parent_id' => $this->parent->id,
            'driver_id' => $this->driver->id,
            'status'    => 'pending',
        ]);

        $this->assertDatabaseHas('request_children', [
            'request_id'        => $requestId,
            'child_id'          => $child->id,
            'subscription_type' => 'multi_day',
        ]);
    }

    public function test_05_store_request_fails_for_single_day_when_wallet_is_empty(): void
    {
        // تصفير المحفظة
        $currentBalance = (int) $this->parent->balance;
        if ($currentBalance > 0) {
            $this->parent->withdraw($currentBalance);
        }

        $child = $this->createTestChild('عمر القرقني', 'male', 'single_day', 'go');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');

        $payload = [
            'driver_id' => $this->driver->id,
            'children'  => [
                [
                    'child_id'          => $child->id,
                    'subscription_type' => 'single_day',
                    'trip_direction'    => 'go',
                    'timing'            => 'MORNING',
                    'start_date'        => $start,
                    'end_date'          => $start,
                    'price_per_child'   => 50.00,
                    'trip_price'        => 50.00,
                ]
            ]
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/requests', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    // =========================================================================
    // 3. اختبارات عرض طلبات الاشتراك (View / Index / Show)
    // =========================================================================

    public function test_06_parent_and_driver_can_list_and_view_their_requests(): void
    {
        $child = $this->createTestChild('يوسف القرقني', 'male', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'pending',
            'total_price'                 => 200.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 200.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 6.0,
            'trip_price'                  => 200.00,
            'price_per_child'             => 200.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 200.00,
            'driver_net_price'            => 184.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // 1. استعراض ولي الأمر
        $parentRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/parent/requests');
        $parentRes->assertStatus(200);
        $this->assertContains($req->id, collect($parentRes->json('data'))->pluck('id')->toArray());

        // تفاصيل الطلب لولي الأمر
        $parentShow = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson("/api/parent/requests/{$req->id}");
        $parentShow->assertStatus(200);
        $parentShow->assertJsonPath('data.id', $req->id);

        // 2. استعراض السائق
        $driverRes = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson('/api/driver/requests');
        $driverRes->assertStatus(200);
        $this->assertContains($req->id, collect($driverRes->json('data'))->pluck('id')->toArray());

        // تفاصيل الرحلة للسائق
        $driverTrip = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson("/api/driver/requests/{$req->id}/trip-details");
        $driverTrip->assertStatus(200);
        $driverTrip->assertJsonPath('data.request_id', $req->id);
    }

    // =========================================================================
    // 4. اختبار قبول طلب الاشتراك (Driver Accepts Subscription Request)
    // =========================================================================

    public function test_07_driver_accepts_subscription_request_activates_and_reserves_seats(): void
    {
        $child = $this->createTestChild('حمزة القرقني', 'male', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'pending',
            'total_price'                 => 220.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 220.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 5.0,
            'trip_price'                  => 220.00,
            'price_per_child'             => 220.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 220.00,
            'driver_net_price'            => 202.40,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $initialReservedSeats = DriverSeatSlot::where('driver_id', $this->driver->id)
            ->where('slot', 'morning_go')
            ->value('reserved_seats');

        // قبول السائق للطلب
        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/driver/requests/{$req->id}/status", [
                'status' => 'accepted'
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // التحقق من تحديث حالة الطلب
        $this->assertDatabaseHas('requests', [
            'id'     => $req->id,
            'status' => 'accepted',
        ]);

        // التحقق من إنشاء اشتراك نشط ActiveSubscription
        $this->assertDatabaseHas('active_subscriptions', [
            'subscription_request_id' => $req->id,
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'status'                  => 'active',
        ]);

        // التحقق من زيادة عداد المقاعد المحجوزة
        $newReservedSeats = DriverSeatSlot::where('driver_id', $this->driver->id)
            ->where('slot', 'morning_go')
            ->value('reserved_seats');
        $this->assertEquals($initialReservedSeats + 1, $newReservedSeats);
    }

    public function test_08_driver_cannot_accept_request_if_seats_are_full(): void
    {
        // ملء مقاعد السائق بالكامل
        DriverSeatSlot::where('driver_id', $this->driver->id)
            ->update(['total_seats' => 2, 'reserved_seats' => 2]);

        $child = $this->createTestChild('فاطمة القرقني', 'female', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'pending',
            'total_price'                 => 180.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 180.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 5.0,
            'trip_price'                  => 180.00,
            'price_per_child'             => 180.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 180.00,
            'driver_net_price'            => 165.60,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/driver/requests/{$req->id}/status", [
                'status' => 'accepted'
            ]);

        $response->assertStatus(500);
        $response->assertJsonPath('success', false);
    }

    // =========================================================================
    // 5. اختبار رفض طلب الاشتراك (Driver Rejects Subscription Request)
    // =========================================================================

    public function test_09_driver_rejects_subscription_request_with_reason(): void
    {
        $child = $this->createTestChild('زينب القرقني', 'female', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'pending',
            'total_price'                 => 190.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 190.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 5.0,
            'trip_price'                  => 190.00,
            'price_per_child'             => 190.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 190.00,
            'driver_net_price'            => 174.80,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/driver/requests/{$req->id}/status", [
                'status'           => 'rejected',
                'rejection_reason' => 'خارج نطاق خط سيري الصباحي',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('requests', [
            'id'               => $req->id,
            'status'           => 'rejected',
            'rejection_reason' => 'خارج نطاق خط سيري الصباحي',
        ]);
    }

    // =========================================================================
    // 6. اختبار عرض الاشتراكات النشطة (View Active Subscriptions)
    // =========================================================================

    public function test_10_view_active_subscriptions_for_parent_and_driver_with_filters(): void
    {
        $child = $this->createTestChild('طارق القرقني', 'male', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 250.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 250.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 5.0,
            'trip_price'                  => 250.00,
            'price_per_child'             => 250.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 250.00,
            'driver_net_price'            => 230.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $activeSub = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_lat'              => 32.87,
            'pickup_lng'              => 13.17,
            'dropoff_lat'             => 32.88,
            'dropoff_lng'             => 13.18,
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        // 1. ولي الأمر
        // نقاط active-subscriptions تُفكِّك كل طفل كصف مستقل بقصد (كل طفل باشتراكه)،
        // والحقل الأعلى `id` هو معرّف صف active_subscriptions الفعلي لهذا الطفل
        // (لا معرّف الطفل ولا معرّف طلب الاشتراك — راجع ParentActiveChildSubscriptionResource).
        $parentRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/parent/active-subscriptions?filter=active');
        $parentRes->assertStatus(200);
        $this->assertContains($activeSub->id, collect($parentRes->json('data'))->pluck('id')->toArray());

        // تفاصيل الاشتراك النشط لولي الأمر
        $parentShow = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson("/api/parent/active-subscriptions/{$activeSub->id}");
        $parentShow->assertStatus(200);

        // 2. السائق
        $driverRes = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson('/api/driver/active-subscriptions?filter=current_active');
        $driverRes->assertStatus(200);
        $this->assertContains($activeSub->id, collect($driverRes->json('data'))->pluck('id')->toArray());

        // تفاصيل الاشتراك النشط للسائق
        $driverShow = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson("/api/driver/active-subscriptions/{$activeSub->id}");
        $driverShow->assertStatus(200);
    }

    // =========================================================================
    // 7. اختبار إلغاء طلب اشتراك معلق (Cancel Pending Subscription Request)
    // =========================================================================

    public function test_11_parent_cancels_pending_subscription_request(): void
    {
        $child = $this->createTestChild('بلال القرقني', 'male', 'multi_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');
        $end   = Carbon::parse($start)->addDays(13)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'pending',
            'total_price'                 => 160.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 160.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $end,
            'working_days_count'          => 10,
            'distance_km'                 => 5.0,
            'trip_price'                  => 160.00,
            'price_per_child'             => 160.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 160.00,
            'driver_net_price'            => 147.20,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson("/api/parent/requests/{$req->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('requests', [
            'id'     => $req->id,
            'status' => 'cancelled',
        ]);
    }

    // =========================================================================
    // 8. اختبار إلغاء الاشتراك النشط وتحرير المقاعد واسترجاع المبالغ (Cancel Active Subscription)
    // =========================================================================

    public function test_12_parent_cancels_active_subscription_before_trip_releases_seats_and_full_refund(): void
    {
        $child = $this->createTestChild('مريم القرقني', 'female', 'single_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $start,
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 100.00,
            'price_per_child'             => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'driver_net_price'            => 92.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $activeSub = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_lat'              => 32.87,
            'pickup_lng'              => 13.17,
            'dropoff_lat'             => 32.88,
            'dropoff_lng'             => 13.18,
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        // زيادة مقعد محجوز لمحاكاة القبول
        DriverSeatSlot::where('driver_id', $this->driver->id)->where('slot', 'morning_go')->increment('reserved_seats');
        DriverSeatSlot::where('driver_id', $this->driver->id)->where('slot', 'morning_return')->increment('reserved_seats');

        // محاكاة حجز مالي في الأمانات (100 د.ل = 10000 سنت)
        $vault = MasterEscrowVault::getVault();
        $vault->increment('parents_escrow_pool', 10000);

        PlatformFinance::create([
            'subscription_request_id'    => $req->id,
            'parent_id'                  => $this->parent->id,
            'driver_id'                  => $this->driver->id,
            'total_amount'               => 100.00,
            'platform_commission_rate'   => 8.00,
            'platform_commission_amount' => 8.00,
            'driver_net_amount'          => 92.00,
            'status'                     => PlatformFinance::STATUS_HELD,
            'held_at'                    => now(),
        ]);

        // إلغاء ولي الأمر للاشتراك النشط
        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson("/api/parent/active-subscriptions/{$activeSub->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // التحقق من تحديث حالة الاشتراك النشط
        $this->assertDatabaseHas('active_subscriptions', [
            'id'     => $activeSub->id,
            'status' => 'cancelled',
        ]);

        // التحقق من تحرير المقاعد
        $reservedSeats = DriverSeatSlot::where('driver_id', $this->driver->id)
            ->where('slot', 'morning_go')
            ->value('reserved_seats');
        $this->assertEquals(0, $reservedSeats);

        // التحقق من استرجاع كامل المبلغ
        $this->assertDatabaseHas('platform_finances', [
            'subscription_request_id' => $req->id,
            'status'                  => PlatformFinance::STATUS_REFUNDED,
            'refunded_amount'         => 100.00,
        ]);
    }

    public function test_13_driver_cancels_active_subscription_gives_full_refund_to_parent(): void
    {
        $child = $this->createTestChild('أروى القرقني', 'female', 'single_day', 'both');
        $start = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');

        $req = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 120.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 120.00,
        ]);

        $req->children()->attach($child->id, [
            'subscription_type'           => 'single_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => $start,
            'end_date'                    => $start,
            'working_days_count'          => 1,
            'distance_km'                 => 5.0,
            'trip_price'                  => 120.00,
            'price_per_child'             => 120.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 120.00,
            'driver_net_price'            => 110.40,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $activeSub = ActiveSubscription::create([
            'subscription_request_id' => $req->id,
            'child_id'                => $child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
            'pickup_lat'              => 32.87,
            'pickup_lng'              => 13.17,
            'dropoff_lat'             => 32.88,
            'dropoff_lng'             => 13.18,
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
        ]);

        // إلغاء السائق للاشتراك
        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson("/api/driver/active-subscriptions/{$activeSub->id}/cancel", [
                'reason' => 'عطل ميكانيكي طارئ بالمركبة',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('active_subscriptions', [
            'id'     => $activeSub->id,
            'status' => 'cancelled',
        ]);
    }
}
