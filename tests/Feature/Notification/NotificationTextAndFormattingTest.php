<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\NotificationFormatter;
use Illuminate\Notifications\DatabaseNotification;

class NotificationTextAndFormattingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'display_name' => 'مدير'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->user = User::create([
            'full_name'     => 'مستخدم اختبار نصوص الإشعارات',
            'email'         => 'notif.text.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
    }

    public function test_all_notification_types_generate_non_empty_arabic_title_and_message(): void
    {
        $typesToTest = [
            // رحلات وتتبع
            NotificationFormatter::TYPE_TRIP_STARTED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_DRIVER_ARRIVED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_CHILD_PICKED_UP => ['child_name' => 'سند', 'trip_id' => '101'],
            NotificationFormatter::TYPE_CHILD_DROPPED_OFF => ['child_name' => 'سند', 'trip_id' => '101'],
            NotificationFormatter::TYPE_STUDENT_ABSENT => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_CHILD_ABSENT => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_CHILD_SKIPPED => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_CHILD_SKIP => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_CHILD_DROPOFF_FAILED => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_CHILD_DIRECT_PARENT_HANDLING => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_MANUAL_PICKUP_CONFIRMED => ['child_name' => 'سند'],
            NotificationFormatter::TYPE_TRIP_COMPLETED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_TRIP_CANCELLED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_TRIP_READY => ['trip_id' => '101'],
            NotificationFormatter::TYPE_TRIP_UPCOMING => ['trip_id' => '101'],
            NotificationFormatter::TYPE_TRIP_SUSPENDED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_DRIVER_ABSENCE => [],

            // اشتراكات وعقود
            NotificationFormatter::TYPE_NEW_SUBSCRIPTION_REQUEST => ['request_id' => '201'],
            NotificationFormatter::TYPE_REQUEST_ACCEPTED => ['request_id' => '201'],
            NotificationFormatter::TYPE_REQUEST_REJECTED => ['request_id' => '201'],
            NotificationFormatter::TYPE_SUBSCRIPTION_APPROVED => ['request_id' => '201'],
            NotificationFormatter::TYPE_SUBSCRIPTION_REJECTED => ['request_id' => '201'],
            NotificationFormatter::TYPE_SUBSCRIPTION_PAYMENT_REQ => ['amount' => 350.00],
            NotificationFormatter::TYPE_CONTRACT_CREATED => ['contract_number' => 'CNT-909'],
            NotificationFormatter::TYPE_CONTRACT_SIGNED => ['contract_number' => 'CNT-909'],
            NotificationFormatter::TYPE_CONTRACT_APPROVED => ['contract_number' => 'CNT-909'],
            NotificationFormatter::TYPE_CONTRACT_REJECTED => ['contract_number' => 'CNT-909'],
            NotificationFormatter::TYPE_CANCELLATION_PARENT => ['request_id' => '201'],
            NotificationFormatter::TYPE_CANCELLATION_DRIVER => ['request_id' => '201', 'parent_name' => 'محمود'],
            NotificationFormatter::TYPE_SUBSCRIPTION_RENEWED => [],

            // تغيير مواقع وتأكيد يدوي
            NotificationFormatter::TYPE_LOCATION_CHANGE_REQUESTED => ['child_name' => 'مروة'],
            NotificationFormatter::TYPE_LOCATION_CHANGE_APPROVED => ['child_name' => 'مروة'],
            NotificationFormatter::TYPE_LOCATION_CHANGE_REJECTED => ['child_name' => 'مروة'],
            NotificationFormatter::TYPE_TRIP_MANUAL_CONFIRMATION_REQUEST => ['child_name' => 'أيمن'],
            NotificationFormatter::TYPE_TRIP_MANUAL_CONFIRMATION_CONFIRMED => ['child_name' => 'أيمن'],
            NotificationFormatter::TYPE_TRIP_MANUAL_CONFIRMATION_DENIED => ['child_name' => 'أيمن'],

            // المالية والمحفظة (د.ل)
            NotificationFormatter::TYPE_RECHARGE_APPROVED => ['amount' => 200.00],
            NotificationFormatter::TYPE_RECHARGE_REJECTED => ['amount' => 200.00],
            NotificationFormatter::TYPE_RECHARGE_COMPLETED => ['amount' => 200.00],
            NotificationFormatter::TYPE_WITHDRAWAL_APPROVED => ['amount' => 450.00],
            NotificationFormatter::TYPE_WITHDRAWAL_REJECTED => ['amount' => 450.00],
            NotificationFormatter::TYPE_INVOICE_GENERATED => ['invoice_number' => 'INV-001', 'amount' => 540.00],
            NotificationFormatter::TYPE_SETTLEMENT_PAID => ['amount' => 496.80],
            NotificationFormatter::TYPE_SETTLEMENT_RECEIVED => ['amount' => 496.80],
            NotificationFormatter::TYPE_SETTLEMENT_OVERDUE => ['amount' => 320.00],
            NotificationFormatter::TYPE_SETTLEMENT_WARNING => ['amount' => 320.00],
            NotificationFormatter::TYPE_DISPUTE_OPENED => ['trip_id' => '101'],
            NotificationFormatter::TYPE_DISPUTE_RESOLVED => [],

            // السائقين والوثائق
            NotificationFormatter::TYPE_DRIVER_ACCOUNT_APPROVED => [],
            NotificationFormatter::TYPE_DRIVER_ACCOUNT_REJECTED => [],
            NotificationFormatter::TYPE_NEW_DRIVER_REGISTERED => ['driver_name' => 'عبد السلام المصراتي'],
            NotificationFormatter::TYPE_DRIVER_DOCUMENTS_UPDATED => ['driver_name' => 'عبد السلام المصراتي'],
            NotificationFormatter::TYPE_DRIVER_VEHICLE_UPDATED => ['driver_name' => 'عبد السلام المصراتي'],
            NotificationFormatter::TYPE_DRIVER_DOC_EXPIRING_SOON => [],
            NotificationFormatter::TYPE_DRIVER_DOC_EXPIRED => [],
            NotificationFormatter::TYPE_DRIVER_DOC_EXPIRED_ADMIN => [],

            // الشكاوى والذكاء الاصطناعي
            NotificationFormatter::TYPE_NEW_COMPLAINT_SUBMITTED => [],
            NotificationFormatter::TYPE_COMPLAINT_RESOLVED => [],
            NotificationFormatter::TYPE_DRIVER_AI_NEEDS_REVIEW => [],
            NotificationFormatter::TYPE_DRIVER_AI_SUSPENDED => [],
            NotificationFormatter::TYPE_DRIVER_SUSPENDED => [],
            NotificationFormatter::TYPE_DRIVER_AI_ALERT => [],
            NotificationFormatter::TYPE_DRIVER_REVIEW_FLAGGED => [],
            NotificationFormatter::TYPE_AI_SERVICE_OUTAGE => [],
            NotificationFormatter::TYPE_GENERAL_ANNOUNCEMENT => [],
        ];

        $service = app(NotificationService::class);

        foreach ($typesToTest as $type => $payload) {
            $formatted = NotificationFormatter::format($type, $payload);

            $this->assertNotEmpty($formatted['title'], "Title is empty for notification type: {$type}");
            $this->assertNotEmpty($formatted['message'], "Message is empty for notification type: {$type}");
            $this->assertNotEquals('', trim($formatted['message']), "Message is whitespace for notification type: {$type}");

            // التأكد من أن العملات المالية بالدينار الليبي د.ل وليس ر.س
            $this->assertStringNotContainsString('ر.س', $formatted['message'], "Currency should not be SAR for type: {$type}");
            $this->assertStringNotContainsString('ر.س', $formatted['title'], "Currency should not be SAR for type: {$type}");

            // إرسال الإشعار والتحقق من حفظه في جدول notifications بالنص المكتمل
            $notifId = $service->sendToUser($this->user, $type, $payload, withPush: false);
            $this->assertNotNull($notifId, "Failed creating notification for type: {$type}");

            // فحص السجل عبر الموديل
            $dbNotif = DatabaseNotification::find($notifId);
            $this->assertNotNull($dbNotif);
            $this->assertEquals($type, $dbNotif->data['type']);
            $this->assertEquals($formatted['title'], $dbNotif->data['title']);
            $this->assertEquals($formatted['message'], $dbNotif->data['message']);
            $this->assertNotEmpty($dbNotif->data['message']);

            // فحص السجل الخام في قاعدة البيانات والتأكد أنه نص عربي صريح وليس \uXXXX
            $rawRow = DB::table('notifications')->where('id', $notifId)->first();
            $this->assertNotNull($rawRow);
            $this->assertStringNotContainsString('\u06', $rawRow->data, "Database row should contain raw Arabic text, not unicode escape sequences");
        }
    }

    public function test_custom_title_and_message_are_preserved_in_database(): void
    {
        $service = app(NotificationService::class);

        $customTitle = '📢 إشعار مخصص تم تحديده يدوياً';
        $customMessage = 'نص رسالة مفصلة ومكتوبة حسب منطق الحدث الخاص.';

        $notifId = $service->sendToUser($this->user, 'custom_event', [
            'title'   => $customTitle,
            'message' => $customMessage,
        ], withPush: false);

        $dbNotif = DatabaseNotification::find($notifId);
        $this->assertNotNull($dbNotif);
        $this->assertEquals($customTitle, $dbNotif->data['title']);
        $this->assertEquals($customMessage, $dbNotif->data['message']);
    }
}
