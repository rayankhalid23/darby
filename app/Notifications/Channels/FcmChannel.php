<?php

namespace App\Notifications\Channels;

use App\Services\Notification\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * إرسال الإشعار عبر قناة FCM Push Notification
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'toFcm')) {
            $payload = $notification->toFcm($notifiable);
        } elseif (method_exists($notification, 'toArray')) {
            $payload = $notification->toArray($notifiable);
        } else {
            return;
        }

        if (!empty($payload)) {
            $this->fcmService->sendPushNotification($notifiable, $payload);
        }
    }
}
