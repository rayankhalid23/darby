<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\RouteStop;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\AbsenceLog;
use App\Services\Trip\DailyTripGenerationService;

/**
 * اختبار توليد الرحلة اليومية (Daily Trip) من المسار الهيكلي المرجعي (Master Route)
 * مع فلترة الغيابات المسبقة، والتوليد الآلي ضمن نافذة T-30 دقيقة.
 */
class DailyTripGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected Driver $driver;
    protected ParentModel $parent;
    protected School $school;
    protected RouteModel $route;
    protected Child $childA;
    protected Child $childB;
    protected int $vehicleId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $driverUser = User::create([
            'full_name'    => 'سائق توليد الرحلات',
            'email'        => 'driver.gen.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'GEN-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $parentUser = User::create([
            'full_name'    => 'ولي أمر توليد الرحلات',
            'email'        => 'parent.gen.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->school = School::create([
            'name'    => 'مدرسة توليد الرحلات',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        $this->childA = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل أ',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $this->childB = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'طفل ب',
            'birth_date'           => '2019-01-15',
            'gender'               => 'female',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $this->route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $this->vehicleId,
            'route_name' => 'مسار اختبار التوليد',
            'route_type' => 'Morning',
            'shift_slot' => 'morning_go',
            'start_time' => Carbon::now()->addMinutes(20)->format('H:i:s'),
            'status'     => 'Active',
        ]);

        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'home', 'child_id' => $this->childA->id, 'lat' => 32.881, 'lng' => 13.191, 'label' => 'منزل أ', 'sequence_order' => 1]);
        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'home', 'child_id' => $this->childB->id, 'lat' => 32.882, 'lng' => 13.192, 'label' => 'منزل ب', 'sequence_order' => 2]);
        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'school', 'school_id' => $this->school->id, 'lat' => 32.90, 'lng' => 13.20, 'label' => 'المدرسة', 'sequence_order' => 3]);
    }

    public function test_generates_trip_and_trip_stops_from_route_stops(): void
    {
        $service = app(DailyTripGenerationService::class);
        $trip = $service->generateForRoute($this->route);

        $this->assertNotNull($trip);
        $this->assertDatabaseHas('trips', [
            'id'        => $trip->id,
            'driver_id' => $this->driver->id,
            'route_id'  => $this->route->id,
        ]);

        $stops = TripStop::where('trip_id', $trip->id)->get();
        $this->assertCount(3, $stops);
        $this->assertTrue($stops->where('child_id', $this->childA->id)->isNotEmpty());
        $this->assertTrue($stops->where('school_id', $this->school->id)->isNotEmpty());
    }

    public function test_absent_child_is_excluded_via_absence_logs(): void
    {
        AbsenceLog::create([
            'child_id'     => $this->childA->id,
            'absence_date' => Carbon::today()->toDateString(),
            'absence_type' => 'pickup',
        ]);

        $service = app(DailyTripGenerationService::class);
        $trip = $service->generateForRoute($this->route);

        $stopA = TripStop::where('trip_id', $trip->id)->where('child_id', $this->childA->id)->first();
        $this->assertEquals('absent_pre', $stopA->status);
        $this->assertEquals(0, $stopA->sequence_order);

        $stopB = TripStop::where('trip_id', $trip->id)->where('child_id', $this->childB->id)->first();
        $this->assertEquals('pending', $stopB->status);
        $this->assertGreaterThan(0, $stopB->sequence_order);
    }

    public function test_generation_is_idempotent_on_repeat_calls(): void
    {
        $service = app(DailyTripGenerationService::class);
        $trip1 = $service->generateForRoute($this->route);
        $trip2 = $service->generateForRoute($this->route);

        $this->assertEquals($trip1->id, $trip2->id);
        $this->assertEquals(1, Trip::where('route_id', $this->route->id)->count());
        $this->assertEquals(3, TripStop::where('trip_id', $trip1->id)->count());
    }

    public function test_generate_due_trips_only_generates_within_t30_window(): void
    {
        $farRoute = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $this->vehicleId,
            'route_name' => 'مسار بعيد عن وقت الانطلاق',
            'route_type' => 'Afternoon',
            'shift_slot' => 'afternoon_go',
            'start_time' => Carbon::now()->addHours(3)->format('H:i:s'),
            'status'     => 'Active',
        ]);

        $service = app(DailyTripGenerationService::class);
        $result = $service->generateDueTrips();

        $this->assertGreaterThanOrEqual(1, $result['generated']);
        $this->assertDatabaseHas('trips', ['route_id' => $this->route->id]);
        $this->assertDatabaseMissing('trips', ['route_id' => $farRoute->id]);
    }
}
