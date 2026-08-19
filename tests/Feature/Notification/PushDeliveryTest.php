<?php

namespace Tests\Feature\Notification;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\UserDevice;
use App\Jobs\SendFcmNotificationJob;
use App\Services\Notification\NotificationService;
use App\Services\Notification\FcmService;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;

class PushDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insertOrIgnore([
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        $this->user = User::create([
            'full_name'     => 'مستخدم اختبار الدفع',
            'email'         => 'push.test.' . uniqid() . '@darby.test',
            'phone_number'  => '091' . rand(1000000, 9999999),
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_to_user_creates_database_row_and_dispatches_job(): void
    {
        Queue::fake();

        $id = app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '99']);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('notifications', ['id' => $id]);

        Queue::assertPushed(SendFcmNotificationJob::class, function ($job) use ($id) {
            return $job->notificationId === $id && $job->userId === $this->user->id;
        });
    }

    public function test_duplicate_event_for_same_recipient_is_not_sent_twice(): void
    {
        Queue::fake();

        $service = app(NotificationService::class);
        $firstId = $service->sendToUser($this->user, 'trip_completed', ['trip_id' => '55']);
        $secondId = $service->sendToUser($this->user, 'trip_completed', ['trip_id' => '55']);

        $this->assertNotNull($firstId);
        $this->assertNull($secondId);
        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $this->user->id)->count());
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_same_type_different_entity_is_not_treated_as_duplicate(): void
    {
        Queue::fake();

        $service = app(NotificationService::class);
        $service->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);
        $service->sendToUser($this->user, 'trip_started', ['trip_id' => '2']);

        $this->assertEquals(2, DatabaseNotification::where('notifiable_id', $this->user->id)->count());
    }

    public function test_job_skips_gracefully_when_notification_missing(): void
    {
        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldNotReceive('sendToUser');
        });

        $job = new SendFcmNotificationJob((string) \Illuminate\Support\Str::uuid(), $this->user->id);
        $job->handle(app(FcmService::class));

        $this->assertTrue(true); // no exception thrown = pass
    }

    public function test_job_skips_gracefully_when_user_missing(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '1']);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldNotReceive('sendToUser');
        });

        $job = new SendFcmNotificationJob($id, 999999999);
        $job->handle(app(FcmService::class));

        $this->assertTrue(true);
    }

    public function test_job_calls_fcm_service_with_correct_payload_for_valid_notification(): void
    {
        Queue::fake();
        $id = app(NotificationService::class)->sendToUser($this->user, 'trip_started', ['trip_id' => '77']);

        $this->mock(FcmService::class, function ($mock) use ($id) {
            $mock->shouldReceive('sendToUser')
                ->once()
                ->withArgs(function ($user, $payload) use ($id) {
                    return $user->id === $this->user->id
                        && $payload['data']['id'] === $id
                        && $payload['data']['type'] === 'trip_started';
                });
        });

        $job = new SendFcmNotificationJob($id, $this->user->id);
        $job->handle(app(FcmService::class));

        $this->assertTrue(true); // Mockery expectation above is verified in tearDown()
    }

    public function test_fcm_service_skips_push_when_user_has_no_devices(): void
    {
        $fcmService = app(FcmService::class);
        // No exception should be thrown, no HTTP call attempted (no credentials configured in test env).
        $fcmService->sendToUser($this->user, ['title' => 'x', 'body' => 'y', 'data' => []]);
        $this->assertTrue(true);
    }

    public function test_fcm_service_without_credentials_does_not_throw(): void
    {
        UserDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'device-x',
            'fcm_token'      => 'token-' . uniqid(),
            'device_name'    => 'Test Device',
            'platform'       => 'android',
            'is_active'      => true,
            'last_active_at' => now(),
        ]);

        $fcmService = app(FcmService::class);
        // Test env has no firebase-service-account.json -> should log and return, not throw.
        $fcmService->sendToUser($this->user, ['title' => 'x', 'body' => 'y', 'data' => ['id' => '1']]);
        $this->assertTrue(true);
    }

    // --- DB-level idempotency: the dedupe_key UNIQUE constraint is the real safety net, ---
    // --- not just an app-level SELECT-before-INSERT check (which races under concurrency). ---

    public function test_dedupe_key_unique_constraint_blocks_a_row_inserted_outside_the_service(): void
    {
        Queue::fake();

        $service = app(NotificationService::class);

        // Simulate a race: a row with the SAME dedupe_key the service would compute already
        // exists (e.g. inserted by a concurrent request that won the race), created directly
        // via the DB, bypassing NotificationService entirely.
        $dedupeKey = md5('trip_started:trip:555:' . $this->user->id);

        DatabaseNotification::create([
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'type'       => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $this->user->id,
            'data'       => ['type' => 'trip_started', 'entity_type' => 'trip', 'entity_id' => '555'],
            'dedupe_key' => $dedupeKey,
        ]);

        $id = $service->sendToUser($this->user, 'trip_started', ['trip_id' => '555']);

        $this->assertNull($id, 'service must detect the DB-level conflict and return null, not throw or insert a duplicate');
        $this->assertEquals(1, DatabaseNotification::where('dedupe_key', $dedupeKey)->count());
        Queue::assertNotPushed(SendFcmNotificationJob::class);
    }

    public function test_notifications_dedupe_key_column_has_a_real_unique_index(): void
    {
        $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM notifications WHERE Key_name = 'notifications_dedupe_key_unique'");
        $this->assertNotEmpty($indexes, 'expected a UNIQUE index on notifications.dedupe_key');
        $this->assertEquals(0, $indexes[0]->Non_unique, 'index must be UNIQUE, not a plain index');
    }

    // --- Queue retry semantics: a transient Firebase/network failure must propagate so the ---
    // --- job's built-in retry/backoff engages, instead of being silently swallowed as "success". ---

    public function test_fcm_transient_request_failure_propagates_for_job_retry(): void
    {
        UserDevice::create([
            'user_id'        => $this->user->id,
            'device_id'      => 'device-retry',
            'fcm_token'      => 'token-retry-' . uniqid(),
            'device_name'    => 'Test Device',
            'platform'       => 'android',
            'is_active'      => true,
            'last_active_at' => now(),
        ]);

        $messaging = Mockery::mock(\Kreait\Firebase\Contract\Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->andThrow(new \RuntimeException('simulated transient Firebase network failure'));

        $fcmService = new FcmService();
        $ref = new \ReflectionClass($fcmService);
        $resolvedProp = $ref->getProperty('messagingResolved');
        $resolvedProp->setAccessible(true);
        $resolvedProp->setValue($fcmService, true);
        $messagingProp = $ref->getProperty('messaging');
        $messagingProp->setAccessible(true);
        $messagingProp->setValue($fcmService, $messaging);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated transient Firebase network failure');

        $fcmService->sendToUser($this->user, ['title' => 'x', 'body' => 'y', 'data' => ['id' => '1']]);
    }
}
