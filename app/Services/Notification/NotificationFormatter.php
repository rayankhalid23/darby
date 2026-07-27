<?php

namespace App\Services\Notification;

class NotificationFormatter
{
    /**
     * ثوابت أنواع الإشعارات التابعة للنظام
     */
    public const TYPE_TRIP_STARTED              = 'trip_started';
    public const TYPE_DRIVER_ARRIVED            = 'driver_arrived';
    public const TYPE_CHILD_PICKED_UP           = 'child_picked_up';
    public const TYPE_CHILD_DROPPED_OFF          = 'child_dropped_off';
    public const TYPE_STUDENT_ABSENT            = 'student_absent';
    public const TYPE_TRIP_COMPLETED            = 'trip_completed';
    public const TYPE_TRIP_CANCELLED            = 'trip_cancelled';

    public const TYPE_CONTRACT_CREATED          = 'contract_created';
    public const TYPE_CONTRACT_SIGNED           = 'contract_signed';
    public const TYPE_CONTRACT_APPROVED         = 'contract_approved';
    public const TYPE_CONTRACT_REJECTED         = 'contract_rejected';

    public const TYPE_SUBSCRIPTION_SUBMITTED    = 'subscription_submitted';
    public const TYPE_SUBSCRIPTION_APPROVED     = 'subscription_approved';
    public const TYPE_SUBSCRIPTION_REJECTED     = 'subscription_rejected';
    public const TYPE_SUBSCRIPTION_PAYMENT_REQ  = 'subscription_payment_required';

    public const TYPE_RECHARGE_APPROVED         = 'recharge_approved';
    public const TYPE_RECHARGE_REJECTED         = 'recharge_rejected';
    public const TYPE_WITHDRAWAL_APPROVED       = 'withdrawal_approved';
    public const TYPE_WITHDRAWAL_REJECTED       = 'withdrawal_rejected';
    public const TYPE_INVOICE_GENERATED         = 'invoice_generated';

    public const TYPE_DRIVER_ACCOUNT_APPROVED   = 'driver_account_approved';
    public const TYPE_DRIVER_ACCOUNT_REJECTED   = 'driver_account_rejected';
    public const TYPE_GENERAL_ANNOUNCEMENT      = 'general_announcement';

