<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Services\Notification\NotificationFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomDatabaseNotification extends Notification
{
    use Queueable;

    protected array $data;

    public function __construct(array $data)
    {
        $type = $data['type'] ?? 'general';
        $this->data = NotificationFormatter::format($type, $data);
    }

    /**
     * القنوات المستخدمة لإرسال الإشعار (قاعدة البيانات + FCM Push)
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * هيكل البيانات المخزنة في جدول notifications قاعدة البيانات (In-App Notification)
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'      => $this->data['title'],
            'message'    => $this->data['message'],
            'type'       => $this->data['type'],
            'action_url' => $this->data['action_url'],
            'entity_id'  => $this->data['entity_id'],
            'screen'     => $this->data['screen'],
            'payload'    => $this->data['payload'],
        ];
    }

    /**
     * هيكل البيانات المرسلة إلى قناة FCM Push Notification
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'title'   => $this->data['title'],
            'message' => $this->data['message'],
            'payload' => $this->data['payload'],
        ];
    }
}