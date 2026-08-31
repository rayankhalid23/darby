<?php

namespace App\Services;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Shared\Complaint;
use App\Models\Shared\DriverReview;
use App\Services\Admin\ComplaintService as AdminComplaintService;
use App\Services\Notification\NotificationService;
use App\Services\Shared\AiClassifierService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يحلّل نص شكوى أو تعليق مراجعة عبر AiClassifierService (اتصال HTTP فقط)،
 * ويطبّق القرار الإداري المقابل بناءً على الأنظمة الجاهزة في المشروع
 * (تعليق السائق عبر ComplaintService::suspendDriver، والإشعارات عبر NotificationService).
 */
class DriverAiService
{
    private const ACTION_SUSPEND = 'suspend_driver';
    private const ACTION_NOTIFY  = 'notify_driver';
    private const ACTION_LOG     = 'log_only';
    private const ACTION_NONE    = 'no_action';

    // severity >= هذه القيمة على تعليق مراجعة يستوجب تنبيه الإدارة (لا إيقافاً تلقائياً)
    private const REVIEW_FLAG_SEVERITY_THRESHOLD = 2;

    protected AiClassifierService $classifier;
    protected AdminComplaintService $adminComplaintService;
    protected NotificationService $notificationService;

    public function __construct(AiClassifierService $classifier, AdminComplaintService $adminComplaintService, NotificationService $notificationService)
    {
        $this->classifier = $classifier;
        $this->adminComplaintService = $adminComplaintService;
        $this->notificationService = $notificationService;
    }

    protected function adminUsers(): \Illuminate\Support\Collection
    {
        return Admin::with('user')->get()->map(fn ($admin) => $admin->user)->filter();
    }

    // ============================================================
    // الشكاوى الرسمية
    // ============================================================

    /**
     * نقطة الدخول الرئيسية للشكاوى: يحلّل شكوى موجودة، يحفظ نتيجة التحليل عليها،
     * ثم يطبّق الإجراء الإداري المناسب بناءً على القرار المستلم.
     */
    public function analyzeComplaint(Complaint $complaint, bool $throwOnFailure = false): array
    {
        $result = $this->classifier->classify($complaint->description, ['driver_id' => $complaint->driver_id], $throwOnFailure);

        $complaint->forceFill([
            'ai_action'           => $result['action'] ?? self::ACTION_NONE,
            'ai_confidence'       => $result['confidence'] ?? null,
            'ai_severity'         => $result['severity'] ?? null,
            'ai_analysis_message' => $result['message_ar'] ?? null,
        ])->save();

        $this->applyDecision($complaint, $result);

        return $result;
    }

    protected function applyDecision(Complaint $complaint, array $result): void
    {
        $action = $result['action'] ?? self::ACTION_NONE;

        // 🛡️ بوابة الثقة المنخفضة: الإيقاف قرار خطير الأثر — لا يُنفَّذ آلياً إن كان
        // النموذج نفسه غير واثق منه (low_confidence)، بل يُحوَّل لمراجعة إدارية عاجلة.
        if ($action === self::ACTION_SUSPEND && !empty($result['low_confidence'])) {
            $this->handleLowConfidenceSuspend($complaint, $result);
            return;
        }

        match ($action) {
            self::ACTION_SUSPEND => $this->handleSuspendDriver($complaint, $result),
            self::ACTION_NOTIFY  => $this->handleNotifyDriver($complaint, $result),
            self::ACTION_LOG     => $this->handleLogOnly($complaint),
            default               => $this->handleNoAction($complaint),
        };
    }

    /**
     * suspend_driver بثقة منخفضة: لا إيقاف تلقائي — تحويل لمراجعة إدارية عاجلة فقط.
     */
    protected function handleLowConfidenceSuspend(Complaint $complaint, array $result): void
    {
        $complaint->forceFill([
            'action_taken' => 'needs_admin_review',
        ])->save();

        $confidencePercent = round(((float) ($result['confidence'] ?? 0)) * 100);

        $this->notificationService->sendToUsers($this->adminUsers(), 'driver_ai_needs_review', [
            'title'       => '⚠️ شكوى تحتاج مراجعة عاجلة (ثقة منخفضة)',
            'message'     => "صنّف النظام الشكوى رقم {$complaint->id} كمخالفة جسيمة، لكن بثقة منخفضة ({$confidencePercent}%) — لم يتم إيقاف السائق آلياً تفادياً لقرار خاطئ، يرجى المراجعة اليدوية فوراً.",
            'entity_type' => 'complaint',
            'entity_id'   => (string) $complaint->id,
        ], withPush: false);
    }

