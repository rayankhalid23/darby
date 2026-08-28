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
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;

class ActiveSubscriptionsDisplayTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected School $school;
    protected int $addressId;
    protected Child $child1;
    protected Child $child2;
    protected SubscriptionRequest $acceptedRequest;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. Driver
        $this->driverUser = User::create([
            'full_name'     => 'الكابتن طارق السائق',
            'email'         => 'driver.test.' . uniqid() . '@darby.test',
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
            'plate_number'    => 'TRIP-' . rand(1000, 9999),
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

        // 2. Parent
        $this->parentUser = User::create([
            'full_name'     => 'الأستاذ أحمد ولي الأمر',
            'email'         => 'parent.test.' . uniqid() . '@darby.test',
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
            'name'    => 'مدرسة الفتح النموذجية',
            'address' => 'شارع النصر، طرابلس',
            'lat'     => 32.8950,
            'lng'     => 13.1950,
            'status'  => 'active',
        ]);

        // 4. Address
        $this->addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل العائلة',
            'lat'        => 32.8800,
            'lng'        => 13.1800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Children
        $this->child1 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->addressId,
            'full_name'  => 'سليم أحمد',
            'birth_date' => '2015-05-10',
            'gender'     => 'male',
            'grade'      => 4,
        ]);

        $this->child2 = Child::create([
            'parent_id'  => $this->parent->id,
            'school_id'  => $this->school->id,
            'address_id' => $this->addressId,
            'full_name'  => 'مريم أحمد',
            'birth_date' => '2017-08-15',
            'gender'     => 'female',
            'grade'      => 2,
        ]);

        // 6. Create an Accepted Subscription Request with 2 children
        $this->acceptedRequest = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 600.00,
            'discount_amount'             => 50.00,
            'total_amount_after_discount' => 550.00,
            'notes'                       => 'اشتراك نشط تجريبي',
        ]);

        DB::table('request_children')->insert([
            [
                'request_id'                  => $this->acceptedRequest->id,
                'child_id'                    => $this->child1->id,
                'subscription_type'           => 'multi_day',
                'trip_direction'              => 'both',
                'timing'                      => 'MORNING',
                'start_date'                  => now()->addDays(1)->format('Y-m-d'),
                'end_date'                    => now()->addMonths(1)->format('Y-m-d'),
                'working_days_count'          => 22,
                'distance_km'                 => 6.5,
                'trip_price'                  => 30.0,
                'price_per_child'             => 300.00,
                'discount_amount'             => 25.00,
                'total_amount_after_discount' => 275.00,
                'driver_net_price'            => 253.00,
                'child_notes'                 => 'ملاحظات صحية لسليم',
            ],
            [
                'request_id'                  => $this->acceptedRequest->id,
                'child_id'                    => $this->child2->id,
                'subscription_type'           => 'multi_day',
                'trip_direction'              => 'both',
                'timing'                      => 'MORNING',
                'start_date'                  => now()->addDays(1)->format('Y-m-d'),
                'end_date'                    => now()->addMonths(1)->format('Y-m-d'),
                'working_days_count'          => 22,
                'distance_km'                 => 6.5,
                'trip_price'                  => 30.0,
                'price_per_child'             => 300.00,
                'discount_amount'             => 25.00,
                'total_amount_after_discount' => 275.00,
                'driver_net_price'            => 253.00,
                'child_notes'                 => 'ملاحظات صحية لمريم',
            ]
        ]);

        ActiveSubscription::create([
            'subscription_request_id' => $this->acceptedRequest->id,
            'child_id'                => $this->child1->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
        ]);

        ActiveSubscription::create([
            'subscription_request_id' => $this->acceptedRequest->id,
            'child_id'                => $this->child2->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'status'                  => 'active',
        ]);
    }

    /**
     * اختبار تطابق صيغة عرض الاشتراكات النشطة لولي الأمر تماماً مع صيغة طلبات الاشتراك
     */
    public function test_parent_active_subscriptions_matches_subscription_requests_format(): void
    {
        $response = $this->actingAs($this->parentUser)->getJson('/api/parent/active-subscriptions');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'total_price',
                    'notes',
                    'driver' => [
                        'id',
                        'name',
                        'phone',
                        'photo',
                    ],
                    'children' => [
                        '*' => [
                            'id',
                            'name',
                            'photo',
                            'details' => [
                                'subscription_type',
                                'trip_direction',
                                'timing',
                                'start_date',
                                'end_date',
                                'working_days_count',
                                'distance_km',
                                'trip_price',
                                'price_per_child',
                            ],
                            'Home' => [
                                'id',
                                'name',
                                'address',
                                'latitude',
                                'longitude',
                            ],
                            'School' => [
                                'id',
                                'name',
                                'address',
                                'latitude',
                                'longitude',
                            ]
                        ]
                    ],
                    'created_at',
                    'updated_at',
                ]
            ],
            'status',
            'message'
        ]);

        $item = $response->json('data.0');
        $this->assertEquals($this->acceptedRequest->id, $item['id']);
        $this->assertEquals('accepted', $item['status']);
        $this->assertEquals($this->driver->id, $item['driver']['id']);
        $this->assertCount(2, $item['children']);
        $this->assertEquals('سليم أحمد', $item['children'][0]['name']);
        $this->assertEquals('multi_day', $item['children'][0]['details']['subscription_type']);
        $this->assertEquals(300.00, $item['children'][0]['details']['price_per_child']);
    }

    /**
     * اختبار استعراض تفاصيل اشتراك نشط محدد لولي الأمر بنفس صيغة طلب الاشتراك
     */
    public function test_parent_show_active_subscription_matches_request_format(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson("/api/parent/active-subscriptions/{$this->acceptedRequest->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.id', $this->acceptedRequest->id);
        $response->assertJsonPath('data.driver.name', $this->driverUser->full_name);
        $this->assertCount(2, $response->json('data.children'));
        $this->assertArrayHasKey('Home', $response->json('data.children.0'));
        $this->assertArrayHasKey('School', $response->json('data.children.0'));
    }

    /**
     * اختبار تطابق صيغة عرض الاشتراكات النشطة للسائق تماماً مع صيغة طلبات الاشتراك
     */
    public function test_driver_active_subscriptions_matches_subscription_requests_format(): void
    {
        $response = $this->actingAs($this->driverUser)->getJson('/api/driver/active-subscriptions');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'status' => [
                        'value',
                    ],
                    'notes',
                    'total_amount',
                    'currency',
                    'children_count',
                    'parent' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'avatar',
                    ],
                    'children' => [
                        '*' => [
                            'id',
                            'name',
                            'gender',
                            'age',
                            'grade',
                            'photo_url',
                            'notes' => [
                                'child_notes',
                            ],
                            'pricing' => [
                                'trip_price',
                                'total_price',
                            ],
                            'subscription_period' => [
                                'start_date',
                                'end_date',
                                'working_days_count',
                            ],
                            'trip_details' => [
                                'subscription_type',
                                'trip_direction',
                                'timing',
                            ],
                            'school' => [
                                'id',
                                'name',
                                'address',
                                'lat',
                                'lng',
                            ],
                            'home' => [
                                'address',
                                'lat',
                                'lng',
                            ],
                        ]
                    ],
                    'created_at',
                    'created_at_formatted',
                ]
            ],
            'status',
            'message'
        ]);

        $item = $response->json('data.0');
        $this->assertEquals($this->acceptedRequest->id, $item['id']);
        $this->assertEquals('accepted', $item['status']['value']);
        $this->assertEquals(506.00, $item['total_amount']);
        $this->assertEquals($this->parent->id, $item['parent']['id']);
        $this->assertCount(2, $item['children']);
        $this->assertEquals(253.00, $item['children'][0]['pricing']['total_price']);
    }

    /**
     * اختبار استعراض تفاصيل اشتراك نشط محدد للسائق بنفس صيغة طلب الاشتراك
     */
    public function test_driver_show_active_subscription_matches_request_format(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/active-subscriptions/{$this->acceptedRequest->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.id', $this->acceptedRequest->id);
        $response->assertJsonPath('data.parent.name', $this->parentUser->full_name);
        $this->assertCount(2, $response->json('data.children'));
        $this->assertEquals(506.00, $response->json('data.total_amount'));
    }

    /**
     * اختبار الفلترة في الاشتراكات النشطة
     */
    public function test_active_subscriptions_filter_support(): void
    {
        // 1. فلتر active
        $parentRes = $this->actingAs($this->parentUser)->getJson('/api/parent/active-subscriptions?filter=active');
        $parentRes->assertStatus(200);
        $this->assertCount(1, $parentRes->json('data'));

        $driverRes = $this->actingAs($this->driverUser)->getJson('/api/driver/active-subscriptions?filter=current_active');
        $driverRes->assertStatus(200);
        $this->assertCount(1, $driverRes->json('data'));

        // 2. فلتر cancelled
        $cancelledRes = $this->actingAs($this->parentUser)->getJson('/api/parent/active-subscriptions?filter=cancelled');
        $cancelledRes->assertStatus(200);
        $this->assertCount(0, $cancelledRes->json('data'));
    }
}
