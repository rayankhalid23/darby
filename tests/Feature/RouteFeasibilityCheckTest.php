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
use App\Services\Trip\RouteFeasibilityService;

/**
 * اختبار فحص إمكانية إضافة طلب اشتراك جديد (Feasibility Check) دون حفظ أي بيانات.
 * GET /api/driver/requests/{id}/feasibility-check
 */
class RouteFeasibilityCheckTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected ParentModel $parent;
    protected User $parentUser;
    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق فحص الإمكانية',
            'email'        => 'driver.feas.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        DriverSeatSlot::create([
            'driver_id'      => $this->driver->id,
            'slot'           => 'morning_go',
            'total_seats'    => 4,
            'reserved_seats' => 0,
        ]);

        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر فحص الإمكانية',
            'email'        => 'parent.feas.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->school = School::create([
            'name'    => 'مدرسة فحص الإمكانية',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);
    }

    private function makePendingRequest(float $homeLat, float $homeLng): SubscriptionRequest
    {
        $child = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل فحص الإمكانية',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل',
            'lat'        => $homeLat,
            'lng'        => $homeLng,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $req = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'go',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 100.00,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $req->id,
            'child_id'           => $child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => $homeLat,
            'home_lng'           => $homeLng,
            'home_label'         => 'المنزل',
            'school_lat'         => 32.90,
            'school_lng'         => 13.20,
            'school_label'       => 'المدرسة',
            'price_per_child'    => 100.00,
        ]);

        return $req;
    }

    public function test_feasibility_check_endpoint_returns_feasible_for_pending_request(): void
    {
        $req = $this->makePendingRequest(32.881, 13.191);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$req->id}/feasibility-check");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.overall_feasible', true);

        $slots = $response->json('data.slots');
        $this->assertNotEmpty($slots);
        $this->assertEquals('morning_go', $slots[0]['shift_slot']);
        $this->assertTrue($slots[0]['feasible']);
        $this->assertNull($slots[0]['reason']);
    }

    public function test_feasibility_check_fails_when_no_seats_available(): void
    {
        DriverSeatSlot::where('driver_id', $this->driver->id)
            ->where('slot', 'morning_go')
            ->update(['reserved_seats' => 4]);

        $req = $this->makePendingRequest(32.881, 13.191);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$req->id}/feasibility-check");

        $response->assertStatus(200);
        $response->assertJsonPath('data.overall_feasible', false);
        $response->assertJsonPath('data.slots.0.reason', 'insufficient_seats');
    }

    public function test_service_reports_infeasible_when_duration_exceeds_max(): void
    {
        $req = $this->makePendingRequest(32.881, 13.191);
        $req->load('children', 'driver.seatSlots');

        $service = app(RouteFeasibilityService::class);
        $result = $service->checkForRequest($req, 0);

        $this->assertFalse($result['overall_feasible']);
        $this->assertEquals('exceeds_max_trip_duration', $result['slots'][0]['reason']);
    }

    public function test_driver_cannot_check_feasibility_for_another_drivers_request(): void
    {
        $otherDriverUser = User::create([
            'full_name'    => 'سائق آخر',
            'email'        => 'driver.other.feas.' . uniqid() . '@darby.test',
            'phone_number' => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);
        Driver::create([
            'user_id'        => $otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $req = $this->makePendingRequest(32.881, 13.191);

        $response = $this->actingAs($otherDriverUser)
            ->getJson("/api/driver/requests/{$req->id}/feasibility-check");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $req = $this->makePendingRequest(32.881, 13.191);

        $response = $this->getJson("/api/driver/requests/{$req->id}/feasibility-check");

        $response->assertStatus(401);
    }
}
