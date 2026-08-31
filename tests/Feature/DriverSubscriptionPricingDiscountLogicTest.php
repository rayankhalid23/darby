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
use App\Models\Shared\SubscriptionRequest;
use App\Services\Shared\SubscriptionRequestService;

class DriverSubscriptionPricingDiscountLogicTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected School $school;
    protected Child $child1;
    protected Child $child2;
    protected Child $child3;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // إعدادات الأسعار: 10% لطفلين، 15% لثلاثة أطفال، 8% عمولة المنصة
        PricingSetting::query()->delete();
        PricingSetting::create([
            'discount_one_child'           => 0.00,
            'discount_two_children'        => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'    => 8.00,
            'price_per_km_ac'              => 2.50,
            'price_per_km_non_ac'          => 2.00,
        ]);

        // السائق
        $this->driverUser = User::create([
            'full_name'    => 'كابتن التجربة المالية',
            'email'        => 'driver.pricing.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('secret123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'          => $this->driverUser->id,
            'national_id'      => 'NAT' . rand(100000, 999999),
            'license_number'   => 'LIC' . rand(100000, 999999),
            'license_expiry'   => now()->addYears(2)->format('Y-m-d'),
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
            'plate_number'    => 'PR-' . rand(1000, 9999),
            'capacity_manual' => 12,
            'capacity_ai'     => 12,
            'status'          => 'Active',
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

        // ولي الأمر
        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر التجربة المالية',
            'email'        => 'parent.pricing.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('secret123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // قيمة الاشتراك تُحجز في الأمانات لكل الأنواع (وليس اليومي فقط)،
        // لذا يجب أن تكون محفظة ولي الأمر ممولة قبل إرسال الطلب أو قبوله.
        $this->parent->deposit(500000);

        // مدرسة
        $this->school = School::create([
            'name'    => 'مدرسة المستقبل',
            'address' => 'حي الأندلس، طرابلس',
            'lat'     => 32.8870,
            'lng'     => 13.1890,
            'status'  => 'active',
        ]);

        // الأطفال
        $this->child1 = Child::create([
            'parent_id' => $this->parent->id,
            'school_id' => $this->school->id,
            'full_name' => 'محمد أحمد',
            'birth_date'=> '2016-01-01',
            'gender'    => 'male',
            'grade'     => 3,
        ]);

        $this->child2 = Child::create([
            'parent_id' => $this->parent->id,
            'school_id' => $this->school->id,
            'full_name' => 'فاطمة أحمد',
            'birth_date'=> '2018-05-10',
            'gender'    => 'female',
            'grade'     => 1,
        ]);

        $this->child3 = Child::create([
            'parent_id' => $this->parent->id,
            'school_id' => $this->school->id,
            'full_name' => 'علي أحمد',
            'birth_date'=> '2019-09-20',
            'gender'    => 'male',
            'grade'     => 1,
        ]);
    }

    /**
     * 1. اختبار طلب لطفل واحد:
     * - لا تخفيض (discount = 0)
     * - السعر بعد التخفيض = 200
     * - عمولة المنصة 8% = 16
     * - صافي السائق = 200 - 16 = 184
     */
    public function test_single_child_request_has_no_discount_and_deducts_platform_commission(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'notes'     => 'ملاحظة طفل واحد',
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);

        // التحقق من جدول الطلبات requests
        $this->assertEquals(200.00, (float) $request->total_price);
        $this->assertEquals(0.00, (float) $request->discount_amount);
        $this->assertEquals(200.00, (float) $request->total_amount_after_discount);

        // التحقق من جدول تفاصيل الأطفال request_children
        $pivot = $request->children()->first()->pivot;
        $this->assertEquals(200.00, (float) $pivot->price_per_child);
        $this->assertEquals(0.00, (float) $pivot->discount_amount);
        $this->assertEquals(200.00, (float) $pivot->total_amount_after_discount);
        $this->assertEquals(184.00, (float) $pivot->driver_net_price); // 200 - 8% (16) = 184

        // اختبار ظهور السعر الصافي للسائق في قائمة الطلبات GET /api/driver/requests
        $response = $this->actingAs($this->driverUser)->getJson('/api/driver/requests');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.total_amount', 184);
        $response->assertJsonPath('data.0.children.0.pricing.driver_net_price', 184);
        $response->assertJsonPath('data.0.children.0.pricing.total_amount_after_discount', 200);
        $response->assertJsonPath('data.0.children.0.pricing.discount_amount', 0);
        $response->assertJsonPath('data.0.children.0.pricing.platform_commission', 16);

        // اختبار تفاصيل الطلب GET /api/driver/requests/{id}
        $detailResponse = $this->actingAs($this->driverUser)->getJson("/api/driver/requests/{$request->id}");
        $detailResponse->assertStatus(200);
        $detailResponse->assertJsonPath('data.total_amount', 184);
        $detailResponse->assertJsonPath('data.children.0.pricing.driver_net_price', 184);
        $detailResponse->assertJsonPath('data.children.0.pricing.total_amount_after_discount', 200);
    }

    /**
     * 2. اختبار طلب لطفلين:
     * - تخفيض 10% لكل طفل
     * - الطفل 1: السعر 200 -> الخصم 20 -> السعر بعد الخصم 180 -> عمولة المنصة 8% (14.4) -> صافي السائق 165.6
     * - الطفل 2: السعر 200 -> الخصم 20 -> السعر بعد الخصم 180 -> عمولة المنصة 8% (14.4) -> صافي السائق 165.6
     * - إجمالي صافي السائق = 331.2
     */
    public function test_two_children_request_applies_ten_percent_discount_and_deducts_commission(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'notes'     => 'طلب طفلين',
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);

        // التحقق من جدول requests
        $this->assertEquals(400.00, (float) $request->total_price);
        $this->assertEquals(40.00, (float) $request->discount_amount);
        $this->assertEquals(360.00, (float) $request->total_amount_after_discount);

        // التحقق من تفاصيل الطفلين
        foreach ($request->children as $child) {
            $pivot = $child->pivot;
            $this->assertEquals(200.00, (float) $pivot->price_per_child);
            $this->assertEquals(20.00, (float) $pivot->discount_amount); // 10%
            $this->assertEquals(180.00, (float) $pivot->total_amount_after_discount);
            $this->assertEquals(165.60, (float) $pivot->driver_net_price); // 180 - 14.40 = 165.60
        }

        // اختبار ظهور السعر الصافي للسائق في قائمة الطلبات
        $response = $this->actingAs($this->driverUser)->getJson('/api/driver/requests');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.total_amount', 331.2);
        $response->assertJsonPath('data.0.children.0.pricing.discount_amount', 20);
        $response->assertJsonPath('data.0.children.0.pricing.total_amount_after_discount', 180);
        $response->assertJsonPath('data.0.children.0.pricing.driver_net_price', 165.6);
    }

    /**
     * 3. اختبار طلب لـ 3 أطفال فما فوق:
     * - تخفيض 15% لكل طفل
     * - الطفل 1: السعر 200 -> الخصم 30 -> السعر بعد الخصم 170 -> عمولة 8% (13.6) -> صافي 156.4
     * - إجمالي صافي السائق لـ 3 أطفال = 156.4 * 3 = 469.2
     */
    public function test_three_children_request_applies_fifteen_percent_discount_and_deducts_commission(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'notes'     => 'طلب 3 أطفال',
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ],
                [
                    'child_id'          => $this->child3->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);

        // التحقق من جدول requests
        $this->assertEquals(600.00, (float) $request->total_price);
        $this->assertEquals(90.00, (float) $request->discount_amount);
        $this->assertEquals(510.00, (float) $request->total_amount_after_discount);

        // التحقق من تفاصيل الأطفال الثلاثة
        foreach ($request->children as $child) {
            $pivot = $child->pivot;
            $this->assertEquals(200.00, (float) $pivot->price_per_child);
            $this->assertEquals(30.00, (float) $pivot->discount_amount); // 15%
            $this->assertEquals(170.00, (float) $pivot->total_amount_after_discount);
            $this->assertEquals(156.40, (float) $pivot->driver_net_price); // 170 - 13.60 = 156.40
        }

        // اختبار ظهور السعر الصافي للسائق في قائمة الطلبات
        $response = $this->actingAs($this->driverUser)->getJson('/api/driver/requests');
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.total_amount', 469.2);
        $response->assertJsonPath('data.0.children.0.pricing.discount_amount', 30);
        $response->assertJsonPath('data.0.children.0.pricing.total_amount_after_discount', 170);
        $response->assertJsonPath('data.0.children.0.pricing.driver_net_price', 156.4);
    }

    /**
     * 4. اختبار ظهور صافي السائق في تفاصيل الاشتراك النشط GET /api/driver/active-subscriptions/{id}
     */
    public function test_driver_can_view_active_subscription_details_with_net_price(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'notes'     => 'طلب اشتراك نشط',
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 200.00,
                    'trip_price'        => 10.00,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);

        // قبول الطلب
        $accepted = $service->updateStatus($request, SubscriptionRequest::STATUS_ACCEPTED);

        // جلب سجل الاشتراك النشط للطفل الأول
        $activeSub = \App\Models\Shared\ActiveSubscription::where('subscription_request_id', $request->id)
            ->where('child_id', $this->child1->id)
            ->first();

        $response = $this->actingAs($this->driverUser)->getJson("/api/driver/active-subscriptions/{$activeSub->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        // total_amount يخص الطفل المعروض في هذه الشاشة (165.6 = 180 - 8٪)،
        // أما driver_net_total فهو صافي الطلب كاملاً بطفليه (331.2).
        $response->assertJsonPath('data.total_amount', 165.6);
        $response->assertJsonPath('data.driver_net_total', 331.2);
        $response->assertJsonPath('data.original_total', 400);
        $response->assertJsonPath('data.discount_total', 40);
        $response->assertJsonPath('data.total_after_discount', 360);
        $response->assertJsonPath('data.children.0.pricing.driver_net_price', 165.6);
        $response->assertJsonPath('data.children.0.pricing.total_amount_after_discount', 180);
        $response->assertJsonPath('data.children.0.pricing.discount_amount', 20);
        $response->assertJsonPath('data.children.0.pricing.original_price', 200);
    }
}
