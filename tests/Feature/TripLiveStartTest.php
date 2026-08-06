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
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\RouteStop;
use App\Models\Shared\TripStop;

/**
 * اختبار بدء الرحلة مع إرسال موقع السائق الحي (Live GPS Lead-In)
 * وتحقق من حفظ start_lat/start_lng وحساب الـ ETAs على trip_stops.
 * ملاحظة: خدمة OSRM غير متاحة في بيئة الاختبار، لذا يعتمد الحساب على
 * GeoEstimator (Haversine) كأداة أساسية دائمة التوفر — وهذا ما يثبته الاختبار ضمنياً.
 */
class TripLiveStartTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected RouteModel $route;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق الرحلة الحية',
            'email'        => 'driver.live.' . uniqid() . '@darby.test',
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
            'current_lat'    => 32.8700,
            'current_lng'    => 13.1800,
        ]);

        $vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'LIVE-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $parentUser = User::create([
            'full_name'    => 'ولي أمر الرحلة الحية',
            'email'        => 'parent.live.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $parent = ParentModel::create(['user_id' => $parentUser->id, 'is_trusted' => 1]);

        $school = School::create([
            'name'    => 'مدرسة الرحلة الحية',
            'address' => 'شارع الاختبار',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        $child = Child::create([
            'parent_id'            => $parent->id,
            'school_id'            => $school->id,
            'full_name'            => 'طفل الرحلة الحية',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        $this->route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $vehicleId,
            'route_name' => 'مسار الرحلة الحية',
            'route_type' => 'Morning',
            'shift_slot' => 'morning_go',
            'start_time' => now()->addMinutes(20)->format('H:i:s'),
            'status'     => 'Active',
        ]);

        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'home', 'child_id' => $child->id, 'lat' => 32.881, 'lng' => 13.191, 'label' => 'المنزل', 'sequence_order' => 1]);
        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'school', 'school_id' => $school->id, 'lat' => 32.90, 'lng' => 13.20, 'label' => 'المدرسة', 'sequence_order' => 2]);
    }

    public function test_starting_trip_with_gps_persists_start_location_and_live_etas(): void
    {
        // 1) السحب اللحظي لرحلات اليوم يولّد الرحلة ومحطاتها تلقائياً (DailyTripGenerationService)
        $todayResponse = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/trips/today');
        $todayResponse->assertStatus(200);

        $tripId = $todayResponse->json('data.0.trip_id');
        $this->assertNotNull($tripId);
        $this->assertDatabaseHas('trip_stops', ['trip_id' => $tripId]);

        // 2) السائق يضغط "بدء الرحلة" مع إرسال موقعه الحي
        $startResponse = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$tripId}/start", [
                'latitude'  => 32.870000,
                'longitude' => 13.180000,
            ]);

        $startResponse->assertStatus(200);

        $this->assertDatabaseHas('trips', [
            'id'        => $tripId,
            'start_lat' => 32.87000000,
            'start_lng' => 13.18000000,
        ]);

        $stops = TripStop::where('trip_id', $tripId)->where('sequence_order', '>', 0)->get();
        $this->assertNotEmpty($stops);

        foreach ($stops as $stop) {
            $this->assertNotNull($stop->eta_minutes, "eta_minutes يجب أن يُحسب للمحطة {$stop->id}");
            $this->assertNotNull($stop->eta, "eta يجب أن يُحسب للمحطة {$stop->id}");
        }
    }

    public function test_starting_trip_without_gps_still_succeeds(): void
    {
        $todayResponse = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/trips/today');
        $tripId = $todayResponse->json('data.0.trip_id');

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$tripId}/start", []);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', ['id' => $tripId, 'status' => 'in_progress']);

        // بدون إحداثيات، لا يُفترض حساب ETAs حية
        $stops = TripStop::where('trip_id', $tripId)->where('sequence_order', '>', 0)->get();
        foreach ($stops as $stop) {
            $this->assertNull($stop->eta_minutes);
        }
    }
}
