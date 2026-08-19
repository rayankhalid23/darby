<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Queue;

class NotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->user = User::create([
            'full_name'     => 'مستخدم اختبار الإشعارات',
            'email'         => 'notif.test.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $this->otherUser = User::create([
            'full_name'     => 'مستخدم آخر',
            'email'         => 'other.test.' . uniqid() . '@darby.test',
            'phone_number'  => '092' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
    }

    public function test_user_can_list_their_notifications(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonCount(1, 'data.notifications');
    }

    public function test_notifications_are_paginated(): void
    {
        Queue::fake();
        for ($i = 0; $i < 20; $i++) {
            app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => (string) $i]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/notifications?per_page=5');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data.notifications');
        $this->assertEquals(20, $response->json('data.pagination.total'));
    }

    public function test_can_filter_notifications_by_type(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);
        app(NotificationService::class)->sendToUser($this->user, 'trip_completed', ['trip_id' => '2']);

        $response = $this->actingAs($this->user)->getJson('/api/notifications?type=trip_completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.notifications');
        $this->assertEquals('trip_completed', $response->json('data.notifications.0.type'));
    }

    public function test_unread_count_endpoint(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);
        app(NotificationService::class)->sendToUser($this->user, 'trip_completed', ['trip_id' => '2']);

        $response = $this->actingAs($this->user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('unread_count'));
    }

    public function test_can_mark_a_notification_as_read(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);

        $response = $this->actingAs($this->user)->patchJson("/api/notifications/{$id}/read");

        $response->assertStatus(200);
        $this->assertDatabaseHas('notifications', ['id' => $id]);
        $this->assertNotNull(\Illuminate\Notifications\DatabaseNotification::find($id)->read_at);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        Queue::fake();
        app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);
        app(NotificationService::class)->sendToUser($this->user, 'trip_completed', ['trip_id' => '2']);

        $response = $this->actingAs($this->user)->postJson('/api/notifications/read-all');

        $response->assertStatus(200);
        $this->assertEquals(0, $this->user->fresh()->unreadNotifications()->count());
    }

    public function test_can_delete_a_notification(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);

        $response = $this->actingAs($this->user)->deleteJson("/api/notifications/{$id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_user_cannot_access_another_users_notification(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->otherUser, 'trip_started', ['trip_id' => '1']);

        $response = $this->actingAs($this->user)->patchJson("/api/notifications/{$id}/read");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/notifications');
        $response->assertStatus(401);
    }
}
