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
use App\Services\Trip\DailyTripGenerationService;

/**
 * اختبار إغلاق الفجوات المُبلَّغ عنها في دليل ربط الفرونت:
 * 1) GET /trips/{tripId}/stops و دقة الحالة في show()/live()
 * 2) POST /trips/register-absence + أثرها الفعلي على توليد الرحلات
 * 3) الإبلاغ عن عطل واستئناف الرحلة
 */
class TripLiveGapsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected RouteModel $route;
    protected Child $child;
    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق فجوات الرحلات',
            'email'        => 'driver.gaps.' . uniqid() . '@darby.test',
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

        $vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'تويوتا',
            'model'           => 'هايس',
            'year'            => 2022,
            'color'           => 'أبيض',
            'plate_number'    => 'GAP-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $parentUser = User::create([
            'full_name'    => 'ولي أمر فجوات الرحلات',
            'email'        => 'parent.gaps.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);
        $parent = ParentModel::create(['user_id' => $parentUser->id, 'is_trusted' => 1]);

        $this->school = School::create([
            'name' => 'مدرسة فجوات الرحلات', 'address' => 'شارع الاختبار',
            'lat' => 32.90, 'lng' => 13.20, 'status' => 'active',
        ]);

        $this->child = Child::create([
            'parent_id' => $parent->id, 'school_id' => $this->school->id,
            'full_name' => 'طفل فجوات الرحلات', 'birth_date' => '2018-05-10',
            'gender' => 'male', 'grade' => 1, 'notification_radius' => 500,
        ]);

        $this->route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $vehicleId,
            'route_name' => 'مسار اختبار الفجوات',
            'route_type' => 'Morning',
            'shift_slot' => 'morning_go',
            'start_time' => Carbon::now()->addMinutes(20)->format('H:i:s'),
            'status'     => 'Active',
        ]);

        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'home', 'child_id' => $this->child->id, 'lat' => 32.881, 'lng' => 13.191, 'label' => 'المنزل', 'sequence_order' => 1]);
        RouteStop::create(['route_id' => $this->route->id, 'stop_type' => 'school', 'school_id' => $this->school->id, 'lat' => 32.90, 'lng' => 13.20, 'label' => 'المدرسة', 'sequence_order' => 2]);
    }

    // =========================================================
    // فجوة 1+4: GET /trips/{tripId}/stops ودقة الحالة في show()
    // =========================================================

    public function test_trip_stops_endpoint_returns_ordered_stops_with_status(): void
    {
        $today = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/trips/today');
        $tripId = $today->json('data.0.trip_id');

        $response = $this->actingAs($this->driverUser)->getJson("/api/v1/driver/trips/{$tripId}/stops");

        $response->assertStatus(200);
        $response->assertJsonPath('data.trip_id', $tripId);
        $stops = $response->json('data.stops');
        $this->assertCount(2, $stops);
        $this->assertEquals('home', $stops[0]['stop_type']);
        $this->assertEquals('pending', $stops[0]['status']);
        $this->assertEquals($this->child->id, $stops[0]['child_id']);
        $this->assertEquals('school', $stops[1]['stop_type']);
    }

    public function test_show_and_live_reflect_precise_trip_stop_status_after_boarding(): void
    {
        $parentRecord = ParentModel::where('id', $this->child->parent_id)->first();

        $subscriptionRequest = \App\Models\Shared\SubscriptionRequest::create([
            'parent_id' => $this->child->parent_id, 'driver_id' => $this->driver->id, 'school_id' => $this->school->id,
            'subscription_type' => 'monthly', 'direction' => 'go', 'timing' => 'MORNING',
            'start_date' => now()->format('Y-m-d'), 'end_date' => now()->addMonths(1)->format('Y-m-d'),
            'days_count' => 22, 'total_price' => 100, 'status' => 'accepted', 'children_count' => 1,
        ]);
        $contract = \App\Models\Shared\Contract::create([
            'subscription_request_id' => $subscriptionRequest->id,
            'parent_id' => $parentRecord->user_id, 'driver_id' => $this->driverUser->id,
            'contract_number' => 'DRBY-GAP-' . rand(100000, 999999), 'subscription_type' => 'monthly',
            'direction' => 'go', 'timing' => 'MORNING', 'pickup_time' => '07:00:00', 'dropoff_time' => '14:00:00',
            'max_waiting_time' => 15, 'start_date' => now()->format('Y-m-d'), 'end_date' => now()->addMonths(1)->format('Y-m-d'),
            'days_count' => 22, 'total_price' => 100, 'clauses' => [], 'status' => 'active', 'signed_at' => now(),
        ]);
        $sub = \App\Models\Shared\ActiveSubscription::create([
            'contract_id' => $contract->id, 'status' => 'active', 'child_id' => $this->child->id,
            'driver_id' => $this->driver->id, 'route_id' => $this->route->id, 'parent_id' => $parentRecord->user_id,
            'pickup_lat' => 32.881, 'pickup_lng' => 13.191, 'pickup_label' => 'المنزل', 'pickup_time' => '07:00:00',
            'dropoff_lat' => 32.90, 'dropoff_lng' => 13.20, 'dropoff_label' => 'المدرسة', 'dropoff_time' => '14:00:00',
        ]);

        $today = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/trips/today');
        $tripId = $today->json('data.0.trip_id');

        // قبل الصعود: pending
        $show = $this->actingAs($this->driverUser)->getJson("/api/v1/driver/trips/{$tripId}");
        $show->assertJsonPath('data.children.0.status', 'pending');
        $show->assertJsonPath('data.children.0.pickup_status', 'pending');

        // تأكيد الصعود عبر QR (يتجاوز فحص الموقع)
        $this->actingAs($this->driverUser)->postJson(
            "/api/v1/driver/trips/{$tripId}/verify-qr/{$sub->id}",
            ['qr_code_token' => $this->child->qr_code_token]
        )->assertStatus(200);

        // بعد الصعود: boarded (وليس القيمة القديمة picked_up)
        $showAfter = $this->actingAs($this->driverUser)->getJson("/api/v1/driver/trips/{$tripId}");
        $showAfter->assertJsonPath('data.children.0.status', 'boarded');
        $showAfter->assertJsonPath('data.children.0.pickup_status', 'picked_up');

        $live = $this->actingAs($this->driverUser)->getJson("/api/v1/driver/trips/{$tripId}/live");
        $live->assertStatus(200);
        $this->assertEquals(1, $live->json('data.progress.total'));
        $this->assertEquals(0, $live->json('data.progress.completed'));
    }

    // =========================================================
    // فجوة 2: تسجيل غياب السائق + أثره على توليد الرحلات
    // =========================================================

    public function test_driver_can_register_absence(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/trips/register-absence', ['dates' => [$tomorrow]]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('driver_absences', [
            'driver_id'     => $this->driver->id,
            'absence_date'  => $tomorrow,
        ]);
    }

    public function test_absent_driver_route_is_not_generated(): void
    {
        $today = Carbon::today()->toDateString();

        DB::table('driver_absences')->insert([
            'driver_id' => $this->driver->id, 'absence_date' => $today,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(DailyTripGenerationService::class);
        $trip = $service->generateForRoute($this->route);

        $this->assertNull($trip);
        $this->assertDatabaseMissing('trips', ['route_id' => $this->route->id, 'trip_date' => $today]);
    }

    public function test_register_absence_rejects_past_dates(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/trips/register-absence', ['dates' => ['2020-01-01']]);

        $response->assertStatus(422);
    }

    // =========================================================
    // فجوة 3: الإبلاغ عن عطل واستئناف الرحلة
    // =========================================================

    public function test_driver_can_report_breakdown_on_in_progress_trip(): void
    {
        $trip = Trip::create([
            'driver_id' => $this->driver->id, 'route_id' => $this->route->id,
            'trip_type' => 'Morning', 'shift_slot' => 'morning_go', 'status' => 'in_progress',
            'trip_date' => now()->toDateString(), 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$trip->id}/report-breakdown", ['reason' => 'إطار مثقوب']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id, 'status' => 'suspended_breakdown', 'suspension_reason' => 'إطار مثقوب',
        ]);
    }

    public function test_cannot_report_breakdown_on_pending_trip(): void
    {
        $trip = Trip::create([
            'driver_id' => $this->driver->id, 'route_id' => $this->route->id,
            'trip_type' => 'Morning', 'shift_slot' => 'morning_go', 'status' => 'pending',
            'trip_date' => now()->toDateString(), 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$trip->id}/report-breakdown", []);

        $response->assertStatus(422);
    }

    public function test_driver_can_resume_suspended_trip(): void
    {
        $trip = Trip::create([
            'driver_id' => $this->driver->id, 'route_id' => $this->route->id,
            'trip_type' => 'Morning', 'shift_slot' => 'morning_go', 'status' => 'suspended_breakdown',
            'suspension_reason' => 'عطل مؤقت',
            'trip_date' => now()->toDateString(), 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$trip->id}/resume");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'in_progress', 'suspension_reason' => null]);
    }

    public function test_cannot_resume_trip_that_is_not_suspended(): void
    {
        $trip = Trip::create([
            'driver_id' => $this->driver->id, 'route_id' => $this->route->id,
            'trip_type' => 'Morning', 'shift_slot' => 'morning_go', 'status' => 'in_progress',
            'trip_date' => now()->toDateString(), 'scheduled_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/trips/{$trip->id}/resume");

        $response->assertStatus(422);
    }
}
