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
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\TripManualConfirmation;

/**
 * اختبار دالة تأكيد ولي الأمر اليدوي لاستلام/تسليم طفله في رحلة سابقة
 * لم يوثّقها التطبيق (السائق نسي تسجيل الحالة أو تعطل تطبيقه).
 */
class TripManualConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected ActiveSubscription $activeSub;
    protected Trip $trip;
    protected TripStop $tripStop;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name' => 'سائق الاختبار', 'email' => 'driver.conf.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 2, 'is_active' => 1,
        ]);
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id, 'national_id' => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999), 'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name' => 'ولي أمر الاختبار', 'email' => 'parent.conf.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 3, 'is_active' => 1,
        ]);
        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);

        $school = School::create([
            'name' => 'مدرسة الاختبار', 'address' => 'شارع الاختبار', 'lat' => 32.90, 'lng' => 13.20, 'status' => 'active',
        ]);

        $this->child = Child::create([
            'parent_id' => $this->parent->id, 'school_id' => $school->id, 'full_name' => 'طفل الاختبار',
            'birth_date' => '2018-05-10', 'gender' => 'male', 'grade' => 1, 'notification_radius' => 500,
        ]);

        $subReq = SubscriptionRequest::create([
            'parent_id' => $this->parent->id, 'driver_id' => $this->driver->id,
            'total_price' => 100, 'status' => SubscriptionRequest::STATUS_ACCEPTED,
            'children_count' => 1,
        ]);

        $vehicle = \App\Models\Driver\Vehicle::create([
            'driver_id' => $this->driver->id, 'plate_number' => '5-' . rand(1000, 9999),
            'brand' => 'Toyota', 'model' => 'Hiace', 'year' => 2022, 'color' => 'White',
            'type' => 'Van', 'capacity_manual' => 14, 'is_verified' => 1, 'status' => 'Active',
        ]);

        $route = Route::create([
            'driver_id' => $this->driver->id, 'vehicle_id' => $vehicle->id, 'subscription_request_id' => $subReq->id,
            'route_name' => 'مسار الاختبار', 'route_type' => 'Morning', 'shift_slot' => 'morning_go',
            'start_time' => '07:00:00', 'status' => 'Active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'subscription_request_id' => $subReq->id, 'route_id' => $route->id,
            'status' => 'active', 'child_id' => $this->child->id, 'driver_id' => $this->driver->id,
            'parent_id' => $this->parentUser->id,
            'pickup_lat' => 32.88, 'pickup_lng' => 13.19, 'pickup_label' => 'المنزل', 'pickup_time' => '07:00:00',
            'dropoff_lat' => 32.90, 'dropoff_lng' => 13.20, 'dropoff_label' => 'المدرسة', 'dropoff_time' => '14:00:00',
        ]);

        // رحلة أمس لم تُغلق بشكل صحيح (السائق نسي توثيق حالة الطفل)
        $this->trip = Trip::create([
            'driver_id' => $this->driver->id, 'route_id' => $route->id, 'trip_type' => 'Morning',
            'shift_slot' => 'morning_go', 'status' => 'in_progress', 'trip_date' => now()->subDay()->toDateString(),
            'scheduled_at' => now()->subDay(),
        ]);

        $this->tripStop = TripStop::create([
            'trip_id' => $this->trip->id, 'stop_type' => TripStop::TYPE_HOME, 'child_id' => $this->child->id,
            'lat' => 32.88, 'lng' => 13.19, 'label' => 'المنزل', 'sequence_order' => 1,
            'status' => TripStop::STATUS_PENDING,
        ]);
    }

    public function test_driver_can_list_subscribed_parents_and_children(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/trip-manual-confirmations/parents-and-children');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.child.id', $this->child->id);
    }

    public function test_driver_can_list_incomplete_trips(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/trip-manual-confirmations/incomplete-trips');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.0.trip_id', $this->trip->id);
    }

    public function test_driver_can_request_manual_confirmation_for_selected_children(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/trip-manual-confirmations', [
                'trip_id'   => $this->trip->id,
                'child_ids' => [$this->child->id],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('trip_manual_confirmations', [
            'trip_id'       => $this->trip->id,
            'trip_stop_id'  => $this->tripStop->id,
            'child_id'      => $this->child->id,
            'parent_id'     => $this->parentUser->id,
            'status'        => TripManualConfirmation::STATUS_PENDING,
            'target_status' => TripStop::STATUS_DROPPED_OFF_SCHOOL,
        ]);
    }

    public function test_parent_confirming_updates_trip_stop_status(): void
    {
        $confirmation = TripManualConfirmation::create([
            'trip_id' => $this->trip->id, 'trip_stop_id' => $this->tripStop->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'question_type' => TripManualConfirmation::QUESTION_PICKUP,
            'target_status' => TripStop::STATUS_DROPPED_OFF_SCHOOL,
            'status' => TripManualConfirmation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/trip-manual-confirmations/{$confirmation->id}/respond", [
                'confirmed' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('trip_manual_confirmations', [
            'id' => $confirmation->id, 'status' => TripManualConfirmation::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('trip_stops', [
            'id' => $this->tripStop->id, 'status' => TripStop::STATUS_DROPPED_OFF_SCHOOL,
        ]);
    }

    public function test_parent_denying_does_not_change_trip_stop_status(): void
    {
        $confirmation = TripManualConfirmation::create([
            'trip_id' => $this->trip->id, 'trip_stop_id' => $this->tripStop->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'question_type' => TripManualConfirmation::QUESTION_PICKUP,
            'target_status' => TripStop::STATUS_DROPPED_OFF_SCHOOL,
            'status' => TripManualConfirmation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/trip-manual-confirmations/{$confirmation->id}/respond", [
                'confirmed' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_manual_confirmations', [
            'id' => $confirmation->id, 'status' => TripManualConfirmation::STATUS_DENIED,
        ]);
        // حالة الطفل في الرحلة لم تتغيّر لأن ولي الأمر لم يؤكد
        $this->assertDatabaseHas('trip_stops', [
            'id' => $this->tripStop->id, 'status' => TripStop::STATUS_PENDING,
        ]);
    }

    public function test_parent_cannot_respond_to_another_childs_confirmation(): void
    {
        $otherParentUser = User::create([
            'full_name' => 'ولي أمر آخر', 'email' => 'parent.other.' . uniqid() . '@darby.test',
            'phone_number' => '096' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 3, 'is_active' => 1,
        ]);

        $confirmation = TripManualConfirmation::create([
            'trip_id' => $this->trip->id, 'trip_stop_id' => $this->tripStop->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'question_type' => TripManualConfirmation::QUESTION_PICKUP,
            'target_status' => TripStop::STATUS_DROPPED_OFF_SCHOOL,
            'status' => TripManualConfirmation::STATUS_PENDING,
        ]);

        $response = $this->actingAs($otherParentUser)
            ->postJson("/api/parent/trip-manual-confirmations/{$confirmation->id}/respond", [
                'confirmed' => true,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('trip_stops', [
            'id' => $this->tripStop->id, 'status' => TripStop::STATUS_PENDING,
        ]);
    }
}
