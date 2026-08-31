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
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\Route as RouteModel;

class DriverTripDetailsGpsCoordinatesTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected Trip $trip;
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

        $this->driverUser = User::create([
            'full_name'     => 'أحمد السائق',
            'email'         => 'driver.gps.' . uniqid() . '@darby.test',
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

        $parentUser = User::create([
            'full_name'     => 'محمد ولي الأمر',
            'email'         => 'parent.gps.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $parent = ParentModel::create(['user_id' => $parentUser->id, 'is_trusted' => 1]);

        $this->school1 = School::create([
            'name'    => 'مدرسة طرابلس المركز النموذجية',
            'address' => 'شارع الجمهورية، طرابلس',
            'lat'     => 32.895420,
            'lng'     => 13.185670,
            'status'  => 'active',
        ]);

        $this->school2 = School::create([
            'name'    => 'مدرسة قرطبة الحديثة',
            'address' => 'حي دمشق، طرابلس',
            'lat'     => 32.864310,
            'lng'     => 13.178920,
            'status'  => 'active',
        ]);

        $address1 = Address::create([
            'parent_id' => $parentUser->id,
            'label'     => 'حي الأندلس - بالقرب من جامع الشريف',
            'lat'       => 32.871230,
            'lng'       => 13.156780,
        ]);

        $address2 = Address::create([
            'parent_id' => $parentUser->id,
            'label'     => 'غوط الشعال - شارع الغربي',
            'lat'       => 32.862340,
            'lng'       => 13.141250,
        ]);

        $this->child1 = Child::create([
            'parent_id'           => $parent->id,
            'school_id'           => $this->school1->id,
            'address_id'          => $address1->id,
            'full_name'           => 'سارة محمد',
            'birth_date'          => '2016-04-12',
            'gender'              => 'female',
            'grade'               => 3,
            'notification_radius' => 500,
        ]);

        $this->child2 = Child::create([
            'parent_id'           => $parent->id,
            'school_id'           => $this->school2->id,
            'address_id'          => $address2->id,
            'full_name'           => 'علي محمد',
            'birth_date'          => '2018-09-20',
            'gender'              => 'male',
            'grade'               => 1,
            'notification_radius' => 500,
        ]);

        $vehicle = \App\Models\Driver\Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => '5-12345',
            'brand'           => 'Toyota',
            'model'           => 'HiAce',
            'year'            => 2022,
            'color'           => 'White',
            'type'            => 'Van',
            'capacity_manual' => 14,
            'status'          => 'active',
        ]);

        $route = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $vehicle->id,
            'route_name'         => 'مسار حي الأندلس - المدارس المركزية',
            'route_type'         => 'Morning',
            'status'             => 'Active',
            'start_time'         => '07:00',
            'estimated_duration' => 45,
            'total_distance'     => 14.5,
        ]);

        $subscriptionRequest = SubscriptionRequest::create([
            'parent_id'                   => $parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => 250,
            'discount_amount'             => 0,
            'total_amount_after_discount' => 250,
            'children_count'              => 2,
        ]);

        $sub1 = ActiveSubscription::create([
            'subscription_request_id' => $subscriptionRequest->id,
            'status'                  => 'active',
            'child_id'                => $this->child1->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $parentUser->id,
            'school_id'               => $this->school1->id,
            'route_id'                => $route->id,
            'pickup_lat'              => 32.871230,
            'pickup_lng'              => 13.156780,
            'pickup_label'            => 'حي الأندلس - بالقرب من جامع الشريف',
            'pickup_time'             => '07:15:00',
            'dropoff_lat'             => 32.895420,
            'dropoff_lng'             => 13.185670,
            'dropoff_label'           => 'مدرسة طرابلس المركز النموذجية',
            'dropoff_time'            => '07:50:00',
        ]);

        $sub2 = ActiveSubscription::create([
            'subscription_request_id' => $subscriptionRequest->id,
            'status'                  => 'active',
            'child_id'                => $this->child2->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $parentUser->id,
            'school_id'               => $this->school2->id,
            'route_id'                => $route->id,
            'pickup_lat'              => 32.862340,
            'pickup_lng'              => 13.141250,
            'pickup_label'            => 'غوط الشعال - شارع الغربي',
            'pickup_time'             => '07:25:00',
            'dropoff_lat'             => 32.864310,
            'dropoff_lng'             => 13.178920,
            'dropoff_label'           => 'مدرسة قرطبة الحديثة',
            'dropoff_time'            => '08:00:00',
        ]);

        $this->trip = Trip::create([
            'driver_id'    => $this->driver->id,
            'route_id'     => $route->id,
            'trip_type'    => 'Morning',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        TripStop::create([
            'trip_id'        => $this->trip->id,
            'child_id'       => $this->child1->id,
            'school_id'      => $this->school1->id,
            'stop_type'      => 'home',
            'lat'            => 32.871230,
            'lng'            => 13.156780,
            'label'          => 'حي الأندلس - بالقرب من جامع الشريف',
            'sequence_order' => 1,
            'status'         => 'pending',
        ]);

        TripStop::create([
            'trip_id'        => $this->trip->id,
            'child_id'       => $this->child2->id,
            'school_id'      => $this->school2->id,
            'stop_type'      => 'home',
            'lat'            => 32.862340,
            'lng'            => 13.141250,
            'label'          => 'غوط الشعال - شارع الغربي',
            'sequence_order' => 2,
            'status'         => 'pending',
        ]);
    }

    public function test_get_driver_trip_returns_real_gps_coordinates_for_children_and_schools(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/trips/{$this->trip->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $data = $response->json('data');

        // التحقق من مصفوفة المدارس schools
        $this->assertNotEmpty($data['schools']);
        foreach ($data['schools'] as $schoolItem) {
            $this->assertArrayHasKey('latitude', $schoolItem);
            $this->assertArrayHasKey('longitude', $schoolItem);
            $this->assertIsFloat($schoolItem['latitude']);
            $this->assertIsFloat($schoolItem['longitude']);
            $this->assertNotEquals(0, $schoolItem['latitude']);
            $this->assertNotEquals(0, $schoolItem['longitude']);
        }

        // التحقق من مصفوفة الأطفال children
        $this->assertNotEmpty($data['children']);
        foreach ($data['children'] as $childItem) {
            $this->assertArrayHasKey('latitude', $childItem);
            $this->assertArrayHasKey('longitude', $childItem);
            $this->assertIsFloat($childItem['latitude']);
            $this->assertIsFloat($childItem['longitude']);
            $this->assertNotEquals(0, $childItem['latitude']);
            $this->assertNotEquals(0, $childItem['longitude']);

            // التحقق أيضاً من كائنات الموقع الفرعية
            $this->assertArrayHasKey('home_location', $childItem);
            $this->assertEquals($childItem['latitude'], $childItem['home_location']['latitude']);
            $this->assertEquals($childItem['longitude'], $childItem['home_location']['longitude']);

            $this->assertArrayHasKey('school_location', $childItem);
            $this->assertArrayHasKey('latitude', $childItem['school_location']);
            $this->assertArrayHasKey('longitude', $childItem['school_location']);
        }

        file_put_contents(base_path('tests/Feature/last_trip_response.json'), json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}