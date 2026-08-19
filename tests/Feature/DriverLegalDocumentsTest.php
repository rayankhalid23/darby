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

class DriverLegalDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق اختبار الوثائق القانونية',
            'email'         => 'driver.legal.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);

        $this->driver = Driver::create([
            'user_id'        => $this->driverUser->id,
            'gender'         => 'Male',
            'status'         => 'Approved',
            'national_id'    => (string) rand(100000000000, 999999999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => now()->addYears(2)->format('Y-m-d'),
        ]);

        $this->vehicle = Vehicle::create([
            'driver_id'       => $this->driver->id,
            'plate_number'    => 'XYZ-' . rand(1000, 9999),
            'brand'           => 'Kia',
            'model'           => 'Bongo',
            'year'            => 2021,
            'color'           => 'Blue',
            'type'            => 'Bus',
            'capacity_manual' => 20,
            'has_ac'          => true,
            'status'          => 'Active',
        ]);

        DriverDocument::create([
            'driver_id'             => $this->driver->id,
            'vehicle_id'            => $this->vehicle->id,
            'doc_type'              => 'INSURANCE',
            'file_url'              => 'storage/drivers/documents/old-insurance.jpg',
            'insurance_expiry_date' => now()->addMonths(2)->format('Y-m-d'),
            'status'                => 'Verified',
        ]);
    }

    /** Test 1: تحديث تاريخ انتهاء التأمين فقط دون رفع صورة جديدة */
    public function test_update_insurance_expiry_only_without_new_file(): void
    {
        $newExpiry = now()->addYear()->format('Y-m-d');

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'insurance_expiry' => $newExpiry,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $this->assertDatabaseHas('driver_documents', [
            'driver_id'             => $this->driver->id,
            'doc_type'              => 'INSURANCE',
            'insurance_expiry_date' => $newExpiry,
        ]);
    }

    /** Test 2: رفع صورة تأمين جديدة مع تاريخ انتهاء جديد معاً */
    public function test_update_insurance_document_with_new_file_and_expiry(): void
    {
        Storage::fake('public');

        $newExpiry = now()->addMonths(8)->format('Y-m-d');

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'doc_insurance'     => UploadedFile::fake()->image('new-insurance.jpg'),
                'insurance_expiry'  => $newExpiry,
            ]);

        $response->assertStatus(200);

        $doc = DriverDocument::where('driver_id', $this->driver->id)
            ->where('doc_type', 'INSURANCE')
            ->first();

        $this->assertEquals($newExpiry, $doc->insurance_expiry_date);
        $this->assertStringContainsString('drivers/documents/', $doc->file_url);
    }

    /** Test 3: فشل عند تاريخ انتهاء تأمين في الماضي */
    public function test_update_insurance_expiry_fails_when_in_past(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'insurance_expiry' => now()->subDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['insurance_expiry']);
    }

    /** Test 4: حقل doc_vehicle_registration لم يعد مقبولاً ولا يُخزَّن حتى لو أُرسل */
    public function test_update_legal_data_ignores_stray_vehicle_registration_field(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'doc_vehicle_registration' => UploadedFile::fake()->image('old-field.jpg'),
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('driver_documents', [
            'driver_id' => $this->driver->id,
            'doc_type'  => 'VEHICLE_REGISTRATION',
        ]);
    }

    /** Test 5: عرض الوثائق القانونية يتضمن تاريخ انتهاء التأمين */
    public function test_show_legal_data_includes_insurance_expiry_date(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/profile/legal-data');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $documents = $response->json('data.uploaded_files');
        $insuranceDoc = collect($documents)->firstWhere('doc_type', 'INSURANCE');

        $this->assertNotNull($insuranceDoc);
        $this->assertArrayHasKey('insurance_expiry_date', $insuranceDoc);
        $this->assertNotNull($insuranceDoc['insurance_expiry_date']);
    }

    /** Test 6: عرض الملف الشخصي الكامل يتضمن تاريخ انتهاء التأمين ضمن المستندات */
    public function test_profile_show_includes_insurance_expiry_date_in_documents(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/profile');

        $response->assertStatus(200);

        $documents = $response->json('data.documents');
        $insuranceDoc = collect($documents)->firstWhere('doc_type', 'INSURANCE');

        $this->assertNotNull($insuranceDoc);
        $this->assertArrayHasKey('insurance_expiry_date', $insuranceDoc);
    }
}
