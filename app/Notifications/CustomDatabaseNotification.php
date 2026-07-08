<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CustomDatabaseNotification extends Notification
{
    use Queueable;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via(object $notifiable): array
    {
        return ['database']; // الحفظ في جدول notifications القياسي
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->data['title'] ?? 'تنبيه جديد',
            'message' => $this->data['message'] ?? '',
            'type' => $this->data['type'] ?? 'general',
            'action_url' => $this->data['action_url'] ?? null,
        ];
    }
}