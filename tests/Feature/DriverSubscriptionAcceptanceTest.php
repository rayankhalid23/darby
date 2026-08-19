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
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\SubscriptionRequestService;
use App\Services\Shared\ContractService;
use Mockery;

/**
 * ط§ط®طھط¨ط§ط± ط¯ط§ظ„ط© ظ‚ط¨ظˆظ„/ط±ظپط¶ ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ظ…ظ† ظ‚ظگط¨ظژظ„ ط§ظ„ط³ط§ط¦ظ‚
 *
 * ظٹط³طھط®ط¯ظ… DatabaseTransactions: ظƒظ„ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ظڈظ†ط´ط£ط© ط®ظ„ط§ظ„ ط§ظ„ط§ط®طھط¨ط§ط±
 * طھظڈط­ط°ظپ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ط¨ط¹ط¯ ط§ظ†طھظ‡ط§ط¦ظ‡ â†گ ظ„ط§ ط¶ط±ط± ط¹ظ„ظ‰ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط­ظ‚ظٹظ‚ظٹط©.
 */
class DriverSubscriptionAcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================
    // ط¨ظٹط§ظ†ط§طھ ظ…ط´طھط±ظƒط© ط¨ظٹظ† ط¬ظ…ظٹط¹ ط§ظ„ط§ط®طھط¨ط§ط±ط§طھ
    // =========================================================
    protected User              $driverUser;
    protected Driver            $driver;
    protected User              $parentUser;
    protected ParentModel       $parent;
    protected Child             $child;
    protected School            $school;
    protected SubscriptionRequest $subscriptionRequest;

    // =========================================================
    // setUp: ط¥ط¹ط¯ط§ط¯ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط£ط³ط§ط³ظٹط© ظ‚ط¨ظ„ ظƒظ„ ط§ط®طھط¨ط§ط±
    // =========================================================
    protected function setUp(): void
    {
        parent::setUp();

        // --- ط¥ط¯ط±ط§ط¬ ط§ظ„ط£ط¯ظˆط§ط± ط¥ط°ط§ ظ„ظ… طھظƒظ† ظ…ظˆط¬ظˆط¯ط© ---
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver',  'display_name' => 'ط³ط§ط¦ظ‚'],
            ['id' => 3, 'name' => 'Parent',  'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
        ]);

        // ---- 1. ظ…ط³طھط®ط¯ظ… ط§ظ„ط³ط§ط¦ظ‚ ----
        $this->driverUser = User::create([
            'full_name'    => 'ط³ط§ط¦ظ‚ ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'        => 'driver.test.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        // ---- 2. ط³ط¬ظ„ ط§ظ„ط³ط§ط¦ظ‚ ظپظٹ ط¬ط¯ظˆظ„ drivers ----
        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
            'current_lat'    => 32.8872,
            'current_lng'    => 13.1932,
        ]);

        // ---- 3. ط¥ط¯ط±ط§ط¬ ظ…ط±ظƒط¨ط© ظ†ط´ط·ط© ظ„ظ„ط³ط§ط¦ظ‚ ----
        DB::table('vehicles')->insert([
            'driver_id'       => $this->driver->id,
            'brand'           => 'طھظˆظٹظˆطھط§',
            'model'           => 'ظ‡ط§ظٹط³',
            'year'            => 2022,
            'color'           => 'ط£ط¨ظٹط¶',
            'plate_number'    => 'TEST-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ---- 4. ظ…ط³طھط®ط¯ظ… ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ----
        $this->parentUser = User::create([
            'full_name'    => 'ظˆظ„ظٹ ط£ظ…ط± ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'        => 'parent.test.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        // ---- 5. ط³ط¬ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ظپظٹ ط¬ط¯ظˆظ„ parents ----
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // ---- 6.1 ظ…ط¯ط±ط³ط© ط§ظ„ط§ط®طھط¨ط§ط± ----
        $this->school = School::create([
            'name'    => 'ظ…ط¯ط±ط³ط© ط§ظ„ط§ط®طھط¨ط§ط±',
            'address' => 'ط´ط§ط±ط¹ ط§ظ„ط§ط®طھط¨ط§ط±',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        // ---- 6.2 ط¹ظ†ظˆط§ظ† ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ----
        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'ظ…ظ†ط²ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---- 6. ط§ظ„ط·ظپظ„ ----
        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'full_name' => 'ط·ظپظ„ ط§ظ„ط§ط®طھط¨ط§ط±',
            'birth_date'=> '2018-05-10',
            'gender'    => 'male',
            'grade'     => 1,
            'notification_radius' => 500,
        ]);

        // ---- 7. ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ ط§ظ„ظ…ط¹ظ„ظ‚ ----
        $this->subscriptionRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'multi_day',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 200.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        // ---- 8. ط±ط¨ط· ط§ظ„ط·ظپظ„ ط¨ط§ظ„ط·ظ„ط¨ ظپظٹ ط¬ط¯ظˆظ„ request_children ----
        DB::table('request_children')->insert([
            'request_id'         => $this->subscriptionRequest->id,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.88,
            'home_lng'           => 13.19,
            'home_label'         => 'ط§ظ„ظ…ظ†ط²ظ„',
            'school_lat'         => 32.90,
            'school_lng'         => 13.20,
            'school_label'       => 'ط§ظ„ظ…ط¯ط±ط³ط©',
            'price_per_child'    => 200.00,
        ]);
    }

    protected function tearDown(): void
    {
        // طھط£ظƒط¯ ظ…ظ† ط¥ط؛ظ„ط§ظ‚ ظƒظ„ mock ظ„طھظپط§ط¯ظٹ طھط³ط±ط¨ ط§ظ„ط­ط§ظ„ط© ط¨ظٹظ† ط§ظ„ط§ط®طھط¨ط§ط±ط§طھ
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 1: ط§ظ„ط³ط§ط¦ظ‚ ظٹظ‚ط¨ظ„ ط§ظ„ط·ظ„ط¨ ط¨ظ†ط¬ط§ط­
    // =========================================================
    public function test_driver_can_accept_subscription_request(): void
    {
        // --- Mock ظ„ظ€ ContractService ظ„طھط¬ظ†ط¨ PDF/OSRM ط§ظ„ط®ط§ط±ط¬ظٹظٹظ† ---
        $fakeContract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequest->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-TEST-' . rand(100000, 999999),
            'subscription_type' => 'multi_day',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->addDays(1)->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
            'signed_at'               => now(),
        ]);

        $mockContractService = Mockery::mock(ContractService::class);
        $mockContractService->shouldReceive('generateContract')
            ->once()
            ->andReturn($fakeContract->load(['subscriptionRequest', 'parent', 'driver', 'activeSubscriptions']));

        $this->app->instance(ContractService::class, $mockContractService);

        // --- ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط§ظ„ظ‚ط¨ظˆظ„ ---
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        // --- ط§ظ„طھط­ظ‚ظ‚ط§طھ ---
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // طھط­ظ‚ظ‚ ط£ظ† ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ طھط؛ظٹظ‘ط±طھ ط¥ظ„ظ‰ accepted
        $this->assertDatabaseHas('requests', [
            'id'     => $this->subscriptionRequest->id,
            'status' => 'accepted',
        ]);

        // طھط­ظ‚ظ‚ ظ…ظ† ط¥ظ†ط´ط§ط، ط³ط¬ظ„ط§طھ active_subscriptions
        $this->assertDatabaseHas('active_subscriptions', [
            'driver_id' => $this->driver->id,
            'child_id'  => $this->child->id,
            'status'    => 'active',
        ]);

        // ط¬ظ„ط¨ ظ…ط¹ط±ظپ ط§ظ„ط§ط´طھط±ط§ظƒ ط§ظ„ظ†ط´ط· ظ…ظ† ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ظ„ظ„طھط­ظ‚ظ‚ ظ…ظ† طھط·ط§ط¨ظ‚ظ‡ ظ…ط¹ ط§ظ„ط§ط³طھط¬ط§ط¨ط©
        $activeSub = ActiveSubscription::where('driver_id', $this->driver->id)
            ->where('child_id', $this->child->id)
            ->first();
        
        $this->assertNotNull($activeSub);

        // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظˆط¬ظˆط¯ ط§ظ„ظ…ط¹ط±ظ‘ظپ (id) ظˆطھط·ط§ط¨ظ‚ظ‡ ط¯ط§ط®ظ„ طھظپط§طµظٹظ„ ط§ط´طھط±ط§ظƒ ط§ظ„ط·ظپظ„ ظپظٹ ظ…طµظپظˆظپط© ط§ظ„ط¥ط®ط±ط§ط¬
        $response->assertJsonPath('data.children.0.subscription.id', $activeSub->id);

        // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظˆط¬ظˆط¯ طµظˆط±ط© ط§ظ„ط·ظپظ„ (photo_url) ظپظٹ ظ…طµظپظˆظپط© ط§ظ„ط¥ط®ط±ط§ط¬
        $response->assertJsonStructure([
            'data' => [
                'children' => [
                    '*' => [
                        'photo_url',
                    ]
                ]
            ]
        ]);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 1.5: ظ‚ط¨ظˆظ„ ط§ظ„ط·ظ„ط¨ ظٹظ†ط´ط¦ ظ…ط³ط§ط±ط§ظ‹ ظˆظٹط®طµظ… ط§ظ„ظ…ظ‚ط§ط¹ط¯ ط§ظ„ظ…طھط§ط­ط© (ط¨ط²ظٹط§ط¯ط© ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط©)
    // =========================================================
    public function test_accepting_subscription_creates_route_and_deducts_available_seats(): void
    {
        $service = app(SubscriptionRequestService::class);

        $beforeActiveCount = ActiveSubscription::where('driver_id', $this->driver->id)->where('status', 'active')->count();

        // ط¥ظƒظ…ط§ظ„ ظ‚ط¨ظˆظ„ ط§ظ„ط·ظ„ط¨
        $updatedRequest = $service->updateStatus($this->subscriptionRequest, 'accepted');

        // 1. طھط­ظ‚ظ‚ ظ…ظ† طھط؛ظٹط± ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ط¥ظ„ظ‰ accepted
        $this->assertEquals('accepted', $updatedRequest->status);

        // 2. طھط­ظ‚ظ‚ ظ…ظ† ط²ظٹط§ط¯ط© ط§ظ„ظ…ظ‚ط§ط¹ط¯ ط§ظ„ظ…ط­ط¬ظˆط²ط© (ط¹ط¯ط¯ ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط©) ط¨ظ†ط§ط،ظ‹ ط¹ظ„ظ‰ ط¹ط¯ط¯ ط§ظ„ط£ط·ظپط§ظ„
        $afterActiveCount = ActiveSubscription::where('driver_id', $this->driver->id)->where('status', 'active')->count();
        $this->assertEquals($beforeActiveCount + $this->subscriptionRequest->children_count, $afterActiveCount);

        // 3. طھط­ظ‚ظ‚ ظ…ظ† ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط³ط§ط± ظپظٹ ط¬ط¯ظˆظ„ routes
        $this->assertDatabaseHas('routes', [
            'driver_id'   => $this->driver->id,
            'contract_id' => $updatedRequest->contract->id,
            'status'      => 'Active',
        ]);

        // 4. طھط­ظ‚ظ‚ ظ…ظ† ط±ط¨ط· active_subscriptions ط¨ظ€ route_id ط§ظ„ظ…ظˆظ„ط¯
        $route = DB::table('routes')->where('contract_id', $updatedRequest->contract->id)->first();
        $this->assertNotNull($route);
        $this->assertDatabaseHas('active_subscriptions', [
            'driver_id'   => $this->driver->id,
            'contract_id' => $updatedRequest->contract->id,
            'route_id'    => $route->id,
            'status'      => 'active',
        ]);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 1.6: ط§ظ„ط³ط§ط¦ظ‚ ظ„ط§ ظٹظ…ظƒظ†ظ‡ ظ‚ط¨ظˆظ„ ط·ظ„ط¨ ط¥ط°ط§ طھظˆط§ط²طھ ط£ظˆ طھط¬ط§ظˆط²طھ ط³ط¹ط© ط§ظ„ظ…ط±ظƒط¨ط© ط§ظ„ظ…طھط§ط­ط©
    // =========================================================
    public function test_accepting_subscription_fails_when_vehicle_capacity_is_exceeded(): void
    {
        // طھط­ط¯ظٹط¯ ط³ط¹ط© ط§ظ„ظ…ط±ظƒط¨ط© ط¨ظ€ 1 ظ…ظ‚ط¹ط¯
        DB::table('vehicles')->where('driver_id', $this->driver->id)->update(['capacity_manual' => 1]);

        $existingContract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequest->id,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-TEST-' . rand(100000, 999999),
            'subscription_type' => 'multi_day',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->addDays(1)->format('Y-m-d'),
            'end_date'                => now()->addMonths(1)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 200.00,
            'clauses'                 => [],
            'status'                  => 'active',
            'signed_at'               => now(),
        ]);

        // ط­ط¬ط² ط§ظ„ظ…ظ‚ط¹ط¯ ط§ظ„ظ…طھط§ط­ ط¨ط§ط´طھط±ط§ظƒ ظ†ط´ط· ط³ط§ط¨ظ‚
        ActiveSubscription::create([
            'contract_id'   => $existingContract->id,
            'status'        => 'active',
            'child_id'      => $this->child->id,
            'driver_id'     => $this->driver->id,
            'parent_id'     => $this->parentUser->id,
            'pickup_lat'    => 32.88,
            'pickup_lng'    => 13.19,
            'pickup_label'  => 'ظ…ظ†ط²ظ„',
            'pickup_time'   => '07:00:00',
            'dropoff_lat'   => 32.90,
            'dropoff_lng'   => 13.20,
            'dropoff_label' => 'ظ…ط¯ط±ط³ط©',
            'dropoff_time'  => '14:00:00',
        ]);

        // ظ…ط­ط§ظˆظ„ط© ظ‚ط¨ظˆظ„ ط·ظ„ط¨ ط¬ط¯ظٹط¯ ظٹطھط·ظ„ط¨ ظ…ظ‚ط¹ط¯ط§ظ‹ ظٹظپط´ظ„ ط¨ط³ط¨ط¨ طھط¬ط§ظˆط² ط§ظ„ط³ط¹ط©
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('ط£ظ‚ظ„ ظ…ظ† ط¹ط¯ط¯ ط§ظ„ط£ط·ظپط§ظ„', $response->json('message'));
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 2: ط§ظ„ط³ط§ط¦ظ‚ ظٹط±ظپط¶ ط§ظ„ط·ظ„ط¨ ط¨ظ†ط¬ط§ط­ ظ…ط¹ ط³ط¨ط¨ ط§ظ„ط±ظپط¶
    // =========================================================
    public function test_driver_can_reject_subscription_request_with_reason(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status'           => 'rejected',
                'rejection_reason' => 'ط§ظ„ظ…ظ†ط·ظ‚ط© ط¨ط¹ظٹط¯ط© ط¹ظ† ظ…ط³ط§ط±ظٹ ط§ظ„ظٹظˆظ…ظٹ.',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // طھط­ظ‚ظ‚ ظ…ظ† طھط­ط¯ظٹط« ط§ظ„ط­ط§ظ„ط© ظˆط³ط¨ط¨ ط§ظ„ط±ظپط¶ ظپظٹ ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ
        $this->assertDatabaseHas('requests', [
            'id'               => $this->subscriptionRequest->id,
            'status'           => 'rejected',
            'rejection_reason' => 'ط§ظ„ظ…ظ†ط·ظ‚ط© ط¨ط¹ظٹط¯ط© ط¹ظ† ظ…ط³ط§ط±ظٹ ط§ظ„ظٹظˆظ…ظٹ.',
        ]);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 3: ط±ظپط¶ ط¨ط¯ظˆظ† ط³ط¨ط¨ ظٹظپط´ظ„ ط¨ظ€ 422
    // =========================================================
    public function test_reject_without_reason_fails_validation(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'rejected',
                // rejection_reason ظ…ظپظ‚ظˆط¯ ط¹ظ…ط¯ط§ظ‹
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 4: ط·ظ„ط¨ ط¨ظ€ status ط؛ظٹط± طµط§ظ„ط­ ظٹظپط´ظ„ ط¨ظ€ 422
    // =========================================================
    public function test_invalid_status_fails_validation(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'pending', // ط؛ظٹط± ظ…ط³ظ…ظˆط­ ط¨ظ‡
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 5: ظ…ط­ط§ظˆظ„ط© ظ‚ط¨ظˆظ„ ط·ظ„ط¨ ظ„ظٹط³ ظ…ط®طµطµط§ظ‹ ظ„ظ„ط³ط§ط¦ظ‚ طھظڈط±ظپط¶ ط¨ظ€ 403
    // =========================================================
    public function test_driver_cannot_accept_another_drivers_request(): void
    {
        // ط¥ظ†ط´ط§ط، ط³ط§ط¦ظ‚ ط¢ط®ط±
        $anotherDriverUser = User::create([
            'full_name'    => 'ط³ط§ط¦ظ‚ ط¢ط®ط±',
            'email'        => 'another.driver.' . uniqid() . '@darby.test',
            'phone_number' => '095' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        Driver::create([
            'user_id'        => $anotherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        // ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط¢ط®ط± ظٹط­ط§ظˆظ„ ظ‚ط¨ظˆظ„ ط·ظ„ط¨ ظ„ظٹط³ ظ„ظ‡
        $response = $this->actingAs($anotherDriverUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 6: ظ…ط­ط§ظˆظ„ط© ظ‚ط¨ظˆظ„ ط·ظ„ط¨ ط؛ظٹط± ظ…ظˆط¬ظˆط¯ طھظڈط±ط¬ط¹ 404
    // =========================================================
    public function test_accepting_nonexistent_request_returns_404(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->putJson("/api/driver/999999/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 7: ظ…ط³طھط®ط¯ظ… ظ„ظٹط³ ط³ط§ط¦ظ‚ط§ظ‹ ظٹظڈط±ط¬ط¹ 403
    // =========================================================
    public function test_non_driver_user_gets_403(): void
    {
        // ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ظٹط­ط§ظˆظ„ ط§ط³طھط®ط¯ط§ظ… ظ…ط³ط§ط± ط§ظ„ط³ط§ط¦ظ‚
        $response = $this->actingAs($this->parentUser)
            ->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // ط§ط®طھط¨ط§ط± 8: ظ…ط³طھط®ط¯ظ… ط؛ظٹط± ظ…ط³ط¬ظ‘ظ„ ط¯ط®ظˆظ„ظ‡ ظٹظڈط±ط¬ط¹ 401
    // =========================================================
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->putJson("/api/driver/{$this->subscriptionRequest->id}/status", [
            'status' => 'accepted',
        ]);

        $response->assertStatus(401);
    }
}
