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
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;

/**
 * اختبار أمني: يجب ألا يستطيع سائق تحديث حالة (صعود/نزول/غياب) طفل مشترك مع سائق آخر (IDOR).
 * GET/POST /api/v1/driver/trips/{tripId}/pickup|dropoff|absent
 */
class DriverTripStatusOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverAUser;
    protected Driver $driverA;
    protected Trip $tripA;
    protected ActiveSubscription $subA;

    protected User $driverBUser;
    protected Driver $driverB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->driverAUser = User::create([
            'full_name'     => 'السائق أ',
            'email'         => 'driver.a.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->driverA = Driver::create([
            'user_id'        => $this->driverAUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $this->driverBUser = User::create([
            'full_name'     => 'السائق ب',
            'email'         => 'driver.b.' . uniqid() . '@darby.test',
            'phone_number'  => '093' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);
        $this->driverB = Driver::create([
            'user_id'        => $this->driverBUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $parentUser = User::create([
            'full_name'     => 'ولي الأمر',
            'email'         => 'parent.own.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $parent = ParentModel::create(['user_id' => $parentUser->id, 'is_trusted' => 1]);

        $school = School::create([
            'name'    => 'مدرسة اختبار الملكية',
            'address' => 'شارع الاختبار',
            'lat'     => 32.90,
            'lng'     => 13.20,
            'status'  => 'active',
        ]);

        $child = Child::create([
            'parent_id'           => $parent->id,
            'school_id'           => $school->id,
            'full_name'           => 'طفل',
            'birth_date'          => '2018-05-10',
            'gender'              => 'male',
            'grade'               => 1,
            'notification_radius' => 500,
        ]);

        $subscriptionRequest = SubscriptionRequest::create([
            'parent_id'                   => $parent->id,
            'driver_id'                   => $this->driverA->id,
            'status'                      => SubscriptionRequest::STATUS_ACCEPTED,
            'total_price'                 => 100,
            'discount_amount'             => 0,
            'total_amount_after_discount' => 100,
            'children_count'              => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'                  => $subscriptionRequest->id,
            'child_id'                    => $child->id,
            'subscription_type'           => 'multi_day',
            'trip_direction'              => 'go',
            'timing'                      => 'MORNING',
            'start_date'                  => now()->format('Y-m-d'),
            'end_date'                    => now()->addMonths(1)->format('Y-m-d'),
            'working_days_count'          => 22,
            'distance_km'                 => 4.0,
            'trip_price'                  => 100.00,
            'price_per_child'             => 100.00,
            'discount_amount'             => 0.00,
            'total_amount_after_discount' => 100.00,
            'driver_net_price'            => 92.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        $this->subA = ActiveSubscription::create([
            'subscription_request_id' => $subscriptionRequest->id,
            'status'                  => 'active',
            'child_id'                => $child->id,
            'driver_id'               => $this->driverA->id,
            'parent_id'               => $parentUser->id,
            'pickup_lat'              => 32.88,
            'pickup_lng'              => 13.19,
            'pickup_label'            => 'منزل',
            'pickup_time'             => '07:00:00',
            'dropoff_lat'             => 32.90,
            'dropoff_lng'             => 13.20,
            'dropoff_label'           => 'مدرسة',
            'dropoff_time'            => '14:00:00',
        ]);

        $this->tripA = Trip::create([
            'driver_id'    => $this->driverA->id,
            'trip_type'    => 'Morning',
            'status'       => 'in_progress',
            'trip_date'    => now()->toDateString(),
            'scheduled_at' => now(),
        ]);

        TripStop::create([
            'trip_id'        => $this->tripA->id,
            'child_id'       => $child->id,
            'stop_type'      => 'home',
            'lat'            => 32.88,
            'lng'            => 13.19,
            'label'          => 'منزل',
            'sequence_order' => 1,
            'status'         => 'pending',
        ]);
    }

    public function test_driver_can_update_status_of_their_own_subscription(): void
    {
        $response = $this->actingAs($this->driverAUser)
            ->postJson("/api/v1/driver/trips/{$this->tripA->id}/children/{$this->subA->id}/status", [
                'action'    => 'pickup',
                'latitude'  => 32.88,
                'longitude' => 13.19,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
    }

    public function test_driver_cannot_update_status_of_another_drivers_subscription(): void
    {
        $response = $this->actingAs($this->driverBUser)
            ->postJson("/api/v1/driver/trips/{$this->tripA->id}/children/{$this->subA->id}/status", [
                'action'    => 'pickup',
                'latitude'  => 32.88,
                'longitude' => 13.19,
            ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('trip_events', [
            'trip_id'  => $this->tripA->id,
            'child_id' => $this->subA->child_id,
        ]);
    }
}
