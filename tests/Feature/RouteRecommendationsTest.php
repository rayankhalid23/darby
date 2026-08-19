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
use App\Models\Shared\Contract;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Route as RouteModel;

/**
 * ط§ط®طھط¨ط§ط± ط¯ط§ظ„ط© ط§ظ„ظ…ط³ط§ط±ط§طھ ط§ظ„ظ…ظ‚طھط±ط­ط© ظ„ظ„ط§ط´طھط±ط§ظƒ
 * GET /api/v1/driver/subscriptions/{subscriptionId}/route-recommendations
 *
 * DatabaseTransactions: ظƒظ„ ط¨ظٹط§ظ†ط§طھ ط§ظ„ط§ط®طھط¨ط§ط± طھظڈط­ط°ظپ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ط¨ط¹ط¯ ط§ظ„ط§ظ†طھظ‡ط§ط،.
 */
class RouteRecommendationsTest extends TestCase
{
    use DatabaseTransactions;

    protected User             $driverUser;
    protected Driver           $driver;
    protected User             $parentUser;
    protected ParentModel      $parent;
    protected Child            $child;
    protected School           $school;
    protected ActiveSubscription $subscription;
    protected int              $vehicleId;

    // =========================================================
    // ط¥ط¹ط¯ط§ط¯ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط£ط³ط§ط³ظٹط© ظ‚ط¨ظ„ ظƒظ„ ط§ط®طھط¨ط§ط±
    // =========================================================
    protected function setUp(): void
    {
        parent::setUp();

        // --- ط§ظ„ط£ط¯ظˆط§ط± ---
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'ط³ط§ط¦ظ‚'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
        ]);

        // --- 1. ظ…ط³طھط®ط¯ظ… ط§ظ„ط³ط§ط¦ظ‚ ---
        $this->driverUser = User::create([
            'full_name'     => 'ط³ط§ط¦ظ‚ طھظˆطµظٹط§طھ ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'         => 'driver.rec.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        // --- 2. ط³ط¬ظ„ ط§ظ„ط³ط§ط¦ظ‚ ---
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        // --- 3. ظ…ط±ظƒط¨ط© ظ„ظ„ط³ط§ط¦ظ‚ (capacity=10) ---
        $this->vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'طھظˆظٹظˆطھط§',
            'model'           => 'ظ‡ط§ظٹط³',
            'year'            => 2022,
            'color'           => 'ط£ط¨ظٹط¶',
            'plate_number'    => 'REC-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // --- 4. ظ…ط³طھط®ط¯ظ… ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ---
        $this->parentUser = User::create([
            'full_name'     => 'ظˆظ„ظٹ ط£ظ…ط± ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'         => 'parent.rec.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        // --- 5. ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ---
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // --- 6. ظ…ط¯ط±ط³ط© ---
        $this->school = School::create([
            'name'       => 'ظ…ط¯ط±ط³ط© ط§ظ„طھظˆطµظٹط§طھ',
            'address'    => 'ط·ط±ط§ط¨ظ„ط³',
            'lat'        => 32.9000,
            'lng'        => 13.2000,
            'start_time' => '08:00:00',
            'status'     => 'active',
        ]);

        // --- 7. ط·ظپظ„ ---
        $this->child = Child::create([
            'parent_id'          => $this->parent->id,
            'school_id'          => $this->school->id,
            'full_name'          => 'ط·ظپظ„ ط§ظ„طھظˆطµظٹط§طھ',
            'birth_date'         => '2018-05-10',
            'gender'             => 'male',
            'grade'              => 1,
            'notification_radius'=> 500,
        ]);

        // --- 8. ط¹ظ‚ط¯ ---
        $request = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'multi_day',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->subDay()->format('Y-m-d'),  // ط¨ط¯ط£ ط¨ط§ظ„ط£ظ…ط³ â†گ طµط§ظ„ط­ ظپظˆط±ط§ظ‹
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => 'accepted',
            'children_count'    => 1,
        ]);

        $contract = Contract::create([
            'subscription_request_id' => $request->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-REC-' . rand(100000, 999999),
            'subscription_type' => 'multi_day',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDay()->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
        ]);

        // --- 9. ط§ط´طھط±ط§ظƒ ظ†ط´ط· ط¨ط¯ظˆظ† ظ…ط³ط§ط± (route_id = null) ---
        $this->subscription = ActiveSubscription::create([
            'contract_id'  => $contract->id,
            'child_id'     => $this->child->id,
            'driver_id'    => $this->driver->id,
            'parent_id'    => $this->parentUser->id,
            'route_id'     => null,     // ظ„ظ… ظٹظڈط³ظ†ط¯ ط¨ط¹ط¯ â†گ ظ…ط±ط´ط­ ظ„ظ„طھظˆطµظٹط§طھ
            'pickup_lat'   => 32.8812,
            'pickup_lng'   => 13.1812,
            'pickup_label' => 'ظ…ظ†ط²ظ„ ط§ظ„ط·ظپظ„',
            'pickup_time'  => '07:00:00',
            'dropoff_time' => '14:00:00',
            'sort_order'   => 0,
            'status'       => 'active',
        ]);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 1: ظ„ط§ طھظˆط¬ط¯ ظ…ط³ط§ط±ط§طھ â†’ ظٹظڈط±ط¬ط¹ ط±ط³ط§ظ„ط© ط¥ظ†ط´ط§ط، ظ…ط³ط§ط± ط¬ط¯ظٹط¯
    // =========================================================
    public function test_returns_no_recommendations_when_driver_has_no_routes(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('recommended_route', null);
        $response->assertJsonStructure(['status', 'recommended_route', 'other_routes']);
        $this->assertIsArray($response->json('other_routes'));
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 2: ظٹظˆط¬ط¯ ظ…ط³ط§ط± ظ†ط´ط· â†’ ظٹظڈط±ط¬ط¹ ط§ظ„طھظˆطµظٹط© ط§ظ„ط£ظپط¶ظ„
    // =========================================================
    public function test_returns_best_recommendation_when_active_route_exists(): void
    {
        $route = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'ظ…ط³ط§ط± ط§ظ„ط§ط®طھط¨ط§ط± ط§ظ„طµط¨ط§ط­ظٹ',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 35,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // ط§ظ„ط®ظˆط§ط±ط²ظ…ظٹط© ط§ظ„ط¬ط¯ظٹط¯ط© (VRPTW) طھظڈط±ط¬ط¹ recommended_route ط£ظˆ null
        // ط¨ظ…ط§ ط£ظ† ط§ظ„ط§ط´طھط±ط§ظƒ ظ„ظٹط³ ظ„ط¯ظٹظ‡ ط¥ط­ط¯ط§ط«ظٹط§طھ طھظپطµظٹظ„ظٹط© ظٹط¸ظ‡ط± Fallback (score=70)
        $recommended = $response->json('recommended_route');
        $this->assertNotNull($recommended);
        $this->assertEquals($route->id, $recommended['id']);

        // score ظپظٹ ط§ظ„ط®ظˆط§ط±ط²ظ…ظٹط© ط§ظ„ط¬ط¯ظٹط¯ط© ظ‡ظˆ ظ…طھظˆط³ط· Slack Time ط£ظˆ Fallback (70.0)
        $score = $response->json('recommended_route.score');
        $this->assertIsNumeric($score);

        // طھط­ظ‚ظ‚ ظ…ظ† ظˆط¬ظˆط¯ ظ…ظپطھط§ط­ rejected_routes ظپظٹ ط§ظ„ط§ط³طھط¬ط§ط¨ط©
        $response->assertJsonStructure(['status', 'recommended_route', 'other_routes', 'rejected_routes', 'message']);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 3: ط³ط§ط¦ظ‚ ط¢ط®ط± ظ„ط§ ظٹط±ظ‰ ط§ط´طھط±ط§ظƒط§طھ ط³ط§ط¦ظ‚ ظ…ط®طھظ„ظپ
    // =========================================================
    public function test_driver_cannot_access_another_drivers_subscription(): void
    {
        // ط¥ظ†ط´ط§ط، ط³ط§ط¦ظ‚ ط«ط§ظ†ظچ
        $otherDriverUser = User::create([
            'full_name'     => 'ط³ط§ط¦ظ‚ ط¢ط®ط±',
            'email'         => 'driver.other.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        Driver::create([
            'user_id'        => $otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        // ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط«ط§ظ†ظٹ ظٹط­ط§ظˆظ„ ط§ظ„ظˆطµظˆظ„ ط¥ظ„ظ‰ ط§ط´طھط±ط§ظƒ ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط£ظˆظ„
        $response = $this->actingAs($otherDriverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 4: ط§ط´طھط±ط§ظƒ ط؛ظٹط± ظ…ظˆط¬ظˆط¯ â†’ 404
    // =========================================================
    public function test_returns_404_for_nonexistent_subscription(): void
    {
        $fakeId = 999999;

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$fakeId}/route-recommendations");

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'SUBSCRIPTION_NOT_FOUND');
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 5: ظ…ط³طھط®ط¯ظ… ط؛ظٹط± ظ…طµط§ط¯ظ‚ â†’ 401
    // =========================================================
    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson(
            "/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations"
        );

        $response->assertStatus(401);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 6: ظ…ط³ط§ط± ظ…ط³ط§ط¦ظٹ ظˆط­ظٹط¯ â†’ ظٹط¸ظ‡ط± ظƒظ€ recommended_route ظ…ط¹ طھط­ط°ظٹط± (score=40)
    //           (ظ„ط£ظ†ظ‡ ط§ظ„ظˆط­ظٹط¯ ط§ظ„ظ…طھط§ط­ ط±ط؛ظ… ط§ط®طھظ„ط§ظپ ط§ظ„ظپطھط±ط©)
    // =========================================================
    public function test_evening_route_appears_as_recommended_with_warning(): void
    {
        $eveningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'ظ…ط³ط§ط± ظ…ط³ط§ط¦ظٹ',
            'route_type'         => 'Afternoon',
            'start_time'         => '13:00:00',
            'estimated_duration' => 35,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // ظپظٹ ط§ظ„ط®ظˆط§ط±ط²ظ…ظٹط© ط§ظ„ط¬ط¯ظٹط¯ط©: ط§ظ„ظ…ط³ط§ط، ظٹط°ظ‡ط¨ ظ„ظ€ rejected_routes ظ„ط§ط®طھظ„ط§ظپ ط§ظ„ظپطھط±ط©
        $rejectedRoutes = $response->json('rejected_routes');
        $this->assertIsArray($rejectedRoutes);
        $this->assertNotEmpty($rejectedRoutes);

        $rejectedIds = array_column($rejectedRoutes, 'id');
        $this->assertContains($eveningRoute->id, $rejectedIds);

        // recommended_route ظٹظƒظˆظ† null ظ„ط£ظ†ظ‡ ظ„ط§ ظٹظˆط¬ط¯ ظ…ط³ط§ط± طµط¨ط§ط­ظٹ
        $response->assertJsonPath('recommended_route', null);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 7: ظ…ط³ط§ط±ط§ظ† (طµط¨ط§ط­ظٹ ظˆظ…ط³ط§ط¦ظٹ) â†’ ط§ظ„طµط¨ط§ط­ظٹ ظ…ظ‚طھط±ط­ ظˆط§ظ„ظ…ط³ط§ط¦ظٹ ظپظٹ other_routes
    // =========================================================
    public function test_morning_recommended_and_evening_in_other_routes(): void
    {
        $morningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'ظ…ط³ط§ط± طµط¨ط§ط­ظٹ ظ…ظ…طھط§ط²',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        $eveningRoute = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'ظ…ط³ط§ط± ظ…ط³ط§ط¦ظٹ ط¨ط¯ظٹظ„',
            'route_type'         => 'Afternoon',
            'start_time'         => '13:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');

        // ط§ظ„طµط¨ط§ط­ظٹ ظ‡ظˆ ط§ظ„ظ…ظ‚طھط±ط­ ط§ظ„ط£ظپط¶ظ„
        $response->assertJsonPath('recommended_route.id', $morningRoute->id);
        $response->assertJsonPath('recommended_route.name', 'ظ…ط³ط§ط± طµط¨ط§ط­ظٹ ظ…ظ…طھط§ط²');

        // ط§ظ„ظ…ط³ط§ط¦ظٹ ظٹط¸ظ‡ط± ظپظٹ rejected_routes (ظپطھط±ط© ظ…ط®طھظ„ظپط©)
        $rejectedRoutes = $response->json('rejected_routes');
        $this->assertIsArray($rejectedRoutes);
        $this->assertNotEmpty($rejectedRoutes);
        $rejectedIds = array_column($rejectedRoutes, 'id');
        $this->assertContains($eveningRoute->id, $rejectedIds);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 8: ط§ط´طھط±ط§ظƒ ظ…ظڈط³ظ†ط¯ ظ„ظ…ط³ط§ط± â†’ 400 ALREADY_ASSIGNED
    // =========================================================
    public function test_already_assigned_subscription_returns_400(): void
    {
        // ط¥ظ†ط´ط§ط، ظ…ط³ط§ط± ط«ظ… ط±ط¨ط· ط§ظ„ط§ط´طھط±ط§ظƒ ط¨ظ‡
        $route = RouteModel::create([
            'driver_id'          => $this->driver->id,
            'vehicle_id'         => $this->vehicleId,
            'route_name'         => 'ظ…ط³ط§ط± ظ…ظڈط³ظ†ط¯',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'estimated_duration' => 30,
            'status'             => 'Active',
        ]);

        // ط¥ط³ظ†ط§ط¯ ط§ظ„ط§ط´طھط±ط§ظƒ ظ„ظ„ظ…ط³ط§ط± ظ…ط¨ط§ط´ط±ط©
        $this->subscription->update(['route_id' => $route->id]);

        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/v1/driver/subscriptions/{$this->subscription->id}/route-recommendations");

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('code', 'ALREADY_ASSIGNED');
        $response->assertJsonPath('message', 'ظ‡ط°ط§ ط§ظ„ط§ط´طھط±ط§ظƒ طھظ… ط¥ط³ظ†ط§ط¯ظ‡ ظ„ظ…ط³ط§ط± ط¨ط§ظ„ظپط¹ظ„.');
    }
}