    /**
     * صياغة كائن الإشعار الموحد بناءً على النوع والتفاصيل
     */
    public static function format(string $type, array $data = []): array
    {
        $title = $data['title'] ?? 'تنبيه جديد';
        $message = $data['message'] ?? '';
        $actionUrl = $data['action_url'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $screen = $data['screen'] ?? 'HOME';
        $extraPayload = $data['payload'] ?? [];

        switch ($type) {
            // --- الرحلات ---
            case self::TYPE_TRIP_STARTED:
                $title = $data['title'] ?? 'بدء الرحلة 🚌';
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $message = $data['message'] ?? "انطلقت الرحلة رقم #{$tripId}، السائق في الطريق الآن.";
                $screen = 'TRIP_DETAILS';
                $entityId = $tripId;
                break;

            case self::TYPE_DRIVER_ARRIVED:
                $title = $data['title'] ?? 'وصول السائق 📍';
                $message = $data['message'] ?? 'وصل السائق إلى نقطة الانطلاق لتسلم الطلاب.';
                $screen = 'TRIP_TRACKING';
                break;

            case self::TYPE_CHILD_PICKED_UP:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'ركوب الطالب 🎒';
                $message = $data['message'] ?? "تم صعود الطالب ({$childName}) إلى الحافلة بنجاح.";
                $screen = 'TRIP_LIVE';
                break;

            case self::TYPE_CHILD_DROPPED_OFF:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'وصول الطالب 🏡';
                $message = $data['message'] ?? "تم نزول الطالب ({$childName}) ووصوله بسلام إلى وجهته.";
                $screen = 'TRIP_LIVE';
                break;

            case self::TYPE_STUDENT_ABSENT:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'تسجيل غياب ⚠️';
                $message = $data['message'] ?? "تم تسجيل غياب الطالب ({$childName}) عن الرحلة الحالية.";
                $screen = 'ATTENDANCE_LOG';
                break;

            case self::TYPE_TRIP_COMPLETED:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'اكتملت الرحلة ✅';
                $message = $data['message'] ?? "تمت الرحلة رقم #{$tripId} بنجاح ووصل جميع الطلاب بسلام.";
                $screen = 'TRIP_SUMMARY';
                $entityId = $tripId;
                break;

            case self::TYPE_TRIP_CANCELLED:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'إلغاء الرحلة ❌';
                $message = $data['message'] ?? "تم إلغاء الرحلة رقم #{$tripId}.";
                $screen = 'TRIP_DETAILS';
                $entityId = $tripId;
                break;

            // --- العقود ---
            case self::TYPE_CONTRACT_CREATED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'عقد جديد 📝';
                $message = $data['message'] ?? "تم إنشاء العقد رقم #{$contractNo} وبانتظار التوقيع.";
                $screen = 'CONTRACT_DETAILS';
                $entityId = $contractNo;
                break;

            case self::TYPE_CONTRACT_SIGNED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'توقيع العقد ✍️';
                $message = $data['message'] ?? "تم توقيع العقد رقم #{$contractNo} بنجاح.";
                $screen = 'CONTRACT_DETAILS';
                $entityId = $contractNo;
                break;

            case self::TYPE_CONTRACT_APPROVED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'اعتماد العقد 🎉';
                $message = $data['message'] ?? "تمت الموافقة واعتماد العقد رقم #{$contractNo}.";
                $screen = 'CONTRACT_DETAILS';
                $entityId = $contractNo;
                break;

            case self::TYPE_CONTRACT_REJECTED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'رفض العقد ⚠️';
                $message = $data['message'] ?? "تعذر قبول أو اعتماد العقد رقم #{$contractNo}.";
                $screen = 'CONTRACT_DETAILS';
                $entityId = $contractNo;
                break;

            // --- طلبات الاشتراكات ---
            case self::TYPE_SUBSCRIPTION_APPROVED:
                $reqId = $data['request_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'الموافقة على طلب الاشتراك 🟢';
                $message = $data['message'] ?? "تمت الموافقة على طلب الاشتراك رقم #{$reqId}.";
                $screen = 'SUBSCRIPTION_DETAILS';
                $entityId = $reqId;
                break;

            case self::TYPE_SUBSCRIPTION_REJECTED:
                $reqId = $data['request_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'رفض طلب الاشتراك 🔴';
                $message = $data['message'] ?? "عذراً، تم رفض طلب الاشتراك رقم #{$reqId}.";
                $screen = 'SUBSCRIPTION_DETAILS';
                $entityId = $reqId;
                break;

            case self::TYPE_SUBSCRIPTION_PAYMENT_REQ:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'مطلوب سداد الاشتراك 💳';
                $message = $data['message'] ?? "تمت الترقية، يرجى سداد المبلغ ({$amount} ر.س) لإكمال الاشتراك.";
                $screen = 'PAYMENT_SCREEN';
                break;

            // --- العمليات المالية والمحفظة ---
            case self::TYPE_RECHARGE_APPROVED:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'شحن المحفظة 💰';
                $message = $data['message'] ?? "تمت إضافة مبلغ ({$amount} ر.س) إلى محفظتك بنجاح.";
                $screen = 'WALLET';
                break;

            case self::TYPE_RECHARGE_REJECTED:
                $title = $data['title'] ?? 'رفض شحن المحفظة ⚠️';
                $message = $data['message'] ?? 'تم رفض طلب شحن المحفظة، يرجى مراجعة التفاصيل.';
                $screen = 'WALLET';
                break;

            case self::TYPE_WITHDRAWAL_APPROVED:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'سحب الرصيد 💵';
                $message = $data['message'] ?? "تمت الموافقة على سحب مبلغ ({$amount} ر.س) إلى حسابك البنكي.";
                $screen = 'WALLET';
                break;

            case self::TYPE_WITHDRAWAL_REJECTED:
                $title = $data['title'] ?? 'رفض سحب الرصيد ⚠️';
                $message = $data['message'] ?? 'تم رفض طلب سحب الرصيد، يرجى التواصل مع الدعم.';
                $screen = 'WALLET';
                break;

            case self::TYPE_INVOICE_GENERATED:
                $invNo = $data['invoice_number'] ?? $entityId ?? '';
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'فاتورة جديدة 📄';
                $message = $data['message'] ?? "تم إصدار فاتورة جديدة برقم #{$invNo} بمبلغ ({$amount} ر.س).";
                $screen = 'INVOICE_DETAILS';
                $entityId = $invNo;
                break;

            // --- حسابات السائقين والإدارة ---
            case self::TYPE_DRIVER_ACCOUNT_APPROVED:
                $title = $data['title'] ?? 'اعتماد حساب السائق 🥳';
                $message = $data['message'] ?? 'تهانينا! تمت الموافقة على حسابك ويمكنك الآن البدء بتلقي الرحلات.';
                $screen = 'DRIVER_HOME';
                break;

            case self::TYPE_DRIVER_ACCOUNT_REJECTED:
                $title = $data['title'] ?? 'تحديث بيانات الحساب ⚠️';
                $message = $data['message'] ?? 'نأمل مراجعة وتحديث بيانات التسجيل والمستندات المرفقة.';
                $screen = 'DRIVER_PROFILE';
                break;
        }

        return [
            'title'      => $title,
            'message'    => $message,
            'type'       => $type,
            'action_url' => $actionUrl,
            'entity_id'  => $entityId,
            'screen'     => $screen,
            'payload'    => array_merge([
                'type'      => $type,
                'entity_id' => (string) $entityId,
                'screen'    => $screen,
            ], $extraPayload),
        ];
    }
}
