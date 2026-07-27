<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $serverKey = config('services.fcm.key');

        $title = $payload['title'] ?? 'إشعار جديد';
        $body = $payload['message'] ?? $payload['body'] ?? '';
        $customData = array_map('strval', $payload['payload'] ?? []);

        // بيئة التطوير أو في حال عدم إدخال Server Key حقيقي
        if (empty($serverKey) || $serverKey === 'mock_key' || config('app.env') === 'testing') {
            Log::info("FcmService [MOCK SEND]: Notification sent to " . count($tokens) . " token(s). Title: {$title}, Body: {$body}");
            return true;
        }

        // إرسال الإشعار عبر FCM (Firebase Cloud Messaging Legacy / HTTP API)
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
                'title'   => $title,
                'body'    => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ], $customData),
            'priority'         => 'high',
        ]);

        if ($response->successful()) {
            $responseData = $response->json();

            // تنظيف التوكنات المنتهية أو غير الصالحة
            if (isset($responseData['results']) && is_array($responseData['results'])) {
                foreach ($responseData['results'] as $index => $result) {
                    if (isset($result['error']) && in_array($result['error'], ['NotRegistered', 'InvalidRegistration', 'MismatchSenderId'])) {
                        $badToken = $tokens[$index] ?? null;
                        if ($badToken) {
                            UserDevice::where('fcm_token', $badToken)->delete();
                            Log::warning("FcmService: Removed invalid FCM token: {$badToken}");
                        }
                    }
                }
            }

            return true;
        }

        Log::error("FcmService Failed: HTTP Status {$response->status()} - Response: " . $response->body());
        return false;
    }
}
