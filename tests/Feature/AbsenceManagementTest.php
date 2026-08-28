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
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\AbsenceLog;
use App\Models\Driver\DriverAbsence;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\Route as RouteModel;
use Carbon\Carbon;

class AbsenceManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;
    protected RouteModel $route;
    protected SubscriptionRequest $subscriptionRequest;
    protected ActiveSubscription $activeSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق تجريبي للاختبار',
            'email'         => 'driver.abs.' . uniqid() . '@darby.test',
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
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1913,
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ولي أمر تجريبي للاختبار',
            'email'         => 'parent.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        $this->school = School::create([
            'name'      => 'مدرسة طرابلس النموذجية',
            'lat'       => 32.8900,
            'lng'       => 13.1900,
            'address'   => 'شارع الجمهورية، طرابلس',
            'status'    => 'approved',
        ]);

        $this->child = Child::create([
            'parent_id'       => $this->parent->id,
            'school_id'       => $this->school->id,
            'full_name'       => 'أحمد التجريبي',
            'gender'          => 'Male',
            'birth_date'      => '2015-05-15',
            'grade'           => '5',
        ]);

        $this->subscriptionRequest = SubscriptionRequest::create([
            'parent_id'                   => $this->parent->id,
            'driver_id'                   => $this->driver->id,
            'status'                      => 'accepted',
            'total_price'                 => 350.00,
            'discount_amount'             => 0,
            'total_amount_after_discount' => 350.00,
            'children_count'              => 1,
        ]);

        $this->subscriptionRequest->children()->attach($this->child->id, [
            'subscription_type'           => 'monthly',
            'trip_direction'              => 'two_way',
            'timing'                      => 'MORNING',
            'start_date'                  => Carbon::today()->startOfMonth()->toDateString(),
            'end_date'                    => Carbon::today()->addMonths(2)->endOfMonth()->toDateString(),
            'working_days_count'          => 22,
            'distance_km'                 => 5.5,
            'price_per_child'             => 350.00,
            'trip_price'                  => 350.00,
            'discount_amount'             => 0,
            'total_amount_after_discount' => 350.00,
            'driver_net_price'            => 280.00,
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

        $this->route = RouteModel::create([
            'driver_id'               => $this->driver->id,
            'vehicle_id'              => $vehicle->id,
            'route_name'              => 'مسار طرابلس الصباحي التجريبي',
            'route_type'              => 'Morning',
            'status'                  => 'Active',
            'start_time'              => '07:00',
            'estimated_duration'      => 40,
            'subscription_request_id' => $this->subscriptionRequest->id,
        ]);

        $this->activeSubscription = ActiveSubscription::create([
            'subscription_request_id' => $this->subscriptionRequest->id,
            'child_id'                => $this->child->id,
            'driver_id'               => $this->driver->id,
            'route_id'                => $this->route->id,
            'parent_id'               => $this->parentUser->id,
            'pickup_lat'              => 32.8800,
            'pickup_lng'              => 13.1800,
            'pickup_label'            => 'حي الأندلس، طرابلس',
            'dropoff_lat'             => 32.8900,
            'dropoff_lng'             => 13.1900,
            'dropoff_label'           => 'مدرسة طرابلس النموذجية',
            'pickup_time'             => '07:15',
            'dropoff_time'            => '07:45',
            'sort_order'              => 1,
            'status'                  => 'active',
        ]);
    }

    // =========================================================
    // 1. تسجيل غياب الطفل من قبل ولي الأمر
    // =========================================================

    public function test_parent_can_set_child_absence_for_future_dates(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $afterTomorrow = Carbon::tomorrow()->addDay()->toDateString();

        $response = $this->actingAs($this->parentUser)->postJson("/api/parent/children/{$this->child->id}/set-absence", [
            'dates'        => [$tomorrow, $afterTomorrow],
            'absence_type' => 'both',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.child_id', $this->child->id);
        $response->assertJsonPath('data.absence_type', 'both');

        $this->assertDatabaseHas('absence_logs', [
            'child_id'     => $this->child->id,
            'absence_date' => $tomorrow,
            'absence_type' => 'both',
        ]);

        $this->assertDatabaseHas('absence_logs', [
            'child_id'     => $this->child->id,
            'absence_date' => $afterTomorrow,
            'absence_type' => 'both',
        ]);
    }

    public function test_parent_can_set_child_absence_for_pickup_or_dropoff_only(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->parentUser)->postJson("/api/parent/children/{$this->child->id}/set-absence", [
            'dates'        => [$targetDate],
            'absence_type' => 'pickup',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.absence_type', 'pickup');

        $this->assertDatabaseHas('absence_logs', [
            'child_id'     => $this->child->id,
            'absence_date' => $targetDate,
            'absence_type' => 'pickup',
        ]);
    }

    public function test_parent_cannot_set_child_absence_for_past_dates(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $response = $this->actingAs($this->parentUser)->postJson("/api/parent/children/{$this->child->id}/set-absence", [
            'dates'        => [$yesterday],
            'absence_type' => 'both',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['dates.0']);
    }

    public function test_parent_cannot_set_absence_for_another_parents_child(): void
    {
        $otherParentUser = User::create([
            'full_name'     => 'ولي أمر ثانٍ',
            'email'         => 'other.abs.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $response = $this->actingAs($otherParentUser)->postJson("/api/parent/children/{$this->child->id}/set-absence", [
            'dates'        => [Carbon::tomorrow()->toDateString()],
            'absence_type' => 'both',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
    }

    // =========================================================
    // 2. إلغاء وجلب غيابات الطفل
    // =========================================================

    public function test_parent_can_get_registered_absences_for_child(): void
    {
        $futureDate = Carbon::tomorrow()->toDateString();

        AbsenceLog::create([
            'child_id'     => $this->child->id,
            'absence_date' => $futureDate,
            'absence_type' => 'both',
        ]);

        $response = $this->actingAs($this->parentUser)->getJson("/api/parent/children/{$this->child->id}/absences");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.child_id', $this->child->id);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.absences.0.date', $futureDate);
    }

    public function test_parent_can_cancel_scheduled_absence(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        AbsenceLog::create([
            'child_id'     => $this->child->id,
            'absence_date' => $targetDate,
            'absence_type' => 'both',
        ]);

        $response = $this->actingAs($this->parentUser)->postJson("/api/parent/children/{$this->child->id}/cancel-absence", [
            'dates'        => [$targetDate],
            'absence_type' => 'both',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('absence_logs', [
            'child_id'     => $this->child->id,
            'absence_date' => $targetDate,
        ]);
    }

    public function test_parent_can_fetch_available_absence_dates(): void
    {
        $response = $this->actingAs($this->parentUser)->getJson("/api/parent/children/{$this->child->id}/available-absence-dates");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.child_id', $this->child->id);
        $this->assertNotEmpty($response->json('data.available_dates'));
        $this->assertGreaterThan(0, $response->json('data.total_available'));
    }

    // =========================================================
    // 3. تسجيل غياب السائق
    // =========================================================

    public function test_driver_can_register_absence(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->driverUser)->postJson('/api/driver/register-absence', [
            'dates' => [$tomorrow],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.dates.0', $tomorrow);

        $this->assertDatabaseHas('driver_absences', [
            'driver_id'    => $this->driver->id,
            'absence_date' => $tomorrow,
        ]);
    }

    public function test_driver_cannot_start_trip_when_marked_absent_today(): void
    {
        $today = Carbon::today()->toDateString();

        DriverAbsence::create([
            'driver_id'    => $this->driver->id,
            'absence_date' => $today,
        ]);

        $response = $this->actingAs($this->driverUser)->postJson('/api/driver/trips/start', [
            'trip_type' => 'Morning',
            'latitude'  => 32.8872,
            'longitude' => 13.1913,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
    }

    // =========================================================
    // 4. تسجيل غياب الطفل أثناء سير الرحلة (Live Trip Absent Action)
    // =========================================================

    public function test_driver_can_mark_child_as_absent_during_live_trip(): void
    {
        $trip = Trip::create([
            'driver_id'            => $this->driver->id,
            'route_id'             => $this->route->id,
            'trip_type'            => 'Morning',
            'status'               => 'in_progress',
            'scheduled_start_time' => now(),
            'actual_start_time'    => now(),
            'trip_date'            => Carbon::today()->toDateString(),
        ]);

        $stop = TripStop::create([
            'trip_id'        => $trip->id,
            'stop_type'      => 'home',
            'child_id'       => $this->child->id,
            'school_id'      => $this->school->id,
            'lat'            => 32.8800,
            'lng'            => 13.1800,
            'label'          => 'حي الأندلس',
            'sequence_order' => 1,
            'status'         => 'pending',
        ]);

        $response = $this->actingAs($this->driverUser)->postJson("/api/driver/trips/{$trip->id}/children/{$this->activeSubscription->id}/status", [
            'action' => 'absent',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        $this->assertEquals(TripStop::STATUS_ABSENT_LATE, $stop->fresh()->status);

        $this->assertDatabaseHas('trip_events', [
            'trip_id'     => $trip->id,
            'child_id'    => $this->child->id,
            'action_type' => 'absent',
        ]);
    }
}
