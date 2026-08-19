<?php

namespace App\Services\Shared;

use App\Models\Admin\Admin;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * عميل HTTP عام لخدمة تصنيف النصوص بالذكاء الاصطناعي (ai_service) — مسؤول فقط
 * عن الاتصال وشبكة الأمان عند الفشل. القرار الإداري (إيقاف/تنبيه/تسجيل) من
 * مسؤولية الخدمة المستهلكة (مثال: DriverAiService)، وليس من مسؤوليته هنا.
 */
class AiClassifierService
{
    private const ACTION_NONE = 'no_action';

    // تتبّع الإخفاقات المتتالية لتنبيه الإدارة عند انقطاع الخدمة، بدل إغراقها
    // بإشعار مستقل لكل شكوى/تعليق يفشل خلال فترة التعطل.
    private const FAILURE_CACHE_KEY = 'ai_classifier_consecutive_failures';
    private const FAILURE_ALERT_THRESHOLD = 5;
    private const FAILURE_WINDOW_MINUTES = 30;

    protected string $endpoint;
    protected int $timeoutSeconds;
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->endpoint = config('services.driver_ai.url', 'http://127.0.0.1:8000/api/v1/predict');
        $this->timeoutSeconds = (int) config('services.driver_ai.timeout', 3);
        $this->notificationService = $notificationService;
    }

    /**
     * يستدعي خدمة التصنيف الآلي عبر HTTP مع شبكة أمان كاملة: أي فشل (شبكة، مهلة،
     * خطأ سيرفر، استجابة غير متوقعة) يؤول دائماً إلى نتيجة افتراضية آمنة (no_action)
     * ولا يُسمح له بإسقاط التطبيق أو تعطيل العملية المستدعية.
     *
     * $context: حقول إضافية تُرسَل مع النص (مثال: ['driver_id' => 5]).
     */
    public function classify(string $text, array $context = []): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)->post(
                $this->endpoint,
                array_merge(['complaint_text' => $text], $context)
            );

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data) && !empty($data['action'])) {
                    $this->recordSuccess();
                    return $data;
                }

                Log::warning('AiClassifierService: استجابة ناجحة لكن بصيغة غير متوقعة من خدمة التصنيف.', [
                    'body' => $response->body(),
                ]);
            } else {
                Log::warning('AiClassifierService: استجابة غير ناجحة من خدمة التصنيف.', [
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('AiClassifierService: تعذر الاتصال بخدمة التصنيف الآلي - ' . $e->getMessage(), [
                'endpoint' => $this->endpoint,
            ]);
        }

        $this->recordFailure();
        return $this->fallbackResult();
    }

    protected function fallbackResult(): array
    {
        return [
            'label'          => null,
            'confidence'     => null,
            'action'         => self::ACTION_NONE,
            'severity'       => null,
            'message_ar'     => 'تعذر الوصول لخدمة التحليل الآلي، تم اعتماد الإجراء الافتراضي (لا إجراء) مؤقتاً.',
            'low_confidence' => null,
        ];
    }

    protected function recordSuccess(): void
    {
        Cache::forget(self::FAILURE_CACHE_KEY);
    }

    protected function recordFailure(): void
    {
        $count = (int) Cache::get(self::FAILURE_CACHE_KEY, 0) + 1;
        Cache::put(self::FAILURE_CACHE_KEY, $count, now()->addMinutes(self::FAILURE_WINDOW_MINUTES));

        // نُنبّه مرة واحدة فقط عند لحظة تجاوز العتبة، لا في كل مرة بعدها
        if ($count === self::FAILURE_ALERT_THRESHOLD) {
            $this->notifyAdminsOfOutage($count);
        }
    }

    protected function notifyAdminsOfOutage(int $failureCount): void
    {
        try {
            $adminUsers = Admin::with('user')->get()->map(fn ($admin) => $admin->user)->filter();

            if ($adminUsers->isEmpty()) {
                return;
            }

            // withPush: false — إشعارات الأدمن عبر DB + polling فقط، بلا Firebase Push.
            // entity_id بدقيقة الحدوث حتى لا يمنع dedupe_key تنبيه انقطاع لاحق فعلي (كل انقطاع جديد
            // يستحق تنبيهاً خاصاً به، بينما يمنع فقط استدعاءً متزامناً مكرراً لنفس اللحظة بالضبط).
            $this->notificationService->sendToUsers($adminUsers, 'ai_service_outage', [
                'title'     => '🚨 خدمة التصنيف الآلي غير متاحة',
                'message'   => "فشل الاتصال بخدمة التصنيف الآلي {$failureCount} مرات متتالية خلال آخر "
                    . self::FAILURE_WINDOW_MINUTES
                    . ' دقيقة — الشكاوى والتعليقات الجديدة لن تُحلَّل آلياً حتى تعود الخدمة، يُنصح بالمراجعة اليدوية مؤقتاً.',
                'entity_id' => now()->format('Y-m-d_H:i'),
            ], withPush: false);
        } catch (Throwable $e) {
            Log::error('AiClassifierService: فشل إرسال إشعار انقطاع الخدمة - ' . $e->getMessage());
        }
    }
}
