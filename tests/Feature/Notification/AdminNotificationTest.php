<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Services\Notification\NotificationService;

class AdminNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'SuperAdmin', 'display_name' => 'مدير النظام'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->admin = User::create([
            'full_name'     => 'أدمن اختبار الإشعارات',
            'email'         => 'admin.notif.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 1,
            'is_active'     => 1,
        ]);

        $this->nonAdmin = User::create([
            'full_name'     => 'مستخدم عادي',
            'email'         => 'nonadmin.notif.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
    }

    public function test_admin_receives_admin_notification(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->admin, 'new_complaint_submitted', ['entity_id' => '1']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.notifications');
        $this->assertEquals('new_complaint_submitted', $response->json('data.notifications.0.type'));
    }

    public function test_admin_unread_count(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->admin, 'new_driver_registered', ['entity_id' => '1']);
        app(NotificationService::class)->sendToUser($this->admin, 'new_complaint_submitted', ['entity_id' => '2']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/notifications/unread-count');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('unread_count'));
    }

    public function test_admin_can_mark_notification_read(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->admin, 'new_complaint_submitted', ['entity_id' => '1']);

        $response = $this->actingAs($this->admin)->patchJson("/api/admin/notifications/{$id}/read");

        $response->assertStatus(200);
        $this->assertNotNull(\Illuminate\Notifications\DatabaseNotification::find($id)->read_at);
    }

    public function test_non_admin_user_is_forbidden_from_admin_notifications(): void
    {
        $response = $this->actingAs($this->nonAdmin)->getJson('/api/admin/notifications');

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_read_admin_unread_count(): void
    {
        $response = $this->actingAs($this->nonAdmin)->getJson('/api/admin/notifications/unread-count');

        $response->assertStatus(403);
    }
}
