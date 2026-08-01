<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationController extends Controller
{
    public static function sendFcmNotification($fcmToken, $title, $body, array $data = [])
    {
        try {
            // تهيئة اتصال الفايربيز
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials.file'));
            $messaging = $factory->createMessaging();

            // بناء الإشعار (Notification + Data Payload)
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($data);

            // إرسال الإشعار عبر فري بيز
            $messaging->send($message);

            return true;
        } catch (\Exception $e) {
            // يمكنك تسجيل الخطأ هنا إن أردت Log::error($e->getMessage());
            return false;
        }
    }

    // API تجريبي لاختبار إرسال الإشعار مباشرة
    public function sendTestNotification(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'title' => 'required|string',
            'body' => 'required|string',
            'trip_id' => 'nullable',
            'child_id' => 'nullable',
        ]);

        $customData = [
            'type' => 'child_picked_up',
            'trip_id' => (string) ($request->trip_id ?? '12'),
            'child_id' => (string) ($request->child_id ?? '1'),
        ];

        $sent = self::sendFcmNotification(
            $request->token,
            $request->title,
            $request->body,
            $customData
        );

        if ($sent) {
            return response()->json(['status' => true, 'message' => 'Notification sent successfully!']);
        }

        return response()->json(['status' => false, 'message' => 'Failed to send notification.'], 500);
    }
}