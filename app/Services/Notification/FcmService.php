<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * إرسال Push Notifications عبر Firebase Cloud Messaging (HTTP v1 فقط، عبر kreait/firebase-php).
 * لا يوجد أي مسار Legacy أو مسار وهمي (mock) هنا — عدم توفر بيانات الاعتماد يعني فشل صامت
 * موثّق في الـ log فقط، وليس نجاحاً وهمياً.
 */
class FcmService
{
    protected ?Messaging $messaging = null;

    protected bool $messagingResolved = false;

    protected function messaging(): ?Messaging
    {
        if ($this->messagingResolved) {
            return $this->messaging;
        }

        $this->messagingResolved = true;

        $credentialsFile = config('firebase.credentials.file');

        if (empty($credentialsFile) || !file_exists($credentialsFile)) {
            Log::error("FcmService: Firebase credentials file not found at [{$credentialsFile}]. Push notifications will not be sent.");
            return null;
        }

        try {
            $this->messaging = (new Factory)->withServiceAccount($credentialsFile)->createMessaging();
        } catch (Throwable $e) {
            Log::error('FcmService: failed to initialize Firebase Messaging: ' . $e->getMessage());
            $this->messaging = null;
        }

        return $this->messaging;
    }

    /**
     * إرسال إشعار Push لكل الأجهزة النشطة (is_active=true) الخاصة بمستخدم واحد.
     *
     * @param  array{title:string,body:string,data:array<string,string>}  $payload
     */
    public function sendToUser(User $user, array $payload): void
    {
        $tokens = UserDevice::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();

        if (empty($tokens)) {
            Log::info("FcmService: no active devices for user #{$user->id}, skipping push.");
            return;
        }

        $this->sendToTokens($tokens, $payload);
    }

    /**
     * إرسال نفس الإشعار لمجموعة توكنات عبر Multicast (Kreait sendMulticast — HTTP v1).
     * كل توكن يُعالَج بشكل مستقل من طرف Firebase: نجاح/فشل توكن واحد لا يوقف الباقي.
     *
     * @param  string[]  $tokens
     * @param  array{title:string,body:string,data:array<string,string>}  $payload
     */
    public function sendToTokens(array $tokens, array $payload): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if (empty($tokens)) {
            return;
        }

        $messaging = $this->messaging();

        if ($messaging === null) {
            Log::warning('FcmService: messaging unavailable, skipping push for ' . count($tokens) . ' token(s).');
            return;
        }

        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? $payload['message'] ?? '');
        $data = array_map('strval', $payload['data'] ?? []);

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        try {
            $report = $messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            // فشل على مستوى الطلب بأكمله (شبكة، انقطاع Firebase مؤقت، مهلة زمنية...) —
            // هذا خطأ عابر (transient) يستحق إعادة المحاولة عبر آلية retry/backoff الخاصة
            // بـ SendFcmNotificationJob، لذا نُعيد رميه بدل ابتلاعه. لو ابتلعناه هنا، الـ Job
            // يُعتبر "نجح" رغم عدم إرسال أي شيء فعلياً، ولن يُعاد أبداً حتى مع خطأ عابر بحت.
            // ملاحظة: هذا مختلف عن فشل توكن واحد داخل استجابة ناجحة (يُعالَج أدناه بلا استثناء).
            Log::error('FcmService: sendMulticast request failed (will be retried by the job if attempts remain): ' . $e->getMessage());
            throw $e;
        }

        $deadTokens = array_unique(array_merge($report->invalidTokens(), $report->unknownTokens()));

        foreach ($deadTokens as $deadToken) {
            UserDevice::where('fcm_token', $deadToken)->update(['is_active' => false]);
            Log::warning("FcmService: device token deactivated (invalid/unregistered): {$deadToken}");
        }

        foreach ($report->failures()->getItems() as $failure) {
            if ($failure->messageTargetWasInvalid() || $failure->messageWasSentToUnknownToken()) {
                continue; // already handled above via is_active=false
            }

            Log::warning('FcmService: delivery failure for token ' . $failure->target()->value() . ': ' . ($failure->error()?->getMessage() ?? 'unknown error'));
        }

        Log::info('FcmService: multicast finished, ' . $report->successes()->count() . '/' . $report->count() . ' token(s) succeeded.');
    }
}
