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

/**
 * ط§ط®طھط¨ط§ط± ط¹ط±ط¶ ظˆط§ط³طھط±ط¬ط§ط¹ ط·ظ„ط¨ط§طھ ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ط®ط§طµط© ط¨ط§ظ„ط³ط§ط¦ظ‚
 *
 * ظٹط³طھط®ط¯ظ… DatabaseTransactions: ط¬ظ…ظٹط¹ ط§ظ„ط¹ظ…ظ„ظٹط§طھ طھظڈظ„ط؛ظ‰ طھظ„ظ‚ط§ط¦ظٹط§ظ‹ ط¨ط¹ط¯ ط§ظ„ط§ط®طھط¨ط§ط± ظ„ط­ظ…ط§ظٹط© ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط­ظ‚ظٹظ‚ظٹط©.
 */
class DriverSubscriptionListingTest extends TestCase
{
    use DatabaseTransactions;

    protected User              $driverUser;
    protected Driver            $driver;
    protected User              $parentUser;
    protected ParentModel       $parent;
    protected Child             $child;
    protected School            $school;
    protected SubscriptionRequest $pendingRequest;
    protected SubscriptionRequest $rejectedRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // ط¥ط¯ط±ط§ط¬ ط§ظ„ط£ط¯ظˆط§ط±
        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'ط³ط§ط¦ظ‚'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
        ]);

        // 1. ط­ط³ط§ط¨ ط§ظ„ط³ط§ط¦ظ‚
        $this->driverUser = User::create([
            'full_name'    => 'ط³ط§ط¦ظ‚ ط§ظ„ط¹ط±ط¶',
            'email'        => 'driver.listing.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
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

        // 2. ط­ط³ط§ط¨ ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
        $this->parentUser = User::create([
            'full_name'    => 'ظˆظ„ظٹ ط£ظ…ط± ط§ظ„ط¹ط±ط¶',
            'email'        => 'parent.listing.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);

        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // 3. ظ…ط¯ط±ط³ط© ط§ظ„ط§ط®طھط¨ط§ط±
        $this->school = School::create([
            'name'    => 'ظ…ط¯ط±ط³ط© ط§ظ„ظ†ظˆط±',
            'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³طŒ ط·ط±ط§ط¨ظ„ط³',
            'lat'     => 32.8870,
            'lng'     => 13.1890,
            'status'  => 'active',
        ]);

        // 4. عنوان ولي الأمر
        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $this->parentUser->id,
            'label'      => 'البيت الرئيسي',
            'lat'        => 32.8810,
            'lng'        => 13.1850,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. ط§ظ„ط·ظپظ„
        $this->child = Child::create([
            'parent_id' => $this->parent->id,
            'full_name' => 'ط³ط§ط±ط© ط£ط­ظ…ط¯',
            'birth_date'=> '2017-03-15',
            'gender'    => 'female',
            'grade'     => 2,
            'notification_radius' => 500,
        ]);

        // 6. ط·ظ„ط¨ ظ…ط¹ظ„ظ‘ظ‚
        $this->pendingRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'multi_day',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 250.00,
            'pickup_time'       => '07:15:00',
            'dropoff_time'      => '14:30:00',
            'max_waiting_time'  => 15,
            'status'            => SubscriptionRequest::STATUS_PENDING,
            'children_count'    => 1,
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $this->pendingRequest->id,
            'child_id'           => $this->child->id,
            'pickup_address_id'  => $addressId,
            'dropoff_address_id' => $this->school->id,
            'home_lat'           => 32.8810,
            'home_lng'           => 13.1850,
            'home_label'         => 'ط§ظ„ط¨ظٹطھ ط§ظ„ط±ط¦ظٹط³ظٹ',
            'school_lat'         => 32.8870,
            'school_lng'         => 13.1890,
            'school_label'       => 'ظ…ط¯ط±ط³ط© ط§ظ„ظ†ظˆط±',
            'price_per_child'    => 250.00,
        ]);

        // 7. ط·ظ„ط¨ ظ…ط±ظپظˆط¶
        $this->rejectedRequest = SubscriptionRequest::create([
            'parent_id'         => $this->parent->id,
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'multi_day',
            'direction'         => 'go',
            'timing'            => 'EVENING',
            'start_date'        => now()->addDays(1)->format('Y-m-d'),
            'end_date'          => now()->addMonths(1)->format('Y-m-d'),
            'days_count'        => 22,
            'total_price'       => 150.00,
            'status'            => SubscriptionRequest::STATUS_REJECTED,
            'rejection_reason'  => 'ط®ط§ط±ط¬ ط§ظ„طھط؛ط·ظٹط©',
            'children_count'    => 1,
        ]);
    }

    // =========================================================
    // 1. ط§ط®طھط¨ط§ط± ط¹ط±ط¶ ظ‚ط§ط¦ظ…ط© ط§ظ„ط·ظ„ط¨ط§طھ ظ„ظ„ط³ط§ط¦ظ‚
    // =========================================================
    public function test_driver_can_list_all_their_subscription_requests(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'status',
                    'start_date',
                    'working_days_count',
                    'total_amount',
                    'children_count',
                    'parent' => ['id', 'name', 'phone'],
                    'driver' => ['id', 'name', 'phone'],
                    'children',
                ]
            ]
        ]);

        $this->assertGreaterThanOrEqual(2, $response->json('count'));
    }

    // =========================================================
    // 2. ط§ط®طھط¨ط§ط± ظپظ„طھط±ط© ط§ظ„ط·ظ„ط¨ط§طھ ط­ط³ط¨ ط§ظ„ط­ط§ظ„ط© (pending / rejected)
    // =========================================================
    public function test_driver_can_filter_subscription_requests(): void
    {
        // ظپظ„طھط±ط© ط§ظ„ط·ظ„ط¨ط§طھ ط§ظ„ظ…ط¹ظ„ظ‚ط©
        $pendingResponse = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests?filter=pending');

        $pendingResponse->assertStatus(200);
        $pendingResponse->assertJsonPath('success', true);
        
        $pendingData = $pendingResponse->json('data');
        foreach ($pendingData as $item) {
            $this->assertEquals('pending', $item['status']);
        }

        // ظپظ„طھط±ط© ط§ظ„ط·ظ„ط¨ط§طھ ط§ظ„ظ…ط±ظپظˆط¶ط©
        $rejectedResponse = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/requests?filter=rejected');

        $rejectedResponse->assertStatus(200);
        $rejectedResponse->assertJsonPath('success', true);

        $rejectedData = $rejectedResponse->json('data');
        foreach ($rejectedData as $item) {
            $this->assertEquals('rejected', $item['status']);
        }
    }

    // =========================================================
    // 3. ط§ط®طھط¨ط§ط± ط¹ط±ط¶ طھظپط§طµظٹظ„ ط·ظ„ط¨ ظ…ط¹ظٹظ†
    // =========================================================
    public function test_driver_can_view_single_request_details(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $this->pendingRequest->id);
        $response->assertJsonPath('data.parent.name', 'ظˆظ„ظٹ ط£ظ…ط± ط§ظ„ط¹ط±ط¶');
    }

    // =========================================================
    // 4. ط§ط®طھط¨ط§ط± ظ…ظ†ط¹ ط§ظ„ط³ط§ط¦ظ‚ ظ…ظ† ط¹ط±ط¶ طھظپط§طµظٹظ„ ط·ظ„ط¨ ط؛ظٹط± ظ…ط®طµطµ ظ„ظ‡
    // =========================================================
    public function test_driver_cannot_view_request_belonging_to_another_driver(): void
    {
        $otherDriverUser = User::create([
            'full_name'    => 'ط³ط§ط¦ظ‚ ط«ط§ظ†ظٹ',
            'email'        => 'other.driver.' . uniqid() . '@darby.test',
            'phone_number' => '096' . rand(1000000, 9999999),
            'password_hash'=> bcrypt('password123'),
            'role_id'      => 2,
            'is_active'    => 1,
        ]);

        Driver::create([
            'user_id'        => $otherDriverUser->id,
            'national_id'    => 'NAT' . rand(100000, 999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
            'status'         => 'Approved',
        ]);

        $response = $this->actingAs($otherDriverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}");

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // 5. ط§ط®طھط¨ط§ط± ط¹ط±ط¶ طھظپط§طµظٹظ„ ط§ظ„ط±ط­ظ„ط© ظ„ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ
    // =========================================================
    public function test_driver_can_view_trip_details_of_request(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson("/api/driver/requests/{$this->pendingRequest->id}/trip-details");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.request_id', $this->pendingRequest->id);
        $response->assertJsonPath('data.parent.name', 'ظˆظ„ظٹ ط£ظ…ط± ط§ظ„ط¹ط±ط¶');
        $response->assertJsonPath('data.school.name', 'ظ…ط¯ط±ط³ط© ط§ظ„ظ†ظˆط±');
        $response->assertJsonPath('data.school.latitude', 32.8870);
        $response->assertJsonPath('data.children.0.name', 'ط³ط§ط±ط© ط£ط­ظ…ط¯');
    }

    // =========================================================
    // 6. ط§ط®طھط¨ط§ط± ظ…ظ†ط¹ ط؛ظٹط± ط§ظ„ط³ط§ط¦ظ‚ ظ…ظ† ط¹ط±ط¶ ظ‚ط§ط¦ظ…ط© ط§ظ„ط·ظ„ط¨ط§طھ
    // =========================================================
    public function test_non_driver_cannot_list_driver_requests(): void
    {
        $response = $this->actingAs($this->parentUser)
            ->getJson('/api/driver/requests');

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    // =========================================================
    // 7. ط§ط®طھط¨ط§ط± ط±ظپط¶ ط§ظ„ط·ظ„ط¨ط§طھ ط؛ظٹط± ط§ظ„ظ…ظˆط«ظˆظ‚ط© (Unauthenticated)
    // =========================================================
    public function test_unauthenticated_user_cannot_list_requests(): void
    {
        $response = $this->getJson('/api/driver/requests');

        $response->assertStatus(401);
    }
}
