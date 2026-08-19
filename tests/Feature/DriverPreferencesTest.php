<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;

class DriverPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected Zone $zone1;
    protected Zone $zone2;
    protected Zone $otherSubZone;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'ط³ط§ط¦ظ‚'],
        ]);

        // 1. ط¥ظ†ط´ط§ط، ط¨ظ„ط¯ظٹط© ظپط±ط¹ظٹط© ظˆظ…ظ†ط§ط·ظ‚ ظ„ظ„ط§ط®طھط¨ط§ط±
        $municipality = Municipality::firstOrCreate(
            ['name' => 'ط¨ظ„ط¯ظٹط© ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظƒط¨ط±ظ‰ ط§ظ„ط§ط®طھظٹط§ط±ظٹط©']
        );

        $subMuni1 = SubMunicipality::firstOrCreate(
            ['name' => 'ظ…ظ†ط·ظ‚ط© ط§ظ„ظپط±ط¹ظٹط© 1', 'municipality_id' => $municipality->id]
        );

        $subMuni2 = SubMunicipality::firstOrCreate(
            ['name' => 'ظ…ظ†ط·ظ‚ط© ط§ظ„ظپط±ط¹ظٹط© 2 ظ…ط®طھظ„ظپط©', 'municipality_id' => $municipality->id]
        );

        $this->zone1 = Zone::firstOrCreate(
            ['name' => 'ظ…ظ†ط·ظ‚ط© 1-ط£', 'sub_municipality_id' => $subMuni1->id]
        );

        $this->zone2 = Zone::firstOrCreate(
            ['name' => 'ظ…ظ†ط·ظ‚ط© 1-ط¨', 'sub_municipality_id' => $subMuni1->id]
        );

        $this->otherSubZone = Zone::firstOrCreate(
            ['name' => 'ظ…ظ†ط·ظ‚ط© 2-ط£ (ظ…ط®طھظ„ظپط©)', 'sub_municipality_id' => $subMuni2->id]
        );

        // 2. ط¥ظ†ط´ط§ط، ظ…ط³طھط®ط¯ظ… ظˆط³ط§ط¦ظ‚
        $this->driverUser = User::create([
            'full_name'     => 'ط³ط§ط¦ظ‚ طھظپط¶ظٹظ„ط§طھ ط§ظ„ط§ط®طھط¨ط§ط±',
            'email'         => 'driver.pref.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'           => $this->driverUser->id,
            'national_id'       => 'NAT' . rand(100000, 999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->format('Y-m-d'),
            'status'            => 'Approved',
            'morning_go'        => true,
            'morning_return'    => true,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'multi_day', // ENUM صحيح: single_day | multi_day | both
        ]);

        // ط¥ط¶ط§ظپط© ظ…ظ†ط·ظ‚ط© ط§ط¨طھط¯ط§ط¦ظٹط© ظ„ظ„ط³ط§ط¦ظ‚
        $this->driver->zones()->sync([$this->zone1->id]);
    }

    /**
     * Test 1: GET /api/v1/driver/preferences (ط¹ط±ط¶ ط§ظ„طھظپط¶ظٹظ„ط§طھ)
     */
    public function test_get_driver_preferences_show(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/preferences');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'driver_id',
                'shift_slots' => ['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'],
                'subscription_type',
                'seat_slots',
                'coverage',
            ]
        ]);
        $response->assertJsonPath('data.shift_slots.morning_go', true);
        $response->assertJsonPath('data.shift_slots.afternoon_go', false);
    }

    /**
     * Test 2: PUT /api/v1/driver/preferences (طھط­ط¯ظٹط« ط´ط§ظ…ظ„ ظ†ط§ط¬ط­)
     */
    public function test_update_driver_preferences_success(): void
    {
        $payload = [
            'morning_go'        => true,
            'morning_return'    => false,
            'afternoon_go'      => true,
            'afternoon_return'  => true,
            'subscription_type' => 'both',
            'school_stages'     => ['primary'],
            'zones'             => [$this->zone1->id, $this->zone2->id],
        ];

        $response = $this->actingAs($this->driverUser)
            ->putJson('/api/v1/driver/preferences', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.shift_slots.morning_go', true);
        $response->assertJsonPath('data.shift_slots.morning_return', false);
        $response->assertJsonPath('data.shift_slots.afternoon_go', true);
        $response->assertJsonPath('data.subscription_type', 'both');
    }

    /**
     * Test 3: PUT /api/v1/driver/preferences (ظپط´ظ„ ط¹ظ†ط¯ ط¹ط¯ظ… ط§ط®طھظٹط§ط± ط£ظٹ ظپطھط±ط©)
     */
    public function test_update_driver_preferences_validation_fails_without_shift_slots(): void
    {
        $payload = [
            'morning_go'        => false,
            'morning_return'    => false,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'multi_day', // قيمة ENUM صحيحة — الـ 422 سببه كل الـ shifts false
            'school_stages'     => ['primary'],
            'zones'             => [$this->zone1->id],
        ];

        $response = $this->actingAs($this->driverUser)
            ->putJson('/api/v1/driver/preferences', $payload);

        $response->assertStatus(422);
    }

    /**
     * Test 4: PUT /api/v1/driver/preferences (ظپط´ظ„ ط¹ظ†ط¯ ط§ط®طھظٹط§ط± ظ…ظ†ط§ط·ظ‚ طھطھط¨ط¹ ط¨ظ„ط¯ظٹط§طھ ظپط±ط¹ظٹط© ظ…ط®طھظ„ظپط©)
     */
    public function test_update_driver_preferences_fails_when_zones_belong_to_different_sub_municipalities(): void
    {
        $payload = [
            'morning_go'        => true,
            'morning_return'    => true,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'multi_day', // قيمة ENUM صحيحة — الـ 422 سببه مناطق ببلديات مختلفة
            'school_stages'     => ['primary'],
            'zones'             => [$this->zone1->id, $this->otherSubZone->id], // ط¨ظ„ط¯ظٹط§طھ ظ…ط®طھظ„ظپط©!
        ];

        $response = $this->actingAs($this->driverUser)
            ->putJson('/api/v1/driver/preferences', $payload);

        $response->assertStatus(422); // Exception مُعالج بالـ catch في الـ controller
    }

    /**
     * Test 5: POST /api/v1/driver/preferences/zones/add (ط¥ط¶ط§ظپط© ظ…ظ†ط·ظ‚ط© ظ…ظ†ظپط±ط¯ط© ط¨ظ†ط¬ط§ط­)
     */
    public function test_add_zone_to_driver_preferences_success(): void
    {
        $payload = ['zone_id' => $this->zone2->id];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/preferences/zones/add', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertDatabaseHas('driver_zone', [
            'driver_id' => $this->driver->id,
            'zone_id'   => $this->zone2->id,
        ]);
    }

    /**
     * Test 6: POST /api/v1/driver/preferences/zones/add (ظپط´ظ„ ط¹ظ†ط¯ ط§ط®طھظٹط§ط± ظ…ظ†ط·ظ‚ط© ط¨ط¨ظ„ط¯ظٹط© ظ…ط®طھظ„ظپط©)
     */
    public function test_add_zone_fails_when_different_sub_municipality(): void
    {
        $payload = ['zone_id' => $this->otherSubZone->id];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/preferences/zones/add', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('message', 'لا يمكن إضافة هذه المنطقة؛ لأنها تتبع بلدية فرعية مختلفة.');
    }

    /**
     * Test 7: POST /api/v1/driver/preferences/zones/remove (ط¥ط²ط§ظ„ط© ظ…ظ†ط·ظ‚ط© ط¨ظ†ط¬ط§ط­)
     */
    public function test_remove_zone_from_driver_preferences_success(): void
    {
        $payload = ['zone_id' => $this->zone1->id];

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/preferences/zones/remove', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertDatabaseMissing('driver_zone', [
            'driver_id' => $this->driver->id,
            'zone_id'   => $this->zone1->id,
        ]);
    }

    /**
     * Test 8: GET /api/v1/driver/preferences/defaults (ط¥ط±ط¬ط§ط¹ ط§ظ„ط®ظٹط§ط±ط§طھ ط§ظ„ط§ظپطھط±ط§ط¶ظٹط© ظ„ظ„ظ†ط¸ط§ظ…)
     */
    public function test_get_driver_preference_system_defaults(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/preferences/defaults');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'available_shift_slots',
                'available_subscription_types',
                'geography_tree',
            ]
        ]);
    }
}
