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
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\Trip;

/**
 * ط§ط®طھط¨ط§ط± ط¥طµظ„ط§ط­ط§طھ ط§ظ„ط£ط®ط·ط§ط، ط§ظ„ظ…ظƒطھط´ظپط© ظپظٹ ظˆط§ط¬ظ‡ط§طھ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط§ظ„ظ…ط§ظ„ظٹط©:
 * 1) POST /active-subscriptions/{id}/cancel ظƒط§ظ† ظ…ط³ط¬ظژظ‘ظ„ط§ظ‹ ط¨ظ„ط§ ط¯ط§ظ„ط© ظپط¹ظ„ظٹط©.
 * 2) ط¨ظ„ظˆظƒ billing ظپظٹ active-subscriptions ظƒط§ظ† ظٹط­طھظˆظٹ ظ‚ظٹظ…ط§ظ‹ ظˆظ‡ظ…ظٹط© ط«ط§ط¨طھط©.
 * 3) ط§ط³طھط¬ط§ط¨ط© hold-trip ظƒط§ظ†طھ طھط±ط¬ط¹ ط§ظ„ظ…ط¨ظ„ط؛ ط¨ط§ظ„ظ‚ط±ظˆط´ ط®ظ„ط§ظپط§ظ‹ ظ„ط¨ط§ظ‚ظٹ ظˆط§ط¬ظ‡ط§طھ ط§ظ„ظ…ط­ظپط¸ط©.
 * 4) ContractResource ظƒط§ظ† ظٹط´ظٹط± ظ„ط¹ظ…ظˆط¯ظٹظ† ظ…ط­ط°ظˆظپظٹظ† (price, selected_clauses).
 */
class ParentFinancialEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;
    protected Child $child;
    protected School $school;
    protected Contract $contract;
    protected ActiveSubscription $activeSub;
    protected int $subscriptionRequestId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'ط³ط§ط¦ظ‚'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'ط³ط§ط¦ظ‚ ظ…ط§ظ„ظٹط© ظˆظ„ظٹ ط§ظ„ط£ظ…ط±',
            'email'         => 'driver.pfin.' . uniqid() . '@darby.test',
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

        $vehicleId = DB::table('vehicles')->insertGetId([
            'driver_id'       => $this->driver->id,
            'brand'           => 'طھظˆظٹظˆطھط§',
            'model'           => 'ظ‡ط§ظٹط³',
            'year'            => 2022,
            'color'           => 'ط£ط¨ظٹط¶',
            'plate_number'    => 'PFIN-' . rand(1000, 9999),
            'capacity_manual' => 10,
            'capacity_ai'     => 10,
            'status'          => 'Active',
            'deleted_at'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->parentUser = User::create([
            'full_name'     => 'ظˆظ„ظٹ ط£ظ…ط± ظ…ط§ظ„ظٹط© ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'         => 'parent.pfin.' . uniqid() . '@darby.test',
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
            'name'    => 'ظ…ط¯ط±ط³ط© ظ…ط§ظ„ظٹط© ط§ظ„ط§ط®طھط¨ط§ط±',
            'address' => 'ط´ط§ط±ط¹ ط§ظ„ط§ط®طھط¨ط§ط±',
            'lat'     => 32.9000,
            'lng'     => 13.2000,
            'status'  => 'active',
        ]);

        $this->child = Child::create([
            'parent_id'            => $this->parent->id,
            'school_id'            => $this->school->id,
            'full_name'            => 'ط·ظپظ„ ظ…ط§ظ„ظٹط© ط§ظ„ط§ط®طھط¨ط§ط±',
            'birth_date'           => '2018-05-10',
            'gender'               => 'male',
            'grade'                => 1,
            'notification_radius' => 500,
        ]);

        // ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ظ…ظ‚ط¨ظˆظ„ + ط³ط¹ط± ط®ط§طµ ط¨ط§ظ„ط·ظپظ„ (request_children.price_per_child)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->subscriptionRequestId = DB::table('requests')->insertGetId([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'timing'            => 'MORNING',
            'direction'         => 'both',
            'status'            => 'accepted',
            'subscription_type' => 'multi_day',
            'children_count'    => 1,
            'created_at'        => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'ظ…ظ†ط²ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $this->subscriptionRequestId,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'price_per_child'    => 175.50,
        ]);

        $this->contract = Contract::create([
            'subscription_request_id' => $this->subscriptionRequestId,
            'parent_id'               => $this->parentUser->id,
            'driver_id'               => $this->driverUser->id,
            'contract_number'         => 'DRBY-PFIN-' . rand(100000, 999999),
            'subscription_type' => 'multi_day',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(5)->format('Y-m-d'),
            'end_date'                => now()->addDays(25)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 175.50,
            'clauses'                 => ['ظٹظ„طھط²ظ… ط§ظ„ط·ط±ظپط§ظ† ط¨ط§ظ„ظ…ظˆط§ط¹ظٹط¯ ط§ظ„ظ…طھظپظ‚ ط¹ظ„ظٹظ‡ط§.', 'ظٹط­ظ‚ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط§ظ„ط¥ظ„ط؛ط§ط، ظˆظپظ‚ ط§ظ„ط´ط±ظˆط·.'],
            'status'                  => 'active',
        ]);

        $this->activeSub = ActiveSubscription::create([
            'contract_id'   => $this->contract->id,
            'status'        => 'active',
            'child_id'      => $this->child->id,
            'driver_id'     => $this->driver->id,
            'parent_id'     => $this->parentUser->id,
            'pickup_lat'    => 32.88,
            'pickup_lng'    => 13.19,
            'pickup_label'  => 'ط§ظ„ظ…ظ†ط²ظ„',
            'pickup_time'   => '07:00:00',
            'dropoff_lat'   => 32.90,
            'dropoff_lng'   => 13.20,
            'dropoff_label' => 'ط§ظ„ظ…ط¯ط±ط³ط©',
            'dropoff_time'  => '14:00:00',
        ]);
    }

    // =========================================================
    // ط¥طµظ„ط§ط­ 1: POST /active-subscriptions/{id}/cancel ظƒط§ظ† ظ…ط¹ط·ظ„ط§ظ‹
    // =========================================================

    public function test_parent_can_cancel_active_subscription(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('active_subscriptions', [
            'id'     => $this->activeSub->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancelling_already_cancelled_subscription_returns_422(): void
    {
        $this->activeSub->update(['status' => 'cancelled']);

        $response = $this->actingAs($this->parentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_cancelling_nonexistent_subscription_returns_404(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/active-subscriptions/999999/cancel');

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_parent_cannot_cancel_another_parents_subscription(): void
    {
        $otherParentUser = User::create([
            'full_name'     => 'ظˆظ„ظٹ ط£ظ…ط± ط¢ط®ط±',
            'email'         => 'other.pfin.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        ParentModel::create(['user_id' => $otherParentUser->id, 'is_trusted' => 1]);

        $response = $this->actingAs($otherParentUser)
            ->postJson("/api/parent/active-subscriptions/{$this->activeSub->id}/cancel");

        $response->assertStatus(404);
    }

    // =========================================================
    // ط¥طµظ„ط§ط­ 2: ط¨ظ„ظˆظƒ billing ظٹط¹ط±ط¶ ظ‚ظٹظ…ط§ظ‹ ط­ظ‚ظٹظ‚ظٹط© ط¨ط¯ظ„ ط§ظ„ظˆظ‡ظ…ظٹط©
    // =========================================================

    public function test_active_subscription_billing_block_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson("/api/parent/active-subscriptions/{$this->activeSub->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.billing.currency', 'ط¯.ظ„');
        $response->assertJsonPath('data.billing.subscriptionType', 'multi_day');
        $response->assertJsonPath('data.billing.totalPrice', 175.5);
        $response->assertJsonPath('data.billing.childPrice', 175.5);
        $response->assertJsonPath('data.billing.autoRenew', false);
        $response->assertJsonPath('data.billing.paymentMethod', 'wallet');
        $response->assertJsonPath('data.billing.startsAt', now()->subDays(5)->format('Y-m-d'));
        $response->assertJsonPath('data.billing.endsAt', now()->addDays(25)->format('Y-m-d'));

        $remainingDays = $response->json('data.billing.remainingDays');
        $this->assertIsInt($remainingDays);
        $this->assertGreaterThan(20, $remainingDays);
        $this->assertLessThan(26, $remainingDays);
    }

    public function test_active_subscriptions_list_billing_block_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/parent/active-subscriptions');

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('id', $this->activeSub->id);

        $this->assertNotNull($item);
        $this->assertEquals('ط¯.ظ„', $item['billing']['currency']);
        $this->assertEquals(175.5, $item['billing']['childPrice']);
        $this->assertEquals('wallet', $item['billing']['paymentMethod']);
        $this->assertFalse($item['billing']['autoRenew']);
    }

    // =========================================================
    // ط¥طµظ„ط§ط­ 3: hold-trip ظٹط±ط¬ط¹ ط§ظ„ظ…ط¨ظ„ط؛ ط¨ط§ظ„ط¯ظٹظ†ط§ط± (ظˆظ„ظٹط³ ط¨ط§ظ„ظ‚ط±ظˆط´)
    // =========================================================

    public function test_hold_trip_amount_response_is_in_dinar_not_cents(): void
    {
        $this->parent->deposit(100000); // 1000 ط¯.ظ„

        $vehicleId = DB::table('vehicles')->where('driver_id', $this->driver->id)->value('id');

        $route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $vehicleId,
            'route_name' => 'ظ…ط³ط§ط± ظ…ط§ظ„ظٹط© ط§ظ„ط§ط®طھط¨ط§ط±',
            'route_type' => 'Morning',
            'shift_slot' => 'morning_go',
            'start_time' => '07:00:00',
            'status'     => 'Active',
        ]);

        $trip = Trip::create([
            'driver_id'    => $this->driver->id,
            'route_id'     => $route->id,
            'trip_type'    => 'Morning',
            'shift_slot'   => 'morning_go',
            'status'       => 'pending',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($this->parentUser)
            ->postJson('/api/parent/wallet/hold-trip', [
                'trip_id' => $trip->id,
                'amount'  => 25.5,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.amount', 25.5); // ظˆظ„ظٹط³ 2550
        $response->assertJsonPath('data.hold_status', 'held');

        $this->assertDatabaseHas('trip_escrow_holds', [
            'trip_id' => $trip->id,
            'amount'  => 2550, // ط§ظ„طھط®ط²ظٹظ† ط§ظ„ط¯ط§ط®ظ„ظٹ ظٹط¨ظ‚ظ‰ ط¨ط§ظ„ظ‚ط±ظˆط´ ظƒظ…ط§ ظ‡ظˆطŒ ظ„ط§ ظ†ط؛ظٹظ‘ط±ظ‡
        ]);
    }

    // =========================================================
    // ط¥طµظ„ط§ط­ 4: ContractResource ظ„ط§ ظٹط´ظٹط± ظ„ط£ط¹ظ…ط¯ط© ظ…ط­ط°ظˆظپط© (price, selected_clauses)
    // =========================================================

    public function test_contract_accept_response_uses_total_price_and_real_clauses(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->putJson("/api/contracts/{$this->contract->id}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.price', 175.5);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.status_text', 'ظ…ظپط¹ظ‘ظ„ ظˆط³ط§ط±ظٹ ط§ظ„ط¹ظ…ظ„ ط¨ظ‡');
        $response->assertJsonPath('data.parent.id', $this->parentUser->id);
        $response->assertJsonPath('data.parent.name', $this->parentUser->full_name);
        $response->assertJsonPath('data.driver.id', $this->driverUser->id);

        $clauses = $response->json('data.clauses');
        $this->assertIsArray($clauses);
        $this->assertCount(2, $clauses);
        $this->assertContains('ظٹظ„طھط²ظ… ط§ظ„ط·ط±ظپط§ظ† ط¨ط§ظ„ظ…ظˆط§ط¹ظٹط¯ ط§ظ„ظ…طھظپظ‚ ط¹ظ„ظٹظ‡ط§.', $clauses);
    }
}
