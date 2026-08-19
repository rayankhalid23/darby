<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notification\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يرسل Push فعلياً عبر Firebase لإشعار database موجود مسبقاً.
 *
 * لا يُنشئ ولا يعدّل أي صف في جدول notifications إطلاقاً — فشل هذا الـ Job
 * (حتى بعد استنفاد كل المحاولات) لا يمس سجل الإشعار داخل التطبيق بأي شكل.
 * ShouldBeUnique يمنع تشغيل أكثر من نسخة لنفس notification_id في وقت واحد،
 * فلا يتكرر الـ push بسبب retry من الـ queue.
 */
class SendFcmNotificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public readonly string $notificationId,
        public readonly int $userId,
    ) {
    }

    public function uniqueId(): string
    {
        return $this->notificationId;
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(FcmService $fcmService): void
    {
        $notification = DatabaseNotification::query()->find($this->notificationId);

        if (!$notification) {
            Log::warning("SendFcmNotificationJob: notification #{$this->notificationId} not found, skipping push.");
            return;
        }

        $user = User::query()->find($this->userId);

        if (!$user) {
            Log::warning("SendFcmNotificationJob: user #{$this->userId} not found, skipping push.");
            return;
        }

        $data = $notification->data;

        $fcmData = [
            'id'          => (string) $notification->id,
            'type'        => (string) ($data['type'] ?? ''),
            'title'       => (string) ($data['title'] ?? ''),
            'body'        => (string) ($data['message'] ?? ''),
            'entity_type' => (string) ($data['entity_type'] ?? ''),
            'entity_id'   => (string) ($data['entity_id'] ?? ''),
            'screen'      => (string) ($data['screen'] ?? ''),
            'action'      => (string) ($data['action'] ?? ''),
            'payload'     => json_encode($data['payload'] ?? [], JSON_UNESCAPED_UNICODE),
        ];

        $fcmService->sendToUser($user, [
            'title' => (string) ($data['title'] ?? ''),
            'body'  => (string) ($data['message'] ?? ''),
            'data'  => $fcmData,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("SendFcmNotificationJob: permanently failed for notification #{$this->notificationId}, user #{$this->userId}: " . ($exception?->getMessage() ?? 'unknown error'));
    }
}
