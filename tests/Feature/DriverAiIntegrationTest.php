<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Client\ConnectionException;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\Shared\Complaint;
use App\Notifications\CustomDatabaseNotification;

/**
 * اختبار تكاملي لربط ai_service مع Laravel عبر App\Services\DriverAiService
 * و App\Observers\ComplaintObserver (يعمل تلقائياً عند Complaint::create()).
 *
 * يغطي الحالات الأربع (suspend_driver, notify_driver, log_only, no_action)
 * بالإضافة لسيناريو تعطّل خدمة الذكاء الاصطناعي (Fail-Safe).
 */
class DriverAiIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected const AI_URL = 'http://127.0.0.1:8000/api/v1/predict';

    protected User $adminUser;
    protected User $driverUser;
    protected Driver $driver;
    protected User $parentUser;
    protected ParentModel $parent;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->adminUser = User::create([
            'full_name'    => 'مدير اختبار الذكاء الاصطناعي',
            'email'        => 'admin.ai.' . uniqid() . '@darby.test',
            'phone_number' => '090' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 1,
            'is_active'    => 1,
        ]);
        // موديل Admin بلا أعمدة created_at/updated_at في قاعدة البيانات، لذا نُدرج مباشرة
        DB::table('admins')->insert([
            'user_id'    => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $this->driverUser = User::create([
            'full_name'    => 'سائق اختبار الذكاء الاصطناعي',
            'email'        => 'driver.ai.' . uniqid() . '@darby.test',
            'phone_number' => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
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

        $this->parentUser = User::create([
            'full_name'    => 'ولي أمر اختبار الذكاء الاصطناعي',
            'email'        => 'parent.ai.' . uniqid() . '@darby.test',
            'phone_number' => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'      => 3,
            'is_active'    => 1,
        ]);
        $this->parent = ParentModel::create(['user_id' => $this->parentUser->id, 'is_trusted' => 1]);
    }

    protected function fakeAiResponse(array $overrides = []): void
    {
        Http::fake([
            self::AI_URL => Http::response(array_merge([
                'label'          => 'normal',
                'confidence'     => 0.5,
                'action'         => 'no_action',
                'severity'       => 0,
                'message_ar'     => 'لا يوجد إجراء مطلوب.',
                'low_confidence' => false,
                'scores'         => ['normal' => 0.5],
                'cleaned_text'   => 'نص الشكوى',
            ], $overrides), 200),
        ]);
    }

    protected function createComplaint(string $description = 'وصف الشكوى الافتراضي للاختبار.'): Complaint
    {
        return Complaint::create([
            'submitted_by' => $this->parent->id,
            'against_type' => 'DRIVER',
            'against_id'   => $this->driver->id,
            'driver_id'    => $this->driver->id,
            'description'  => $description,
            'status'       => 'pending',
        ]);
    }

    // =========================================================
    // 1) suspend_driver
    // =========================================================
    public function test_suspend_driver_action_suspends_driver_and_notifies_admins(): void
    {
        $this->fakeAiResponse([
            'label' => 'deactivate', 'confidence' => 0.93, 'action' => 'suspend_driver',
            'severity' => 3, 'message_ar' => 'مخالفة جسيمة: إيقاف السائق وتحويل الحالة للإدارة.',
        ]);
        Notification::fake();

        $complaint = $this->createComplaint('السائق شتم الطفل واعتدى عليه لفظياً بشكل صارخ.');

        Http::assertSent(fn ($request) => $request->url() === self::AI_URL
            && $request['driver_id'] === $this->driver->id
            && $request['complaint_text'] === $complaint->description);

        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'status' => 'Suspended']);

        $this->assertDatabaseHas('complaints', [
            'id'             => $complaint->id,
            'ai_action'      => 'suspend_driver',
            'ai_severity'    => 3,
            'status'         => 'completed',
            'action_taken'   => 'suspension',
            'action_details' => 'تجاوز سلوكي خطير بناءً على تحليل الشكوى.',
        ]);
        $this->assertNotNull($complaint->fresh()->resolved_at);
        $this->assertEquals(0.93, (float) $complaint->fresh()->ai_confidence);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->adminUser->id]);
        // السائق نفسه يُخطَر أيضاً بإيقاف حسابه (كان مفقوداً سابقاً وتم إصلاحه)
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->driverUser->id]);
    }

    // =========================================================
    // 2) notify_driver
    // =========================================================
    public function test_notify_driver_action_alerts_driver_without_suspension(): void
    {
        $this->fakeAiResponse([
            'label' => 'driver_alert', 'confidence' => 0.7, 'action' => 'notify_driver',
            'severity' => 2, 'message_ar' => 'إرسال تنبيه للسائق ومتابعة السلوك.',
        ]);
        Notification::fake();

        $complaint = $this->createComplaint('السائق كان يستخدم الهاتف أثناء القيادة.');

        // لا إيقاف للسائق
        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'status' => 'Approved']);

        // المخالفة أُدرجت في سجل الشكوى للتدقيق
        $this->assertDatabaseHas('complaints', [
            'id'           => $complaint->id,
            'ai_action'    => 'notify_driver',
            'ai_severity'  => 2,
            'action_taken' => 'warning',
        ]);
        // الشكوى تبقى معلّقة لمراجعة الأدمن، لم تُغلق تلقائياً
        $this->assertEquals('pending', $complaint->fresh()->status);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->driverUser->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->adminUser->id]);
    }

    // =========================================================
    // 3) log_only
    // =========================================================
    public function test_log_only_action_flags_complaint_without_touching_driver_or_notifications(): void
    {
        $this->fakeAiResponse([
            'label' => 'ignore', 'confidence' => 0.55, 'action' => 'log_only',
            'severity' => 1, 'message_ar' => 'شكوى بسيطة، تُسجَّل في السجل بدون إجراء.',
        ]);
        Notification::fake();

        $complaint = $this->createComplaint('تأخر السائق بضع دقائق عن الموعد المعتاد.');

        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'status' => 'Approved']);
        $this->assertDatabaseHas('complaints', [
            'id'           => $complaint->id,
            'ai_action'    => 'log_only',
            'ai_severity'  => 1,
            'status'       => 'pending',
            'action_taken' => 'none',
        ]);

        Notification::assertNothingSent();
    }

    // =========================================================
    // 4) no_action
    // =========================================================
    public function test_no_action_leaves_driver_and_complaint_untouched(): void
    {
        $this->fakeAiResponse([
            'label' => 'normal', 'confidence' => 0.98, 'action' => 'no_action',
            'severity' => 0, 'message_ar' => 'لا يوجد إجراء مطلوب، ملاحظة عادية.',
        ]);
        Notification::fake();

        $complaint = $this->createComplaint('كل شيء كان ممتازاً، شكراً للسائق.');

        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'status' => 'Approved']);
        $this->assertDatabaseHas('complaints', [
            'id'           => $complaint->id,
            'ai_action'    => 'no_action',
            'ai_severity'  => 0,
            'status'       => 'pending',
            'action_taken' => 'none',
        ]);

        Notification::assertNothingSent();
    }

    // =========================================================
    // 5) Fail-Safe: خدمة الذكاء الاصطناعي غير متاحة
    // =========================================================
    public function test_ai_service_unreachable_falls_back_to_no_action_and_does_not_break_complaint_creation(): void
    {
        Http::fake([
            self::AI_URL => function () {
                throw new ConnectionException('Connection refused - AI service is down');
            },
        ]);
        Notification::fake();

        $complaint = $this->createComplaint('شكوى عادية أثناء تعطّل خدمة الذكاء الاصطناعي.');

        // الشكوى أُنشئت بنجاح رغم تعطّل الخدمة (لا انهيار في تطبيق Laravel)
        $this->assertDatabaseHas('complaints', [
            'id'        => $complaint->id,
            'ai_action' => 'no_action',
        ]);
        $this->assertNull($complaint->fresh()->ai_confidence);
        $this->assertDatabaseHas('drivers', ['id' => $this->driver->id, 'status' => 'Approved']);

        Notification::assertNothingSent();
    }

    public function test_ai_service_timeout_also_falls_back_to_no_action(): void
    {
        Http::fake([
            self::AI_URL => Http::response(null, 500),
        ]);

        $complaint = $this->createComplaint('شكوى أثناء استجابة خطأ من الخادم.');

        $this->assertEquals('no_action', $complaint->fresh()->ai_action);
    }

    // =========================================================
    // 6) مراجعات وتعليقات السائقين (DriverReview Analysis)
    // =========================================================
    public function test_driver_review_creation_analyzes_comment_and_flags_high_severity_to_admins(): void
    {
        $this->fakeAiResponse([
            'label'          => 'driver_alert',
            'confidence'     => 0.88,
            'action'         => 'notify_driver',
            'severity'       => 2,
            'message_ar'     => 'إرسال تنبيه للسائق ومتابعة السلوك.',
            'low_confidence' => false,
        ]);
        Notification::fake();

        $review = \App\Models\Shared\DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 2,
            'comment'   => 'السائق كان مسرعاً ولم يلتزم بالسرعة المحددة.',
            'status'    => 'active',
        ]);

        $this->assertDatabaseHas('driver_reviews', [
            'id'          => $review->id,
            'ai_action'   => 'notify_driver',
            'ai_severity' => 2,
        ]);

        // إشعار الإدارة بمراجعة مثيرة للقلق (severity >= 2)
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->adminUser->id,
            'type'          => 'App\\Notifications\\SystemNotification',
        ]);
    }

    public function test_driver_review_comment_update_reanalyzes_comment(): void
    {
        Http::fake([
            self::AI_URL => Http::sequence()
                ->push([
                    'label'          => 'normal',
                    'confidence'     => 0.95,
                    'action'         => 'no_action',
                    'severity'       => 0,
                    'message_ar'     => 'لا يوجد إجراء مطلوب، ملاحظة عادية.',
                    'low_confidence' => false,
                ], 200)
                ->push([
                    'label'          => 'deactivate',
                    'confidence'     => 0.96,
                    'action'         => 'suspend_driver',
                    'severity'       => 3,
                    'message_ar'     => 'مخالفة جسيمة: إيقاف السائق وتحويل الحالة للإدارة.',
                    'low_confidence' => false,
                ], 200),
        ]);

        $review = \App\Models\Shared\DriverReview::create([
            'parent_id' => $this->parentUser->id,
            'driver_id' => $this->driver->id,
            'rating'    => 5,
            'comment'   => 'سائق ممتاز شكراً جزيلاً.',
            'status'    => 'active',
        ]);

        $this->assertEquals('no_action', $review->fresh()->ai_action);

        $review->update([
            'comment' => 'تعديل: السائق شتم الطفل وطرده من الحافلة.',
        ]);

        $this->assertEquals('suspend_driver', $review->fresh()->ai_action);
        $this->assertEquals(3, $review->fresh()->ai_severity);
    }

    public function test_ai_classifier_sends_api_key_header_when_configured(): void
    {
        config(['services.driver_ai.api_key' => 'secret-test-key-123']);
        $this->fakeAiResponse();

        $complaint = $this->createComplaint('شكوى للتحقق من التوثيق.');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-API-Key', 'secret-test-key-123');
        });
    }
}
