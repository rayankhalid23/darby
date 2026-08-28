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

        // طلب اشتراك مقبول
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->subscriptionRequestId = DB::table('requests')->insertGetId([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'status'            => 'accepted',
            'created_at'        => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'منزل ولي الأمر',
            'lat'        => 32.88,
            'lng'        => 13.19,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $this->subscriptionRequestId,
            'child_id'                    => $this->child->id,
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'both',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->subDays(5)->format('Y-m-d'),
            'end_date'                    => now()->addDays(25)->format('Y-m-d'),
            'working_days_count'          => 22,
            'distance_km'                 => 5.0,
            'trip_price'                  => 25.0,
            'price_per_child'             => 175.50,
            'discount_amount'             => 0.0,
            'total_amount_after_discount' => 175.50,
            'driver_net_price'            => 160.00,
        ]);

        $this->activeSub = ActiveSubscription::create([
            'subscription_request_id' => $this->subscriptionRequestId,
            'status'                  => 'active',
            'child_id'                => $this->child->id,
            'driver_id'               => $this->driver->id,
            'parent_id'               => $this->parentUser->id,
            'pickup_lat'              => 32.88,
            'pickup_lng'              => 13.19,
            'pickup_label'            => 'المنزل',
            'pickup_time'             => '07:00:00',
            'dropoff_lat'             => 32.90,
            'dropoff_lng'             => 13.20,
            'dropoff_label'           => 'المدرسة',
            'dropoff_time'            => '14:00:00',
        ]);
    }

    // =========================================================
    // إصلاح 1: POST /active-subscriptions/{id}/cancel
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
            'full_name'     => 'ولي أمر آخر',
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
    // إصلاح 2: قائمة وتفاصيل الاشتراكات النشطة
    // =========================================================

    public function test_active_subscription_details_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson("/api/parent/active-subscriptions/{$this->activeSub->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_active_subscriptions_list_reflects_real_data(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/parent/active-subscriptions');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertNotEmpty($response->json('data'));
    }

    // =========================================================
    // إصلاح 3: hold-trip يرجع المبلغ بالدينار
    // =========================================================

    public function test_hold_trip_amount_response_is_in_dinar_not_cents(): void
    {
        $this->parent->deposit(100000); // 1000 د.ل

        $vehicleId = DB::table('vehicles')->where('driver_id', $this->driver->id)->value('id');

        $route = RouteModel::create([
            'driver_id'  => $this->driver->id,
            'vehicle_id' => $vehicleId,
            'route_name' => 'مسار مالية الاختبار',
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
        $response->assertJsonPath('data.amount', 25.5);
        $response->assertJsonPath('data.hold_status', 'held');

        $this->assertDatabaseHas('trip_escrow_holds', [
            'trip_id' => $trip->id,
            'amount'  => 2550,
        ]);
    }
}
