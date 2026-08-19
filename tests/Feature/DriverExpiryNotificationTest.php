<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverDocument;
use App\Notifications\CustomDatabaseNotification;
use App\Services\Driver\DriverExpiryNotificationService;
use App\Services\Driver\DriverProfileService;
use App\Services\Parent\DriverMatchingService;

class DriverExpiryNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected DriverExpiryNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DriverExpiryNotificationService::class);

        DB::table('roles')->insertOrIgnore([
            ['id' => 4, 'name' => 'DriverRole4', 'display_name' => 'سائق'],
        ]);
    }

    protected function makeDriver(?string $licenseExpiry = null, ?string $email = null): Driver
    {
        $user = User::create([
            'full_name'     => 'سائق اختبار انتهاء الوثائق',
            'email'         => $email ?? ('driver.expiry.' . uniqid() . '@darby.test'),
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 4,
            'is_active'     => 1,
        ]);

        return Driver::create([
            'user_id'        => $user->id,
            'gender'         => 'Male',
            'status'         => 'Approved',
            'national_id'    => (string) rand(100000000000, 999999999999),
            'license_number' => 'LIC' . rand(100000, 999999),
            'license_expiry' => $licenseExpiry,
        ]);
    }

    protected function makeInsuranceDoc(Driver $driver, string $expiry, ?string $status = 'Verified'): DriverDocument
    {
        return DriverDocument::create([
            'driver_id'             => $driver->id,
            'doc_type'              => 'INSURANCE',
            'file_url'              => 'storage/drivers/documents/insurance.jpg',
            'insurance_expiry_date' => $expiry,
            'status'                => $status,
        ]);
    }

    protected function makeLicenseDoc(Driver $driver, ?string $status = 'Verified'): DriverDocument
    {
        return DriverDocument::create([
            'driver_id' => $driver->id,
            'doc_type'  => 'LICENSE',
            'file_url'  => 'storage/drivers/documents/license.jpg',
            'status'    => $status,
        ]);
    }

    /** Test 1: تذكير عند بلوغ نقطة 15 يوم لانتهاء الرخصة */
    public function test_sends_reminder_when_license_reaches_15_day_milestone(): void
    {
        Notification::fake();

        $driver = $this->makeDriver(now()->addDays(15)->format('Y-m-d'));

        $stats = $this->service->run();

        $this->assertEquals(1, $stats['license_reminders']);
        Notification::assertSentTo($driver->user, CustomDatabaseNotification::class);

        $this->assertEquals(15, $driver->fresh()->license_expiry_notified_milestone);
    }

    /** Test 2: عدم تكرار نفس التذكير عند تشغيل الدالة مرتين لنفس اليوم */
    public function test_does_not_duplicate_reminder_for_same_milestone(): void
    {
        Notification::fake();

        $driver = $this->makeDriver(now()->addDays(7)->format('Y-m-d'));

        $this->service->run();
        $this->service->run();

        Notification::assertSentToTimes($driver->user, CustomDatabaseNotification::class, 1);
    }

    /** Test 3: انتهاء الرخصة فعلياً يُعلّم المستند Expired وينبّه السائق + الإدارة */
    public function test_marks_license_document_expired_and_notifies_driver_and_admin(): void
    {
        Notification::fake();

        $driver = $this->makeDriver(now()->subDay()->format('Y-m-d'));
        $licenseDoc = $this->makeLicenseDoc($driver);

        $adminUser = User::create([
            'full_name'     => 'أدمن اختبار',
            'email'         => 'admin.expiry.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);
        $admin = Admin::create(['user_id' => $adminUser->id, 'created_by' => $adminUser->id]);

        $stats = $this->service->run();

        $this->assertEquals(1, $stats['license_expired']);
        $this->assertEquals('Expired', $licenseDoc->fresh()->status);

        Notification::assertSentTo($driver->user, CustomDatabaseNotification::class);
        Notification::assertSentTo($adminUser, CustomDatabaseNotification::class);
    }

    /** Test 4: لا يُعاد إرسال تنبيه الانتهاء مرة أخرى بعد تعليم المستند Expired مسبقاً */
    public function test_does_not_resend_expired_alert_once_already_marked(): void
    {
        Notification::fake();

        $driver = $this->makeDriver(now()->subDays(5)->format('Y-m-d'));
        $this->makeLicenseDoc($driver);

        $this->service->run();
        $stats = $this->service->run();

        $this->assertEquals(0, $stats['license_expired']);
        Notification::assertSentToTimes($driver->user, CustomDatabaseNotification::class, 1);
    }

    /** Test 5: تذكير وانتهاء التأمين (نفس منطق الرخصة لكن على مستوى المستند) */
    public function test_insurance_expiry_reminder_and_expiration_flow(): void
    {
        Notification::fake();

        $driver = $this->makeDriver(now()->addYears(2)->format('Y-m-d'));
        $doc = $this->makeInsuranceDoc($driver, now()->addDays(3)->format('Y-m-d'));

        $stats = $this->service->run();

        $this->assertEquals(1, $stats['insurance_reminders']);
        $this->assertEquals(3, $doc->fresh()->expiry_notified_milestone);

        // الآن ننتهي التأمين فعلياً ونعيد الفحص
        $doc->update(['insurance_expiry_date' => now()->subDay()->format('Y-m-d')]);
        $stats2 = $this->service->run();

        $this->assertEquals(1, $stats2['insurance_expired']);
        $this->assertEquals('Expired', $doc->fresh()->status);
    }

    /** Test 6: تجديد تاريخ انتهاء التأمين يُعيد ضبط عداد التذكيرات ويُلغي علامة الانتهاء */
    public function test_renewing_insurance_resets_milestone_and_unexpires_status(): void
    {
        $driver = $this->makeDriver(now()->addYears(2)->format('Y-m-d'));
        $doc = $this->makeInsuranceDoc($driver, now()->subDay()->format('Y-m-d'), 'Expired');
        $doc->update(['expiry_notified_milestone' => 0]);

        $service = app(DriverProfileService::class);
        $service->updateLegalDocuments($driver->user_id, [
            'insurance_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $fresh = $doc->fresh();
        $this->assertEquals('Pending', $fresh->status);
        $this->assertNull($fresh->expiry_notified_milestone);
    }

    /** Test 7: السائق ذو الوثيقة المنتهية لا يظهر في نتائج مطابقة اشتراكات جديدة */
    public function test_driver_with_expired_document_excluded_from_new_matching(): void
    {
        $activeDriver = $this->makeDriver(now()->addYears(2)->format('Y-m-d'));
        $this->makeLicenseDoc($activeDriver, 'Verified');

        $expiredDriver = $this->makeDriver(now()->addYears(2)->format('Y-m-d'));
        $this->makeLicenseDoc($expiredDriver, 'Expired');

        $matchingService = app(DriverMatchingService::class);
        $results = $matchingService->matchDrivers([], 999999);

        $ids = $results->getCollection()->pluck('id')->toArray();

        $this->assertContains($activeDriver->id, $ids);
        $this->assertNotContains($expiredDriver->id, $ids);
    }
}
