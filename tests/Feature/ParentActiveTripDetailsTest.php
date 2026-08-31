<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Parent\Address;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Shared\Trip;
use App\Models\Shared\ActiveSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ParentActiveTripDetailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_active_trips_returns_driver_vehicle_occupancy_home_address_and_school()
    {
        $parentUser = User::create([
            'full_name'     => 'طارق الزنتاني',
            'email'         => 'parent.test.' . uniqid() . '@darby.test',
            'phone_number'  => '0912223344',
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $parentModel = ParentModel::create(['user_id' => $parentUser->id]);

        $driverUser = User::create([
            'full_name'     => 'محمود الترهوني',
            'email'         => 'driver.test.' . uniqid() . '@darby.test',
            'phone_number'  => '0925556677',
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $driver = Driver::create([
            'user_id'           => $driverUser->id,
            'national_id'       => 'NAT' . rand(100000, 999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->toDateString(),
            'status'            => 'Approved',
            'gender'            => 'male',
            'accepted_gender'   => 'both',
            'subscription_type' => 'both',
            'morning_go'        => 1,
        ]);
        $vehicle = Vehicle::create([
            'driver_id'       => $driver->id,
            'brand'           => 'Toyota',
            'model'           => 'Hiace',
            'year'            => '2023',
            'color'           => 'أبيض',
            'plate_number'    => '5-99887',
            'capacity_manual' => 14,
            'is_verified'     => true,
        ]);

        $homeAddress = Address::create([
            'parent_id' => $parentUser->id,
            'label'     => 'حي الأندلس - بالقرب من مصحة الأمل',
            'lat'       => 32.875210,
            'lng'       => 13.165420,
        ]);

        $school1 = School::create([
            'name'    => 'مدرسة المستقبل الدولية',
            'address' => 'النوفليين - طرابلس',
            'lat'     => 32.890000,
            'lng'     => 13.180000,
            'status'  => 'active',
        ]);

        $school2 = School::create([
            'name'    => 'مدرسة براعم المعرفة',
            'address' => 'طريق الشط - طرابلس',
            'lat'     => 32.898000,
            'lng'     => 13.190000,
            'status'  => 'active',
        ]);

        $child1 = Child::create([
            'parent_id'           => $parentModel->id,
            'full_name'           => 'أحمد طارق',
            'birth_date'          => '2015-05-10',
            'gender'              => 'male',
            'grade'               => 5,
            'address_id'          => $homeAddress->id,
            'school_id'           => $school1->id,
            'notification_radius' => 500,
        ]);

        $child2 = Child::create([
            'parent_id'           => $parentModel->id,
            'full_name'           => 'سارة طارق',
            'birth_date'          => '2017-08-15',
            'gender'              => 'female',
            'grade'               => 3,
            'address_id'          => $homeAddress->id,
            'school_id'           => $school2->id,
            'notification_radius' => 500,
        ]);

        $subRequest = \App\Models\Shared\SubscriptionRequest::create([
            'parent_id'                   => $parentModel->id,
            'driver_id'                   => $driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 360.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 360.00,
        ]);

        $sub1 = ActiveSubscription::create([
            'subscription_request_id' => $subRequest->id,
            'child_id'      => $child1->id,
            'driver_id'     => $driver->id,
            'parent_id'     => $parentUser->id,
            'school_id'     => $school1->id,
            'pickup_lat'    => 32.875210,
            'pickup_lng'    => 13.165420,
            'pickup_label'  => 'حي الأندلس',
            'dropoff_lat'   => 32.890000,
            'dropoff_lng'   => 13.180000,
            'dropoff_label' => 'مدرسة المستقبل',
            'pickup_time'   => '07:15:00',
            'status'        => 'active',
        ]);

        $sub2 = ActiveSubscription::create([
            'subscription_request_id' => $subRequest->id,
            'child_id'      => $child2->id,
            'driver_id'     => $driver->id,
            'parent_id'     => $parentUser->id,
            'school_id'     => $school2->id,
            'pickup_lat'    => 32.875210,
            'pickup_lng'    => 13.165420,
            'pickup_label'  => 'حي الأندلس',
            'dropoff_lat'   => 32.898000,
            'dropoff_lng'   => 13.190000,
            'dropoff_label' => 'مدرسة براعم المعرفة',
            'pickup_time'   => '07:20:00',
            'status'        => 'active',
        ]);

        $trip = Trip::create([
            'driver_id'          => $driver->id,
            'trip_type'          => 'Morning',
            'status'             => 'in_progress',
            'trip_date'          => Carbon::today()->toDateString(),
            'actual_start_time'  => Carbon::now()->subMinutes(20),
        ]);

        DB::table('trip_stops')->insert([
            [
                'trip_id'        => $trip->id,
                'child_id'       => $child1->id,
                'stop_type'      => 'home',
                'status'         => 'boarded',
                'sequence_order' => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'trip_id'        => $trip->id,
                'child_id'       => $child2->id,
                'stop_type'      => 'home',
                'status'         => 'pending',
                'sequence_order' => 2,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);

        $response = $this->actingAs($parentUser, 'sanctum')
            ->getJson('/api/parent/trips/active');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                '*' => [
                    'trip_id',
                    'trip_type',
                    'direction',
                    'status',
                    'started_at',
                    'driver' => ['id', 'name', 'phone', 'photo'],
                    'vehicle' => ['info', 'plate_number', 'capacity'],
                    'bus_occupancy' => ['current_onboard_count', 'total_trip_children'],
                    'children' => [
                        '*' => [
                            'child_id',
                            'child_name',
                            'child_photo',
                            'child_status',
                            'pickup_time',
                            'dropoff_time',
                            'home_address' => ['title', 'street', 'lat', 'lng'],
                            'school' => ['id', 'name', 'branch', 'address', 'lat', 'lng'],
                        ],
                    ],
                ],
            ],
        ]);

        $data = $response->json('data.0');
        $this->assertEquals($driver->id, $data['driver']['id']);
        $this->assertEquals('morning', $data['trip_type']);
        $this->assertEquals('to_school', $data['direction']);
        $this->assertEquals('in_progress', $data['status']);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}\s+(AM|PM)$/', $data['started_at']);
        $this->assertEquals('Toyota Hiace 2023 - أبيض', $data['vehicle']['info']);
        $this->assertEquals('5-99887', $data['vehicle']['plate_number']);
        $this->assertEquals(14, $data['vehicle']['capacity']);
        $this->assertEquals(1, $data['bus_occupancy']['current_onboard_count']);
        $this->assertEquals(2, $data['bus_occupancy']['total_trip_children']);
        $this->assertCount(2, $data['children']);

        $child1Data = collect($data['children'])->firstWhere('child_id', $child1->id);
        $this->assertEquals('onboard', $child1Data['child_status']);
        $this->assertEquals('مدرسة المستقبل الدولية', $child1Data['school']['name']);
        $this->assertEquals('حي الأندلس - بالقرب من مصحة الأمل', $child1Data['home_address']['title']);

        $child2Data = collect($data['children'])->firstWhere('child_id', $child2->id);
        $this->assertEquals('waiting', $child2Data['child_status']);
        $this->assertEquals('مدرسة براعم المعرفة', $child2Data['school']['name']);
    }
}
