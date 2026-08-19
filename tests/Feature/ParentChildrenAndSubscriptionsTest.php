<?php

namespace Tests\Feature;

use App\Models\Parent\Child;
use App\Models\Parent\ChildLogistics;
use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Parent\School;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;
use App\Models\Parent\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ParentChildrenAndSubscriptionsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $parentUser;
    protected ParentModel $parent;
    protected User $driverUser;
    protected Driver $driver;
    protected School $school;
    protected Address $address;
    protected Address $schoolAddress;
    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. ط¥ظ†ط´ط§ط، ظ…ظ†ط·ظ‚ط© ط¬ط؛ط±ط§ظپظٹط© ظˆظ…ط¯ط±ط³ط©
        $municipality = Municipality::firstOrCreate(['name' => 'ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط±ظƒط²']);
        $subMuni = SubMunicipality::firstOrCreate(['municipality_id' => $municipality->id, 'name' => 'ط§ظ„ط¸ظ‡ط±ط©']);
        $this->zone = Zone::firstOrCreate(['sub_municipality_id' => $subMuni->id, 'name' => 'ط´ط§ط±ط¹ ط§ظ„ظ†طµط±']);

        $this->school = School::create([
            'name'         => 'ظ…ط¯ط±ط³ط© ط§ظ„ظ†ظˆط± ط§ظ„ظ†ظ…ظˆط°ط¬ظٹط©',
            'zone_id'      => $this->zone->id,
            'lat'          => 32.88000000,
            'lng'          => 13.18000000,
            'address'      => 'ط·ط±ط§ط¨ظ„ط³ - ط´ط§ط±ط¹ ط§ظ„ظ†طµط±',
            'status'       => 'approved',
        ]);

        // 2. ط¥ظ†ط´ط§ط، ط­ط³ط§ط¨ ظˆظ„ظٹ ط£ظ…ط±
        $this->parentUser = User::create([
            'full_name'     => 'ط£ط­ظ…ط¯ ط³ط§ظ„ظ… ط§ظ„ظپظٹطھظˆط±ظٹ',
            'email'         => 'parent.test.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
        $this->parent = ParentModel::create([
            'user_id'    => $this->parentUser->id,
            'is_trusted' => 1,
        ]);

        // 3. ط¥ظ†ط´ط§ط، ط¹ظ†ظˆط§ظ† ط³ظƒظ† ظˆط¹ظ†ظˆط§ظ† ظ…ط¯ط±ط³ط© ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
        $this->address = Address::create([
            'parent_id'   => $this->parentUser->id,
            'zone_id'     => $this->zone->id,
            'label'       => 'ط§ظ„ظ…ظ†ط²ظ„ ط§ظ„ط±ط¦ظٹط³ظٹ',
            'lat'         => 32.88500000,
            'lng'         => 13.18500000,
            'is_default'  => true,
        ]);

        $this->schoolAddress = Address::create([
            'parent_id'   => $this->parentUser->id,
            'zone_id'     => $this->zone->id,
            'label'       => 'ظ…ظˆظ‚ط¹ ط§ظ„ظ…ط¯ط±ط³ط©',
            'lat'         => 32.88000000,
            'lng'         => 13.18000000,
            'is_default'  => false,
        ]);

        // 4. ط¥ظ†ط´ط§ط، ط­ط³ط§ط¨ ط³ط§ط¦ظ‚
        $this->driverUser = User::create([
            'full_name'     => 'ط§ظ„ظƒط§ط¨طھظ† ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ…',
            'email'         => 'driver.test.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => Hash::make('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);
        $this->driver = Driver::create([
            'user_id'                 => $this->driverUser->id,
            'national_id'             => '11988' . rand(1000000, 9999999),
            'license_number'          => 'LY-' . rand(1000, 9999),
            'status'                  => 'Approved',
            'morning_go'              => 1,
            'morning_return'          => 1,
            'afternoon_go'            => 1,
            'afternoon_return'        => 1,
            'morning_go_capacity'     => 10,
            'morning_return_capacity' => 10,
            'evening_go_capacity'     => 10,
            'evening_return_capacity' => 10,
        ]);

        DB::table('drivers')->where('id', $this->driver->id)->update([
            'morning_go'       => 1,
            'morning_return'   => 1,
            'afternoon_go'     => 1,
            'afternoon_return' => 1,
        ]);

        DB::table('driver_seat_slots')->insert([
            ['driver_id' => $this->driver->id, 'slot' => 'morning_go', 'total_seats' => 10, 'reserved_seats' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['driver_id' => $this->driver->id, 'slot' => 'morning_return', 'total_seats' => 10, 'reserved_seats' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ط±ط¨ط· ط§ظ„ط³ط§ط¦ظ‚ ط¨ط§ظ„ظ…ظ†ط·ظ‚ط©
        DB::table('driver_zone')->insert([
            'driver_id' => $this->driver->id,
            'zone_id'   => $this->zone->id,
        ]);
    }

    /**
     * 1. ط§ط®طھط¨ط§ط± ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظˆط¬ظˆط¯ ط£ط·ظپط§ظ„ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط± (has-children)
     */
    public function test_check_has_children_returns_correct_boolean(): void
    {
        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/parent/children/has-children');

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'has_children' => false,
            ]);
    }

    /**
     * 2. ط§ط®طھط¨ط§ط± ط¥ط¶ط§ظپط© ط·ظپظ„ ط¬ط¯ظٹط¯ ظ…ط¹ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ†ظ‚ظ„ ط§ظ„ظ„ظˆط¬ط³طھظٹط© (Add Child)
     */
    public function test_add_child_with_logistics_successfully(): void
    {
        $payload = [
            'school_id'           => $this->school->id,
            'address_id'          => $this->address->id,
            'full_name'           => 'ظ…ط­ظ…ط¯ ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'birth_date'          => '2015-05-10',
            'gender'              => 'male',
            'grade'               => 4,
            'medical_notes'       => 'ط­ط³ط§ط³ظٹط© ظ…ظ† ط§ظ„ط؛ط¨ط§ط±',
            'notification_radius' => 500,
            'preferred_time_slot' => 'morning',
            'trip_direction'      => 'both',
            'pickup_time'         => '07:15',
            'dropoff_time'        => '13:30',
            'start_date'          => now()->addDay()->format('Y-m-d'),
            'end_date'            => now()->addMonths(3)->format('Y-m-d'),
            'subscription_type'   => 'multi_day',
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent/children', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'طھظ… ط¥ط¶ط§ظپط© ط¨ظٹط§ظ†ط§طھ ط§ظ„ط·ظپظ„ ط¨ظ†ط¬ط§ط­.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'full_name',
                    'gender',
                    'grade',
                    'school',
                    'address',
                    'logistics',
                ]
            ]);

        $this->assertDatabaseHas('children', [
            'full_name' => 'ظ…ط­ظ…ط¯ ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'parent_id' => $this->parent->id,
            'gender'    => 'male',
        ]);
    }

    /**
     * 3. ط§ط®طھط¨ط§ط± ط¬ظ„ط¨ ظ‚ط§ط¦ظ…ط© ط£ط·ظپط§ظ„ ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ظˆط¹ط±ط¶ طھظپط§طµظٹظ„ ط·ظپظ„ ظ…ط­ط¯ط¯
     */
    public function test_list_and_show_child(): void
    {
        $child = Child::create([
            'parent_id'           => $this->parent->id,
            'school_id'           => $this->school->id,
            'address_id'          => $this->address->id,
            'full_name'           => 'ظپط§ط·ظ…ط© ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'birth_date'          => '2016-08-15',
            'gender'              => 'female',
            'grade'               => 3,
            'notification_radius' => 500,
            'qr_code_token'       => 'QR_' . uniqid(),
        ]);

        ChildLogistics::create([
            'child_id'            => $child->id,
            'preferred_time_slot' => 'both',
            'trip_direction'      => 'both',
            'start_date'          => now()->addDay()->format('Y-m-d'),
            'end_date'            => now()->addMonths(2)->format('Y-m-d'),
            'subscription_type' => 'multi_day',
        ]);

        // ظ‚ط§ط¦ظ…ط© ط§ظ„ط£ط·ظپط§ظ„
        $listRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/parent/children');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // ط¹ط±ط¶ طھظپط§طµظٹظ„ ط·ظپظ„ ظ…ط­ط¯ط¯
        $showRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson("/api/parent/children/{$child->id}");

        $showRes->assertStatus(200)
            ->assertJsonPath('data.full_name', 'ظپط§ط·ظ…ط© ط£ط­ظ…ط¯ ط³ط§ظ„ظ…')
            ->assertJsonPath('data.gender', 'female');

        // ط¹ط±ط¶ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ†ظ‚ظ„ ظˆط§ظ„ظ„ظˆط¬ط³طھظٹط§طھ
        $subRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson("/api/parent/children/{$child->id}/subscription");

        $subRes->assertStatus(200)
            ->assertJsonPath('data.preferred_time_slot', 'both');
    }

    /**
     * 4. ط§ط®طھط¨ط§ط± طھط¹ط¯ظٹظ„ ط¨ظٹط§ظ†ط§طھ ط§ظ„ط·ظپظ„ ظˆط§ظ„ظ†ظ‚ظ„ ظƒط§ظ…ظ„ط§ظ‹
     */
    public function test_update_child_and_logistics_successfully(): void
    {
        $child = Child::create([
            'parent_id'           => $this->parent->id,
            'school_id'           => $this->school->id,
            'address_id'          => $this->address->id,
            'full_name'           => 'ط¹ظ„ظٹ ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'birth_date'          => '2014-02-10',
            'gender'              => 'male',
            'grade'               => 5,
            'notification_radius' => 500,
            'qr_code_token'       => 'QR_' . uniqid(),
        ]);

        ChildLogistics::create([
            'child_id'            => $child->id,
            'preferred_time_slot' => 'morning',
            'trip_direction'      => 'go',
            'start_date'          => now()->addDay()->format('Y-m-d'),
            'end_date'            => now()->addMonth()->format('Y-m-d'),
            'subscription_type' => 'multi_day',
        ]);

        $updatePayload = [
            'full_name'           => 'ط¹ظ„ظٹ ط£ط­ظ…ط¯ ط³ط§ظ„ظ… ط§ظ„ظ…ط¹ط¯ظ„',
            'grade'               => 6,
            'preferred_time_slot' => 'both',
            'trip_direction'      => 'both',
            'notification_radius' => 600,
        ];

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson("/api/parent/children/{$child->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'طھظ… طھط­ط¯ظٹط« ط¨ظٹط§ظ†ط§طھ ط§ظ„ط·ظپظ„ ط¨ظ†ط¬ط§ط­.',
            ]);

        $this->assertDatabaseHas('children', [
            'id'        => $child->id,
            'full_name' => 'ط¹ظ„ظٹ ط£ط­ظ…ط¯ ط³ط§ظ„ظ… ط§ظ„ظ…ط¹ط¯ظ„',
            'grade'     => 6,
        ]);
    }

    /**
     * 5. ط§ط®طھط¨ط§ط± ط­ط°ظپ ط·ظپظ„
     */
    public function test_delete_child_successfully(): void
    {
        $child = Child::create([
            'parent_id'           => $this->parent->id,
            'school_id'           => $this->school->id,
            'address_id'          => $this->address->id,
            'full_name'           => 'ط³ط§ط±ط© ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'birth_date'          => '2017-09-01',
            'gender'              => 'female',
            'grade'               => 2,
            'notification_radius' => 500,
            'qr_code_token'       => 'QR_' . uniqid(),
        ]);

        $response = $this->actingAs($this->parentUser, 'sanctum')
            ->deleteJson("/api/parent/children/{$child->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'طھظ… ط­ط°ظپ ط¨ظٹط§ظ†ط§طھ ط§ظ„ط·ظپظ„ ظˆط¥ظ„ط؛ط§ط، ط§ط´طھط±ط§ظƒظ‡ ط¨ظ†ط¬ط§ط­.',
            ]);

        $this->assertDatabaseMissing('children', [
            'id' => $child->id,
        ]);
    }

    /**
     * 6. ط§ط®طھط¨ط§ط± ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط§ط´طھط±ط§ظƒطŒ ط¬ظ„ط¨ ط§ظ„ط·ظ„ط¨ط§طھطŒ ظˆط¥ظ„ط؛ط§ط، ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ظ…ط¹ظ„ظ‚
     */
    public function test_parent_subscription_request_lifecycle(): void
    {
        $child = Child::create([
            'parent_id'           => $this->parent->id,
            'school_id'           => $this->school->id,
            'address_id'          => $this->address->id,
            'full_name'           => 'ط·ط§ط±ظ‚ ط£ط­ظ…ط¯ ط³ط§ظ„ظ…',
            'birth_date'          => '2015-01-01',
            'gender'              => 'male',
            'grade'               => 4,
            'notification_radius' => 500,
            'qr_code_token'       => 'QR_' . uniqid(),
        ]);

        // ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ
        $reqPayload = [
            'driver_id'         => $this->driver->id,
            'school_id'         => $this->school->id,
            'subscription_type' => 'multi_day',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->addDay()->format('Y-m-d'),
            'end_date'          => now()->addMonth()->format('Y-m-d'),
            'children'          => [
                [
                    'child_id'           => $child->id,
                    'pickup_address_id'  => $this->address->id,
                    'dropoff_address_id' => $this->school->id,
                    'price_per_child'    => 150.00,
                ]
            ]
        ];

        $storeRes = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson('/api/parent', $reqPayload);

        $storeRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'طھظ… ط¥ط±ط³ط§ظ„ ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ ط¨ظ†ط¬ط§ط­.',
            ]);

        $requestId = $storeRes->json('data.id');

        // ط¬ظ„ط¨ ظ‚ط§ط¦ظ…ط© ط§ظ„ط·ظ„ط¨ط§طھ
        $listReqRes = $this->actingAs($this->parentUser, 'sanctum')
            ->getJson('/api/parent/requests');

        $listReqRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // ط¥ظ„ط؛ط§ط، ط§ظ„ط·ظ„ط¨ ط§ظ„ظ…ط¹ظ„ظ‚
        $cancelRes = $this->actingAs($this->parentUser, 'sanctum')
            ->postJson("/api/parent/requests/{$requestId}/cancel");

        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'طھظ… ط¥ظ„ط؛ط§ط، ط·ظ„ط¨ ط§ظ„ط§ط´طھط±ط§ظƒ ط¨ظ†ط¬ط§ط­.',
            ]);

        $this->assertDatabaseHas('requests', [
            'id'     => $requestId,
            'status' => 'cancelled',
        ]);
    }
}
