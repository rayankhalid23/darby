<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use App\Models\Shared\Municipality;

class DriverAllUpdateEndpointsPartialUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected Zone $zone1;
    protected Zone $zone2;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->driverUser = User::create([
            'full_name'         => 'سائق اختبار التحديث الجزئي',
            'email'             => 'driver.partial.' . uniqid() . '@darby.test',
            'phone_number'      => '091' . rand(1000000, 9999999),
            'alternative_phone' => '0921112233',
            'password_hash'     => bcrypt('password123'),
            'role_id'           => 4,
            'is_active'         => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'           => $this->driverUser->id,
            'gender'            => 'male',
            'status'            => 'Approved',
            'national_id'       => (string) rand(100000000000, 999999999999),
            'license_number'    => 'LIC' . rand(100000, 999999),
            'license_expiry'    => now()->addYears(2)->format('Y-m-d'),
            'morning_go'        => true,
            'morning_return'    => true,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'subscription_type' => 'both',
            'school_stages'     => ['primary'],
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => '5-' . rand(10000, 99999),
            'brand'           => 'Hyundai',
            'model'           => 'H1',
            'year'            => 2021,
            'color'           => 'Silver',
            'type'            => 'Van',
            'capacity_manual' => 12,
            'has_ac'          => true,
            'status'          => 'Active',
            'is_verified'     => 1,
        ]);

        DriverDocument::create([
            'driver_id'             => $this->driver->id,
            'vehicle_id'            => $this->vehicle->id,
            'doc_type'              => 'INSURANCE',
            'file_url'              => 'storage/drivers/documents/ins.jpg',
            'insurance_expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'status'                => 'Verified',
            'uploaded_at'           => now(),
        ]);

        $municipality = Municipality::firstOrCreate(
            ['name' => 'بلدية طرابلس الكبرى'],
            ['code' => 'TRP', 'is_active' => 1]
        );
        $subMunicipality = SubMunicipality::firstOrCreate(
            ['name' => 'بلدية تاجوراء الفرعية', 'municipality_id' => $municipality->id],
            ['code' => 'TAJ', 'is_active' => 1]
        );
        $this->zone1 = Zone::firstOrCreate(
            ['name' => 'منطقة تاجوراء الوسط', 'sub_municipality_id' => $subMunicipality->id],
            ['code' => 'Z1', 'is_active' => 1]
        );
        $this->zone2 = Zone::firstOrCreate(
            ['name' => 'منطقة تاجوراء الشرقية', 'sub_municipality_id' => $subMunicipality->id],
            ['code' => 'Z2', 'is_active' => 1]
        );
    }

    /**
     * 1. اختبار التعديل الجزئي للملف الشخصي (POST /api/v1/driver/profile)
     */
    public function test_driver_profile_partial_updates(): void
    {
        Storage::fake('public');

        // أ) تعديل رقم الهاتف البديل فقط
        $res1 = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile', [
                'alternative_phone' => '0929998877',
            ]);
        $res1->assertStatus(200);
        $res1->assertJsonPath('success', true);
        $this->driverUser->refresh();
        $this->assertEquals('0929998877', $this->driverUser->alternative_phone);

        // ب) تعديل الصورة الشخصية فقط بصيغة WebP
        $res2 = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile', [
                'avatar' => UploadedFile::fake()->image('driver_avatar.webp'),
            ]);
        $res2->assertStatus(200);
        $this->driverUser->refresh();
        $this->assertNotEmpty($this->driverUser->avatar_url);
    }

    /**
     * 2. اختبار التعديل الجزئي للوثائق القانونية (POST /api/v1/driver/profile/legal-data)
     */
    public function test_driver_legal_data_partial_updates(): void
    {
        Storage::fake('public');

        $newExpiry = now()->addYears(2)->format('Y-m-d');

        // تعديل تاريخ انتهاء التأمين فقط دون أي حقل آخر
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'insurance_expiry' => $newExpiry,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $doc = DriverDocument::where('driver_id', $this->driver->id)
            ->where('doc_type', 'INSURANCE')
            ->first();
        $this->assertEquals($newExpiry, $doc->insurance_expiry_date);
    }

    /**
     * 3. اختبار التعديل الجزئي لبيانات المركبة (POST /api/v1/driver/profile/vehicle/{id})
     */
    public function test_driver_vehicle_partial_updates(): void
    {
        Storage::fake('public');

        // أ) تعديل لون المركبة فقط
        $res1 = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/profile/vehicle/{$this->vehicle->id}", [
                'color' => 'Black',
            ]);
        $res1->assertStatus(200);
        $res1->assertJsonPath('status', true);

        // التحقق من تسجيل التعديل في driver_profile_changes
        $this->assertDatabaseHas('driver_profile_changes', [
            'driver_id' => $this->driver->id,
            'status'    => 'Pending',
        ]);

        // ب) تعديل صورة المركبة فقط باسم حقل الفرونت (vehicle_photo)
        $res2 = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/profile/vehicle/{$this->vehicle->id}", [
                'vehicle_photo' => UploadedFile::fake()->image('new_van.webp'),
            ]);
        $res2->assertStatus(200);
        $res2->assertJsonPath('status', true);
    }

    /**
     * 4. اختبار التعديل الجزئي للتفضيلات والمناطق (POST /api/v1/driver/preferences)
     */
    public function test_driver_preferences_partial_updates(): void
    {
        // أ) تعديل نوع الاشتراك فقط
        $res1 = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/preferences', [
                'subscription_type' => 'multi_day',
            ]);
        $res1->assertStatus(200);
        $res1->assertJsonPath('status', true);
        $this->driver->refresh();
        $this->assertEquals('multi_day', $this->driver->subscription_type);
        // التأكد من عدم تغيير الفترات السابقة
        $this->assertTrue((bool) $this->driver->morning_go);

        // ب) تعديل المناطق فقط
        $res2 = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/preferences', [
                'zones' => [$this->zone1->id, $this->zone2->id],
            ]);
        $res2->assertStatus(200);
        $this->driver->refresh();
        $this->assertEquals(2, $this->driver->zones()->count());
    }
}
