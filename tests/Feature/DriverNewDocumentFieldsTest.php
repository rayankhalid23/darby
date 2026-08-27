<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverDocument;
use App\Services\Driver\DriverExpiryNotificationService;

/**
 * اختبار الحقول الخمسة الجديدة: صورة بيانات الكتيب، صورة الدمغة + تاريخ انتهائها،
 * صورة الفحص الفني + تاريخ انتهائه — عبر دوال الإنشاء/العرض/التعديل (سائق وأدمن).
 */
class DriverNewDocumentFieldsTest extends TestCase
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
            'full_name'     => 'سائق اختبار الحقول الجديدة',
            'email'         => 'driver.newdocs.' . uniqid() . '@darby.test',
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
            'plate_number'    => 'NEW-' . rand(1000, 9999),
            'brand'           => 'Kia',
            'model'           => 'Bongo',
            'year'            => 2021,
            'color'           => 'Blue',
            'type'            => 'Bus',
            'capacity_manual' => 20,
            'has_ac'          => true,
            'status'          => 'Active',
            'is_verified'     => true,
        ]);
    }

    protected function completeProfilePayload(): array
    {
        return [
            'national_id'      => (string) rand(100000000000, 999999999999),
            'license_number'   => 'LIC-' . rand(100000, 999999),
            'license_expiry'   => now()->addYears(2)->format('Y-m-d'),
            'insurance_expiry' => now()->addYear()->format('Y-m-d'),
            'stamp_expiry'                => now()->addYear()->format('Y-m-d'),
            'technical_inspection_expiry' => now()->addYear()->format('Y-m-d'),
            'plate_number'     => 'CP-' . rand(1000, 9999),
            'brand'            => 'Toyota',
            'model'            => 'Hiace',
            'year'             => 2022,
            'color'            => 'White',
            'type'             => 'Van',
            'capacity_manual'  => 14,
            'has_ac'           => true,
            'vehicle_image'    => UploadedFile::fake()->image('vehicle.jpg'),
            'doc_license'      => UploadedFile::fake()->image('license.jpg'),
            'doc_logbook'      => UploadedFile::fake()->image('logbook.jpg'),
            'doc_insurance'    => UploadedFile::fake()->image('insurance.jpg'),
            'doc_booklet_page'         => UploadedFile::fake()->image('booklet.jpg'),
            'doc_stamp'                => UploadedFile::fake()->image('stamp.jpg'),
            'doc_technical_inspection' => UploadedFile::fake()->image('technical.jpg'),
        ];
    }

    /** Test 1: نجاح إكمال الملف الشخصي مع الحقول الخمسة الجديدة، وإنشاء 6 مستندات بالكامل */
    public function test_complete_profile_creates_all_new_documents(): void
    {
        Storage::fake('public');

        $user = User::create([
            'full_name'     => 'سائق اختبار إكمال جديد',
            'email'         => 'driver.cp.newdocs.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 0,
        ]);
        $driver = Driver::create(['user_id' => $user->id, 'gender' => 'Male', 'status' => 'Offline']);

        $payload = $this->completeProfilePayload();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/driver/complete-profile/{$user->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $this->assertEquals(6, $driver->documents()->count());

        $booklet = DriverDocument::where('driver_id', $driver->id)->where('doc_type', 'BOOKLET_PERSONAL_PAGE')->first();
        $this->assertNotNull($booklet);
        $this->assertNull($booklet->stamp_expiry_date);

        $stamp = DriverDocument::where('driver_id', $driver->id)->where('doc_type', 'STAMP')->first();
        $this->assertNotNull($stamp);
        $this->assertEquals($payload['stamp_expiry'], $stamp->stamp_expiry_date);

        $inspection = DriverDocument::where('driver_id', $driver->id)->where('doc_type', 'TECHNICAL_INSPECTION')->first();
        $this->assertNotNull($inspection);
        $this->assertEquals($payload['technical_inspection_expiry'], $inspection->technical_inspection_expiry_date);
    }

    /** Test 2: فشل واضح ومختصر عند غياب أي من الحقول الخمسة الجديدة */
    public function test_complete_profile_fails_clearly_when_new_fields_missing(): void
    {
        Storage::fake('public');

        $payload = $this->completeProfilePayload();
        unset($payload['doc_stamp'], $payload['technical_inspection_expiry']);

        $response = $this->actingAs($this->driverUser)
            ->postJson("/api/v1/driver/complete-profile/{$this->driverUser->id}", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['doc_stamp', 'technical_inspection_expiry']);
        $response->assertJsonPath('errors.doc_stamp.0', 'يرجى إرفاق صورة الدمغة.');
        $response->assertJsonPath('errors.technical_inspection_expiry.0', 'تاريخ انتهاء الفحص الفني مطلوب.');
    }

    /** Test 3: السائق يقدر يجدّد صورة+تاريخ الفحص الفني فقط عبر updateLegalData */
    public function test_driver_can_renew_technical_inspection_document(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'doc_technical_inspection'    => UploadedFile::fake()->image('new-inspection.jpg'),
                'technical_inspection_expiry' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $doc = DriverDocument::where('driver_id', $this->driver->id)->where('doc_type', 'TECHNICAL_INSPECTION')->first();
        $this->assertNotNull($doc);
        $this->assertEquals('Pending', $doc->status);
        $this->assertNotNull($doc->file_url);
    }

    /** Test 4: تعديل تاريخ انتهاء الدمغة فقط دون صورة جديدة يُعيد ضبط عداد التذكيرات ويُلغي "منتهية" */
    public function test_updating_stamp_expiry_only_resets_milestone_and_unexpires(): void
    {
        $doc = DriverDocument::create([
            'driver_id'         => $this->driver->id,
            'doc_type'          => 'STAMP',
            'file_url'          => 'storage/drivers/documents/old-stamp.jpg',
            'stamp_expiry_date' => now()->subDay()->format('Y-m-d'),
            'status'            => 'Expired',
        ]);
        $doc->update(['expiry_notified_milestone' => 0]);

        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'stamp_expiry' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);

        $fresh = $doc->fresh();
        $this->assertEquals('Pending', $fresh->status);
        $this->assertNull($fresh->expiry_notified_milestone);
    }

    /** Test 5: GET /profile يُرجع تاريخي انتهاء الدمغة والفحص الفني ضمن المستندات */
    public function test_show_profile_returns_new_expiry_fields(): void
    {
        DriverDocument::create([
            'driver_id'                          => $this->driver->id,
            'doc_type'                            => 'TECHNICAL_INSPECTION',
            'file_url'                            => 'storage/drivers/documents/inspection.jpg',
            'technical_inspection_expiry_date'    => '2027-05-01',
            'status'                               => 'Verified',
        ]);

        $response = $this->actingAs($this->driverUser)->getJson('/api/v1/driver/profile');

        $response->assertStatus(200);
        $docs = collect($response->json('data.documents'));
        $inspectionDoc = $docs->firstWhere('doc_type', 'TECHNICAL_INSPECTION');
        $this->assertNotNull($inspectionDoc);
        $this->assertEquals('2027-05-01', $inspectionDoc['technical_inspection_expiry_date']);
    }

    /** Test 6: الأدمن يقدر يعدّل صورة+تاريخ الدمغة للسائق عبر PUT /api/admin/drivers/{id} */
    public function test_admin_can_update_stamp_document(): void
    {
        Storage::fake('public');

        $adminUser = User::create([
            'full_name'     => 'أدمن اختبار الدمغة',
            'email'         => 'admin.stamp.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        Admin::create(['user_id' => $adminUser->id, 'created_by' => $adminUser->id]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", [
                'doc_stamp'    => UploadedFile::fake()->image('admin-stamp.jpg'),
                'stamp_expiry' => now()->addYear()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $doc = DriverDocument::where('driver_id', $this->driver->id)->where('doc_type', 'STAMP')->first();
        $this->assertNotNull($doc);
        $this->assertEquals(now()->addYear()->format('Y-m-d'), $doc->stamp_expiry_date);

        $response->assertJsonPath('data.documents.0.stamp_expiry_date', now()->addYear()->format('Y-m-d'));
    }

    /** Test 6-ب: الأدمن يقدر يرفع "صورة بيانات الكتيب" فقط (بلا تاريخ انتهاء) عبر نفس المسار */
    public function test_admin_can_update_booklet_page_without_expiry(): void
    {
        Storage::fake('public');

        $adminUser = User::create([
            'full_name'     => 'أدمن اختبار الكتيب',
            'email'         => 'admin.booklet.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        Admin::create(['user_id' => $adminUser->id, 'created_by' => $adminUser->id]);

        $response = $this->actingAs($adminUser, 'sanctum')
            ->putJson("/api/admin/drivers/{$this->driver->id}", [
                'doc_booklet_page' => UploadedFile::fake()->image('admin-booklet.jpg'),
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        $doc = DriverDocument::where('driver_id', $this->driver->id)->where('doc_type', 'BOOKLET_PERSONAL_PAGE')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->file_url);
        $this->assertEquals('Pending', $doc->status);
    }

    /** Test 7: خدمة فحص الانتهاء تراقب الدمغة والفحص الفني أيضاً (تذكير + انتهاء + استثناء من المطابقة) */
    public function test_expiry_service_covers_stamp_and_technical_inspection(): void
    {
        $stampDoc = DriverDocument::create([
            'driver_id'         => $this->driver->id,
            'doc_type'          => 'STAMP',
            'file_url'          => 'storage/drivers/documents/stamp.jpg',
            'stamp_expiry_date' => now()->addDays(7)->format('Y-m-d'),
            'status'            => 'Verified',
        ]);

        $inspectionDoc = DriverDocument::create([
            'driver_id'                        => $this->driver->id,
            'doc_type'                          => 'TECHNICAL_INSPECTION',
            'file_url'                           => 'storage/drivers/documents/inspection.jpg',
            'technical_inspection_expiry_date'  => now()->subDay()->format('Y-m-d'),
            'status'                             => 'Verified',
        ]);

        $service = app(DriverExpiryNotificationService::class);
        $stats = $service->run();

        $this->assertEquals(1, $stats['stamp_reminders']);
        $this->assertEquals(7, $stampDoc->fresh()->expiry_notified_milestone);

        $this->assertEquals(1, $stats['technical_inspection_expired']);
        $this->assertEquals('Expired', $inspectionDoc->fresh()->status);

        // السائق الآن يحمل وثيقة فحص فني منتهية — يجب استبعاده من مطابقة اشتراكات جديدة
        $matchingService = app(\App\Services\Parent\DriverMatchingService::class);
        $results = $matchingService->matchDrivers([], 999999);
        $this->assertNotContains($this->driver->id, $results->getCollection()->pluck('id')->toArray());
    }
}
