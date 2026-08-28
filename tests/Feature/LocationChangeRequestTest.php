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
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Models\Shared\RouteStop;
use App\Models\Shared\LocationChangeRequest;

/**
 * اختبار دالة طلب ولي الأمر تغيير موقع استلام/تسليم طفله، وموافقة/رفض السائق عليه.
 */
class LocationChangeRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected Address $address;
    protected Route $route;
    protected ActiveSubscription $activeSub;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverUser = User::create([
            'full_name' => 'سائق الاختبار', 'email' => 'driver.loc.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 2, 'is_active' => 1,
        ]);
        $this->driver = Driver::create([
            'user_id' => $this->driverUser->id, 'national_id' => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999), 'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $this->parentUser = User::create([
            'full_name' => 'ولي أمر الاختبار', 'email' => 'parent.loc.' . uniqid() . '@darby.test',
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

        // الموقع الجديد المحفوظ الذي سيطلب ولي الأمر التغيير إليه
        $this->address = Address::create([
            'parent_id' => $this->parentUser->id, 'label' => 'منزل الجدة', 'lat' => 32.95, 'lng' => 13.25,
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

        $this->route = Route::create([
            'driver_id' => $this->driver->id, 'vehicle_id' => $vehicle->id, 'subscription_request_id' => $subReq->id,
            'route_name' => 'مسار الاختبار', 'route_type' => 'Morning', 'shift_slot' => 'morning_go',
            'start_time' => '07:00:00', 'status' => 'Active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'subscription_request_id' => $subReq->id, 'route_id' => $this->route->id,
            'status' => 'active', 'child_id' => $this->child->id, 'driver_id' => $this->driver->id,
            'parent_id' => $this->parentUser->id,
            'pickup_lat' => 32.88, 'pickup_lng' => 13.19, 'pickup_label' => 'المنزل', 'pickup_time' => '07:00:00',
            'dropoff_lat' => 32.90, 'dropoff_lng' => 13.20, 'dropoff_label' => 'المدرسة', 'dropoff_time' => '14:00:00',
        ]);

        RouteStop::create([
            'route_id' => $this->route->id, 'stop_type' => RouteStop::TYPE_HOME, 'child_id' => $this->child->id,
            'lat' => 32.88, 'lng' => 13.19, 'label' => 'المنزل', 'sequence_order' => 1,
        ]);
    }

    public function test_parent_can_fetch_change_options(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/parent/location-change-requests/options');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.active_subscriptions.0.active_subscription_id', $this->activeSub->id);
        $response->assertJsonPath('data.addresses.0.id', $this->address->id);
    }

    public function test_parent_can_request_pickup_location_change_from_saved_address(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/location-change-requests', [
                'active_subscription_id' => $this->activeSub->id,
                'point_type'             => 'pickup',
                'address_id'             => $this->address->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('location_change_requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'status'                 => LocationChangeRequest::STATUS_PENDING,
            'new_address_id'         => $this->address->id,
        ]);
    }

    public function test_parent_cannot_submit_duplicate_pending_request_for_same_point(): void
    {
        $this->actingAs($this->parentUser)->postJson('/api/parent/location-change-requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'address_id'             => $this->address->id,
        ])->assertStatus(201);

        $response = $this->actingAs($this->parentUser)->postJson('/api/parent/location-change-requests', [
            'active_subscription_id' => $this->activeSub->id,
            'point_type'             => 'pickup',
            'lat'                    => 32.91,
            'lng'                    => 13.21,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_driver_can_approve_pickup_change_and_route_stop_is_updated(): void
    {
        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'point_type' => 'pickup', 'new_address_id' => $this->address->id,
            'new_lat' => $this->address->lat, 'new_lng' => $this->address->lng, 'new_label' => $this->address->label,
            'status' => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('location_change_requests', [
            'id' => $changeRequest->id, 'status' => LocationChangeRequest::STATUS_APPROVED,
        ]);

        // نقطة الاستلام في الاشتراك النشط تحدثت للموقع الجديد
        $this->assertDatabaseHas('active_subscriptions', [
            'id' => $this->activeSub->id,
            'pickup_label' => 'منزل الجدة',
        ]);

        // محطة المسار الرئيسي (route_stops) تزامنت مع الموقع الجديد أيضاً
        $this->assertDatabaseHas('route_stops', [
            'route_id' => $this->route->id, 'child_id' => $this->child->id, 'stop_type' => RouteStop::TYPE_HOME,
            'label' => 'منزل الجدة',
        ]);
    }

    public function test_driver_can_reject_change_with_reason_and_data_is_not_modified(): void
    {
        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'point_type' => 'pickup', 'new_address_id' => $this->address->id,
            'new_lat' => $this->address->lat, 'new_lng' => $this->address->lng, 'new_label' => $this->address->label,
            'status' => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status'           => 'rejected',
                'rejection_reason' => 'المسار الجديد بعيد جداً عن باقي الأطفال.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('location_change_requests', [
            'id' => $changeRequest->id, 'status' => LocationChangeRequest::STATUS_REJECTED,
            'rejection_reason' => 'المسار الجديد بعيد جداً عن باقي الأطفال.',
        ]);

        // لم يتغيّر شيء في الاشتراك النشط
        $this->assertDatabaseHas('active_subscriptions', [
            'id' => $this->activeSub->id, 'pickup_label' => 'المنزل',
        ]);
    }

    public function test_driver_cannot_respond_to_another_drivers_request(): void
    {
        $otherDriverUser = User::create([
            'full_name' => 'سائق آخر', 'email' => 'driver.other.' . uniqid() . '@darby.test',
            'phone_number' => '095' . rand(1000000, 9999999), 'password_hash' => bcrypt('password123'),
            'role_id' => 2, 'is_active' => 1,
        ]);
        Driver::create([
            'user_id' => $otherDriverUser->id, 'national_id' => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999), 'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        $changeRequest = LocationChangeRequest::create([
            'active_subscription_id' => $this->activeSub->id, 'child_id' => $this->child->id,
            'parent_id' => $this->parentUser->id, 'driver_id' => $this->driver->id,
            'point_type' => 'pickup', 'new_lat' => 32.95, 'new_lng' => 13.25, 'new_label' => 'منزل الجدة',
            'status' => LocationChangeRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($otherDriverUser)
            ->postJson("/api/v1/driver/location-change-requests/{$changeRequest->id}/respond", [
                'status' => 'approved',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('location_change_requests', [
            'id' => $changeRequest->id, 'status' => LocationChangeRequest::STATUS_PENDING,
        ]);
    }
}
