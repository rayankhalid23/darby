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

class DriverLegalDocumentsAndProfileChangeLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'SuperAdmin', 'display_name' => 'مدير النظام'],
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);

        $this->adminUser = User::create([
            'full_name'     => 'مدير النظام تجريبي',
            'email'         => 'admin.test.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        \App\Models\Admin\Admin::create([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $this->driverUser = User::create([
            'full_name'     => 'سائق تجريبي معتمد',
            'email'         => 'driver.active.' . uniqid() . '@darby.test',
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
            'plate_number'    => '5-' . rand(10000, 99999),
            'brand'           => 'Toyota',
            'model'           => 'Hiace',
            'year'            => 2022,
            'color'           => 'White',
            'type'            => 'Van',
            'capacity_manual' => 14,
            'has_ac'          => true,
            'status'          => 'Active',
            'is_verified'     => 1,
        ]);

        DriverDocument::create([
            'driver_id'             => $this->driver->id,
            'vehicle_id'            => $this->vehicle->id,
            'doc_type'              => 'LICENSE',
            'file_url'              => 'storage/drivers/documents/old_license.jpg',
            'status'                => 'Verified',
            'uploaded_at'           => now(),
        ]);

        DriverDocument::create([
            'driver_id'             => $this->driver->id,
            'vehicle_id'            => $this->vehicle->id,
            'doc_type'              => 'INSURANCE',
            'file_url'              => 'storage/drivers/documents/old_insurance.jpg',
            'insurance_expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'status'                => 'Verified',
            'uploaded_at'           => now(),
        ]);
    }

    /**
     * 1. اختبار دالة عرض الوثائق والتأكد من إرجاع روابط الصور الكاملة وتوافقها مع الفرونت
     */
    public function test_show_legal_data_returns_full_urls_and_frontend_mappings(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/v1/driver/profile/legal-data');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.national_id', $this->driver->national_id);
        $response->assertJsonPath('data.license_number', $this->driver->license_number);

        // التأكد من روابط الصور
        $data = $response->json('data');
        $this->assertNotEmpty($data['uploaded_files']);
        $this->assertNotEmpty($data['documents_map']);
        $this->assertStringContainsString('http', $data['doc_license_url']);
        $this->assertStringContainsString('http', $data['doc_insurance_url']);
    }

    /**
     * 2. اختبار دالة تعديل الوثائق مع إرسال بيانات جزئية (Partial Update) وصور بصيغ متعددة
     */
    public function test_update_legal_data_partial_update_with_new_files_and_aliases(): void
    {
        Storage::fake('public');

        $newInsuranceExpiry = now()->addYears(2)->format('Y-m-d');

        // إرسال تعديل جزئي فقط لوثيقة التأمين مع استخدام اسم حقل الفرونت (insurance_photo)
        $response = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'insurance_photo'  => UploadedFile::fake()->create('new_insurance.pdf', 300, 'application/pdf'),
                'insurance_expiry' => $newInsuranceExpiry,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);

        // التحقق من تحديث حالة الوثيقة إلى Pending للمراجعة
        $this->assertDatabaseHas('driver_documents', [
            'driver_id'             => $this->driver->id,
            'doc_type'              => 'INSURANCE',
            'insurance_expiry_date' => $newInsuranceExpiry,
            'status'                => 'Pending',
        ]);

        // التحقق من إنشاء سجل تعديل معلق في driver_profile_changes
        $this->assertDatabaseHas('driver_profile_changes', [
            'driver_id' => $this->driver->id,
            'status'    => 'Pending',
        ]);
    }

    /**
     * 3. اختبار دورة مراجعة الأدمن للتعديلات المعلقة والموافقة عليها وتطبيقها فوراً
     */
    public function test_admin_reviews_and_approves_driver_profile_change_successfully(): void
    {
        Storage::fake('public');

        $newLicenseNumber = 'DL-999888';
        $newLicenseExpiry = now()->addYears(3)->format('Y-m-d');

        // السائق يرسل طلب تعديل رخصة القيادة
        $updateRes = $this->actingAs($this->driverUser)
            ->postJson('/api/v1/driver/profile/legal-data', [
                'license_number' => $newLicenseNumber,
                'license_expiry' => $newLicenseExpiry,
                'doc_license'    => UploadedFile::fake()->image('updated_license.webp'),
            ]);
        $updateRes->assertStatus(200);

        // جلب طلب التعديل المعلق
        $change = DB::table('driver_profile_changes')
            ->where('driver_id', $this->driver->id)
            ->where('status', 'Pending')
            ->latest('id')
            ->first();
        $this->assertNotNull($change);

        // الأدمن يعرض قائمة التعديلات المعلقة
        $listRes = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/drivers/pending-changes');
        $listRes->assertStatus(200);
        $listRes->assertJsonPath('status', true);

        // الأدمن يعرض تفاصيل طلب التعديل المحدد للمقارنة
        $showRes = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/drivers/pending-changes/{$change->id}");
        $showRes->assertStatus(200);
        $showRes->assertJsonPath('status', true);
        $showRes->assertJsonPath('data.driver_id', $this->driver->id);

        // الأدمن يوافق على التعديلات
        $reviewRes = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/pending-changes/{$change->id}/review", [
                'decision' => 'Approved',
            ]);

        $reviewRes->assertStatus(200);
        $reviewRes->assertJsonPath('status', true);

        // التحقق من تحديث بيانات السائق المفعّل
        $this->driver->refresh();
        $this->assertEquals($newLicenseNumber, $this->driver->license_number);
        $this->assertEquals($newLicenseExpiry, $this->driver->license_expiry->format('Y-m-d'));

        // التحقق من اعتماد الوثيقة المحدثة في driver_documents
        $this->assertDatabaseHas('driver_documents', [
            'driver_id' => $this->driver->id,
            'doc_type'  => 'LICENSE',
            'status'    => 'Verified',
        ]);

        // التحقق من تحديث حالة طلب التعديل
        $this->assertDatabaseHas('driver_profile_changes', [
            'id'     => $change->id,
            'status' => 'Approved',
        ]);
    }

    /**
     * 4. اختبار رفض الأدمن لطلب التعديل مع سبب الرفض
     */
    public function test_admin_rejects_driver_profile_change_with_reason(): void
    {
        $changeId = DB::table('driver_profile_changes')->insertGetId([
            'driver_id'  => $this->driver->id,
            'old_values' => json_encode(['license_number' => 'DL-112233']),
            'new_values' => json_encode(['license_number' => 'DL-INVALID']),
            'status'     => 'Pending',
            'created_at' => now(),
        ]);

        $rejectionReason = 'صورة الرخصة غير واضحة يرجى إعادة التصوير.';

        $reviewRes = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/drivers/pending-changes/{$changeId}/review", [
                'decision'         => 'Rejected',
                'rejection_reason' => $rejectionReason,
            ]);

        $reviewRes->assertStatus(200);
        $reviewRes->assertJsonPath('status', true);

        $this->assertDatabaseHas('driver_profile_changes', [
            'id'               => $changeId,
            'status'           => 'Rejected',
            'rejection_reason' => $rejectionReason,
        ]);
    }
}
