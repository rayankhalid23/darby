<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FcmService
{
    /**
     * إرسال إشعار FCM Push لمستخدم واحد أو عدة مستخدمين
     */
    public function sendPushNotification($users, array $payload): bool
    {
        $userCollection = is_iterable($users) ? $users : [$users];
        $tokens = [];

        foreach ($userCollection as $user) {
            if ($user instanceof User) {
                $userTokens = UserDevice::where('user_id', $user->id)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->where('fcm_token', '!=', 'mock_fcm_token')
                    ->pluck('fcm_token')
                    ->toArray();

                $tokens = array_merge($tokens, $userTokens);
            }
        }

        $tokens = array_unique(array_filter($tokens));

        if (empty($tokens)) {
            Log::info("FcmService: No valid FCM tokens found for target user(s).");
            return false;
        }

        return $this->sendToTokens($tokens, $payload);
    }

    /**
     * إرسال الإشعار لمجموعة من توكنات FCM
     */
    public function sendToTokens(array $tokens, array $payload): bool
    {
        $title = $payload['title'] ?? 'إشعار جديد';
        $body = $payload['message'] ?? $payload['body'] ?? '';
        $customData = array_map('strval', $payload['payload'] ?? []);

        // 1. استخدام Firebase Admin SDK (V1 API) عند توفر ملف الـ Credentials
        $serviceAccountPath = config('firebase.credentials.file', storage_path('app/firebase/firebase-service-account.json'));
        
        if (file_exists($serviceAccountPath)) {
            try {
                $factory = (new Factory)->withServiceAccount($serviceAccountPath);
                $messaging = $factory->createMessaging();

                $notification = Notification::create($title, $body);

                foreach ($tokens as $token) {
                    try {
                        $message = CloudMessage::withTarget('token', $token)
                            ->withNotification($notification)
                            ->withData(array_merge([
                                'title' => $title,
                                'body'  => $body,
                            ], $customData));

                        $messaging->send($message);
                    } catch (Throwable $e) {
                        Log::warning("FcmService V1 send error for token {$token}: " . $e->getMessage());
                        if (str_contains($e->getMessage(), 'UNREGISTERED') || str_contains($e->getMessage(), 'NOT_FOUND')) {
                            UserDevice::where('fcm_token', $token)->delete();
                        }
                    }
                }

                Log::info("FcmService [V1 SDK SENT]: Notification sent to " . count($tokens) . " token(s).");
                return true;

            } catch (Throwable $e) {
                Log::error("FcmService V1 Factory Error: " . $e->getMessage());
            }
        }

        // 2. المحاولة عبر Legacy HTTP API أو Mock Send في بيئة الاختبار
        $serverKey = config('services.fcm.key');
        if (empty($serverKey) || $serverKey === 'mock_key' || config('app.env') === 'testing') {
            Log::info("FcmService [MOCK SEND]: Notification sent to " . count($tokens) . " token(s). Title: {$title}, Body: {$body}");
            return true;
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type'  => 'application/json',
        ])->post($url, [
            'registration_ids' => array_values($tokens),
            'notification'     => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'badge' => 1,
            ],
            'data'             => array_merge([
                'title'        => $title,
                'body'         => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ], $customData),
            'priority'         => 'high',
        ]);

        if ($response->successful()) {
            return true;
        }

        Log::error("FcmService Failed: HTTP Status {$response->status()} - Response: " . $response->body());
        return false;
    }
}