    /**
     * suspend_driver: إيقاف فوري عبر الدالة الجاهزة + تسجيل السبب + إشعار الإدارة والسائق.
     */
    protected function handleSuspendDriver(Complaint $complaint, array $result): void
    {
        $driver = Driver::with('user')->find($complaint->driver_id);
        if (!$driver) {
            return;
        }

        // الدالة الجاهزة الموجودة مسبقاً — لا داعي لإعادة كتابة منطق الإيقاف.
        // adminId = null لأن هذا إيقاف آلي (نظام/ذكاء اصطناعي)، وليس قرار أدمن محدد.
        $this->adminComplaintService->suspendDriver($driver->id, null);

        $complaint->forceFill([
            'status'         => 'completed',
            'action_taken'   => 'suspension',
            'action_details' => 'تجاوز سلوكي خطير بناءً على تحليل الشكوى.',
            'resolved_at'    => now(),
        ])->save();

        $this->notificationService->sendToUsers($this->adminUsers(), 'driver_ai_suspended', [
            'title'       => 'إيقاف تلقائي لسائق ⛔',
            'message'     => "أوقف النظام السائق رقم {$driver->id} تلقائياً بناءً على تحليل الذكاء الاصطناعي لشكوى رقم {$complaint->id} (خطورة: " . ($result['severity'] ?? '-') . ').',
            'entity_type' => 'complaint',
            'entity_id'   => (string) $complaint->id,
        ], withPush: false);

        // 🔔 إخطار السائق نفسه بإيقاف حسابه (كان مفقوداً سابقاً — السائق لم يكن يُخطَر إطلاقاً)
        if ($driver->user) {
            $this->notificationService->sendToUser($driver->user, 'driver_suspended', [
                'title'       => 'تم إيقاف حسابك مؤقتاً ⛔',
                'message'     => 'تم إيقاف حسابك بناءً على مراجعة شكوى وردت بحقك. يرجى التواصل مع الدعم لمعرفة التفاصيل وتقديم توضيحك.',
                'entity_type' => 'complaint',
                'entity_id'   => (string) $complaint->id,
            ]);
        }
    }

    /**
     * notify_driver: لا يوقف السائق، فقط تنبيه رسمي له + إدراج المخالفة في سجله للتدقيق.
     */
    protected function handleNotifyDriver(Complaint $complaint, array $result): void
    {
        $driver = Driver::with('user')->find($complaint->driver_id);

        $complaint->forceFill([
            'action_taken' => 'warning',
        ])->save();

        if (!$driver || !$driver->user) {
            return;
        }

        $this->notificationService->sendToUser($driver->user, 'driver_ai_alert', [
            'title'       => 'تنبيه سلوكي ⚠️',
            'message'     => $result['message_ar'] ?? 'تم رصد ملاحظة على أدائك بناءً على شكوى، يرجى الانتباه لجودة الخدمة.',
            'entity_type' => 'complaint',
            'entity_id'   => (string) $complaint->id,
        ]);
    }

    /**
     * log_only: لا تعديل على حالة السائق ولا إشعار له — الشكوى تبقى بحالتها الافتراضية
     * (pending) بانتظار مراجعة الأدمن، مع علامة ai_action='log_only' جاهزة للتدقيق والفلترة.
     */
    protected function handleLogOnly(Complaint $complaint): void
    {
        // لا شيء إضافي مطلوب: نتيجة التحليل الآلي محفوظة بالفعل على الشكوى في analyzeComplaint().
    }

    /**
     * no_action: تقييم طبيعي — لا تعديل على حالة الشكوى أو السائق.
     */
    protected function handleNoAction(Complaint $complaint): void
    {
        // لا شيء إضافي مطلوب.
    }

    // ============================================================
    // تعليقات المراجعات (DriverReview.comment)
    // ============================================================

    /**
     * يحلّل تعليق مراجعة نصي. قناة تقييم خفيفة وليست شكوى رسمية، لذا لا تُوقِف
     * سائقاً تلقائياً أبداً مهما كانت النتيجة — فقط تحفظ التصنيف وتُنبّه الإدارة
     * إن كانت الخطورة مرتفعة (severity >= 2) لمراجعة يدوية.
     */
    public function analyzeReview(DriverReview $review, bool $throwOnFailure = false): array
    {
        $text = trim((string) $review->comment);
        if ($text === '') {
            return [];
        }

        $result = $this->classifier->classify($text, ['driver_id' => $review->driver_id], $throwOnFailure);

        $review->forceFill([
            'ai_action'           => $result['action'] ?? self::ACTION_NONE,
            'ai_confidence'       => $result['confidence'] ?? null,
            'ai_severity'         => $result['severity'] ?? null,
            'ai_analysis_message' => $result['message_ar'] ?? null,
        ])->save();

        if ((int) ($result['severity'] ?? 0) >= self::REVIEW_FLAG_SEVERITY_THRESHOLD) {
            $this->notifyAdminsOfFlaggedReview($review, $result);
        }

        return $result;
    }

    protected function notifyAdminsOfFlaggedReview(DriverReview $review, array $result): void
    {
        try {
            $this->notificationService->sendToUsers($this->adminUsers(), 'driver_review_flagged', [
                'title'       => '📝 تعليق تقييم يحتاج مراجعة',
                'message'     => "تعليق ولي أمر على تقييم السائق رقم {$review->driver_id} صُنِّف كملاحظة مثيرة للقلق (" . ($result['message_ar'] ?? '') . ')، يُنصح بالمراجعة.',
                'entity_type' => 'driver_review',
                'entity_id'   => (string) $review->id,
            ], withPush: false);
        } catch (Throwable $e) {
            Log::warning('DriverAiService: فشل إشعار الإدارة بتعليق مراجعة مُعلَّم - ' . $e->getMessage());
        }
    }
}
