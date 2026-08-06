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
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
        ]);

        // 1. إنشاء بلدية فرعية ومناطق للاختبار
        $municipality = Municipality::firstOrCreate(
            ['name' => 'بلدية طرابلس الكبرى الاختيارية']
        );

        $subMuni1 = SubMunicipality::firstOrCreate(
            ['name' => 'منطقة الفرعية 1', 'municipality_id' => $municipality->id]
        );

        $subMuni2 = SubMunicipality::firstOrCreate(
            ['name' => 'منطقة الفرعية 2 مختلفة', 'municipality_id' => $municipality->id]
        );

        $this->zone1 = Zone::firstOrCreate(
            ['name' => 'منطقة 1-أ', 'sub_municipality_id' => $subMuni1->id]
        );

        $this->zone2 = Zone::firstOrCreate(
            ['name' => 'منطقة 1-ب', 'sub_municipality_id' => $subMuni1->id]
        );

        $this->otherSubZone = Zone::firstOrCreate(
            ['name' => 'منطقة 2-أ (مختلفة)', 'sub_municipality_id' => $subMuni2->id]
        );

        // 2. إنشاء مستخدم وسائق
        $this->driverUser = User::create([
            'full_name'     => 'سائق تفضيلات الاختبار',
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
            'subscription_type' => 'monthly',
        ]);

        // إضافة منطقة ابتدائية للسائق
        $this->driver->zones()->sync([$this->zone1->id]);
    }

    /**
     * Test 1: GET /api/v1/driver/preferences (عرض التفضيلات)
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
     * Test 2: PUT /api/v1/driver/preferences (تحديث شامل ناجح)
     */
    public function test_update_driver_preferences_success(): void
    {
        $payload = [
            'morning_go'        => true,
            'morning_return'    => false,
            'afternoon_go'      => true,
            'afternoon_return'  => true,
            'subscription_type' => 'both',
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
     * Test 3: PUT /api/v1/driver/preferences (فشل عند عدم اختيار أي فترة)
     */
    public function test_update_driver_preferences_validation_fails_without_shift_slots(): void
    {
        $payload = [
            'morning_go'        => false,
            'morning_return'    => false,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'monthly',
            'zones'             => [$this->zone1->id],
        ];

        $response = $this->actingAs($this->driverUser)
            ->putJson('/api/v1/driver/preferences', $payload);

        $response->assertStatus(422);
    }

    /**
     * Test 4: PUT /api/v1/driver/preferences (فشل عند اختيار مناطق تتبع بلديات فرعية مختلفة)
     */
    public function test_update_driver_preferences_fails_when_zones_belong_to_different_sub_municipalities(): void
    {
        $payload = [
            'morning_go'        => true,
            'morning_return'    => true,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'monthly',
            'zones'             => [$this->zone1->id, $this->otherSubZone->id], // بلديات مختلفة!
        ];

        $response = $this->actingAs($this->driverUser)
            ->putJson('/api/v1/driver/preferences', $payload);

        $response->assertStatus(500); // Exception مرفوع في الخدمة
    }

    /**
     * Test 5: POST /api/v1/driver/preferences/zones/add (إضافة منطقة منفردة بنجاح)
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
     * Test 6: POST /api/v1/driver/preferences/zones/add (فشل عند اختيار منطقة ببلدية مختلفة)
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
     * Test 7: POST /api/v1/driver/preferences/zones/remove (إزالة منطقة بنجاح)
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
     * Test 8: GET /api/v1/driver/preferences/defaults (إرجاع الخيارات الافتراضية للنظام)
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
