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
use App\Models\Parent\Address;
use App\Models\Shared\PricingSetting;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\SubscriptionRequestService;

class ParentUpcomingTripsPricingAndLocationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected School $school1;
    protected School $school2;
    protected Child $child1;
    protected Child $child2;

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
            'full_name'    => 'محمود علي',
            'email'        => 'driver.test.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password'),
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
            'capacity_manual' => 14,
            'capacity_ai'     => 14,
            'status'          => 'Active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        foreach (DriverSeatSlot::ALL_SLOTS as $slot) {
            DriverSeatSlot::create([
                'driver_id'      => $this->driver->id,
                'slot'           => $slot,
                'total_seats'    => 14,
                'reserved_seats' => 0,
            ]);
        }

        $this->parentUser = User::create([
            'full_name'    => 'محمد عبد الله',
            'email'        => 'parent.test.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password'),
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

        $this->school1 = School::create([
            'name'    => 'مدرسة المستقبل الواعد',
            'address' => 'طرابلس - حي الأندلس',
            'lat'     => 32.8870,
            'lng'     => 13.1890,
            'status'  => 'active',
        ]);

        $this->school2 = School::create([
            'name'    => 'مدرسة النخبة الدولية',
            'address' => 'طرابلس - طريق الشط',
            'lat'     => 32.8950,
            'lng'     => 13.1950,
            'status'  => 'active',
        ]);

        $addr1 = Address::create([
            'parent_id' => $this->parentUser->id,
            'label'     => 'المنزل - حي الأندلس',
            'lat'       => 32.8752,
            'lng'       => 13.1654,
        ]);

        $this->child1 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school1->id,
            'address_id' => $addr1->id,
            'full_name'  => 'أحمد محمد',
            'birth_date' => '2016-01-01',
            'gender'     => 'male',
            'grade'      => 3,
        ]);

        $this->child2 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school2->id,
            'address_id' => $addr1->id,
            'full_name'  => 'سارة محمد',
            'birth_date' => '2018-05-10',
            'gender'     => 'female',
            'grade'      => 1,
        ]);
    }

    /**
     * اختبار التأكد من تخزين سعر الرحلة الواحدة في طلب الاشتراك بعد التخفيض
     */
    public function test_subscription_creation_stores_discounted_trip_price(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'notes'     => 'اشتراك طفلين مع تخفيض 10%',
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(1)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 150.00,
                    'trip_price'        => 10.00, // السعر الأصلي للرحلة 10 د.ل
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->addDays(1)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    'price_per_child'   => 150.00,
                    'trip_price'        => 10.00,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);

        // نسبة التخفيض لطفلين هي 10%
        // سعر الرحلة 10 د.ل -> بعد التخفيض 10% يصبح 9.00 د.ل
        $pivot1 = $request->children()->where('child_id', $this->child1->id)->first()->pivot;
        $pivot2 = $request->children()->where('child_id', $this->child2->id)->first()->pivot;

        $this->assertEquals(9.00, (float) $pivot1->trip_price, 'سعر الرحلة للطفل الأول يجب أن يُخزن بعد التخفيض 9.00 د.ل');
        $this->assertEquals(9.00, (float) $pivot2->trip_price, 'سعر الرحلة للطفل الثاني يجب أن يُخزن بعد التخفيض 9.00 د.ل');
    }

    /**
     * اختبار جلب الرحلات المجدولة القادمة GET /api/parent/trips/upcoming
     * والتأكد من إرجاع موقع المدرسة والمنزل لكل طفل وسعر كل طفل منفصلاً والإجمالي للرحلة
     */
    public function test_get_upcoming_trips_returns_locations_and_pricing_per_child(): void
    {
        $service = app(SubscriptionRequestService::class);

        $data = [
            'driver_id' => $this->driver->id,
            'children'  => [
                [
                    'child_id'          => $this->child1->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->subDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    // 4 كم × 2.50 د.ل/كم (مركبة مكيفة) = 10.00 للاتجاه الواحد -> 9.00 بعد خصم 10%
                    'distance_km'       => 4.0,
                ],
                [
                    'child_id'          => $this->child2->id,
                    'subscription_type' => 'multi_day',
                    'trip_direction'    => 'both',
                    'timing'            => 'MORNING',
                    'start_date'        => now()->subDays(2)->format('Y-m-d'),
                    'end_date'          => now()->addMonths(1)->format('Y-m-d'),
                    // 8 كم × 2.50 د.ل/كم = 20.00 للاتجاه الواحد -> 18.00 بعد خصم 10%
                    'distance_km'       => 8.0,
                ]
            ]
        ];

        $request = $service->createRequest($data, $this->parentUser);
        $service->updateStatus($request, SubscriptionRequest::STATUS_ACCEPTED);

        $response = $this->actingAs($this->parentUser)->getJson('/api/parent/trips/upcoming');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $responseData = $response->json();
        $this->assertNotEmpty($responseData['data']);

        $firstTrip = $responseData['data'][0];
        
        // التحقق من وجود الحقول الأساسية وعدم وجود وجهة عامة للرحلة
        $this->assertArrayHasKey('trip_id', $firstTrip);
        $this->assertArrayHasKey('trip_type', $firstTrip);
        $this->assertArrayHasKey('title', $firstTrip);
        $this->assertArrayHasKey('scheduled_for', $firstTrip);
        $this->assertArrayHasKey('total_children', $firstTrip);
        $this->assertArrayHasKey('driver', $firstTrip);
        $this->assertArrayNotHasKey('destination', $firstTrip, 'يجب عدم وجود وجهة عامة على مستوى الرحلة والاعتماد على وجهة كل طفل');
        $this->assertArrayHasKey('children', $firstTrip);
        $this->assertArrayHasKey('pricing', $firstTrip);

        // السائق
        $this->assertEquals('محمود علي', $firstTrip['driver']['name']);

        // عدد الأطفال 2
        $this->assertEquals(2, $firstTrip['total_children']);
        $this->assertCount(2, $firstTrip['children']);

        // الطفل الأول
        $child1Data = $firstTrip['children'][0];
        $this->assertEquals($this->child1->id, $child1Data['child_id']);
        $this->assertEquals('أحمد محمد', $child1Data['child_name']);
        $this->assertEquals('مدرسة المستقبل الواعد', $child1Data['school_name']);
        $this->assertEquals('9.00', $child1Data['cost_per_child']);
        $this->assertArrayHasKey('home_location', $child1Data);
        $this->assertEquals(32.8752, $child1Data['home_location']['lat']);
        $this->assertEquals(13.1654, $child1Data['home_location']['lng']);
        $this->assertArrayHasKey('school_location', $child1Data);
        $this->assertEquals('مدرسة المستقبل الواعد', $child1Data['school_location']['name']);
        $this->assertEquals(32.8870, $child1Data['school_location']['lat']);
        $this->assertEquals(13.1890, $child1Data['school_location']['lng']);

        // الطفل الثاني (مدرسة مختلفة)
        $child2Data = $firstTrip['children'][1];
        $this->assertEquals($this->child2->id, $child2Data['child_id']);
        $this->assertEquals('سارة محمد', $child2Data['child_name']);
        $this->assertEquals('مدرسة النخبة الدولية', $child2Data['school_name']);
        $this->assertEquals('18.00', $child2Data['cost_per_child']);
        $this->assertArrayHasKey('home_location', $child2Data);
        $this->assertArrayHasKey('school_location', $child2Data);
        $this->assertEquals('مدرسة النخبة الدولية', $child2Data['school_location']['name']);
        $this->assertEquals(32.8950, $child2Data['school_location']['lat']);

        // التسعير الإجمالي للرحلة (9.00 + 18.00 = 27.00)
        $this->assertEquals('27.00', $firstTrip['pricing']['total_trip_cost']);
        $this->assertEquals('13.50', $firstTrip['pricing']['cost_per_child']);
        $this->assertEquals('LYD', $firstTrip['pricing']['currency']);
    }
}
