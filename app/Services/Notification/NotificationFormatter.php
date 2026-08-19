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
    public const TYPE_TRIP_READY                = 'trip_ready';
    public const TYPE_TRIP_UPCOMING             = 'trip_upcoming';
    public const TYPE_TRIP_SUSPENDED            = 'trip_suspended';
    public const TYPE_CHILD_SKIPPED             = 'child_skipped';
    public const TYPE_MANUAL_PICKUP_CONFIRMED   = 'manual_pickup_confirmed';
    public const TYPE_DRIVER_ABSENCE            = 'driver_absence';
    public const TYPE_CHILD_ABSENT              = 'child_absent';
    public const TYPE_CHILD_SKIP                = 'child_skip';
    public const TYPE_CHILD_DROPOFF_FAILED      = 'child_dropoff_failed';
    public const TYPE_CHILD_DIRECT_PARENT_HANDLING = 'child_direct_parent_handling';

    public const TYPE_CONTRACT_CREATED          = 'contract_created';
    public const TYPE_CONTRACT_SIGNED           = 'contract_signed';
    public const TYPE_CONTRACT_APPROVED         = 'contract_approved';
    public const TYPE_CONTRACT_REJECTED         = 'contract_rejected';

    public const TYPE_SUBSCRIPTION_SUBMITTED    = 'subscription_submitted';
    public const TYPE_SUBSCRIPTION_APPROVED     = 'subscription_approved';
    public const TYPE_SUBSCRIPTION_REJECTED     = 'subscription_rejected';
    public const TYPE_SUBSCRIPTION_PAYMENT_REQ  = 'subscription_payment_required';
    public const TYPE_NEW_SUBSCRIPTION_REQUEST  = 'new_subscription_request';
    public const TYPE_REQUEST_ACCEPTED          = 'request_accepted';
    public const TYPE_REQUEST_REJECTED          = 'request_rejected';

    public const TYPE_RECHARGE_APPROVED         = 'recharge_approved';
    public const TYPE_RECHARGE_REJECTED         = 'recharge_rejected';
    public const TYPE_WITHDRAWAL_APPROVED       = 'withdrawal_approved';
    public const TYPE_WITHDRAWAL_REJECTED       = 'withdrawal_rejected';
    public const TYPE_INVOICE_GENERATED         = 'invoice_generated';
    public const TYPE_SETTLEMENT_PAID           = 'settlement_paid';
    public const TYPE_SETTLEMENT_RECEIVED       = 'settlement_received';
    public const TYPE_SETTLEMENT_OVERDUE        = 'settlement_overdue';
    public const TYPE_SETTLEMENT_WARNING        = 'settlement_warning';

    public const TYPE_DRIVER_ACCOUNT_APPROVED   = 'driver_account_approved';
    public const TYPE_DRIVER_ACCOUNT_REJECTED   = 'driver_account_rejected';
    public const TYPE_GENERAL_ANNOUNCEMENT      = 'general_announcement';

    // --- أنواع خاصة بلوحة تحكم الأدمن ---
    public const TYPE_NEW_DRIVER_REGISTERED     = 'new_driver_registered';
    public const TYPE_NEW_COMPLAINT_SUBMITTED   = 'new_complaint_submitted';

    /**
     * صياغة كائن الإشعار الموحد بناءً على النوع والتفاصيل
     */
    public static function format(string $type, array $data = []): array
    {
        $title = $data['title'] ?? 'تنبيه جديد';
        $message = $data['message'] ?? '';
        $actionUrl = $data['action_url'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $entityType = $data['entity_type'] ?? null;
        $screen = $data['screen'] ?? 'HOME';
        $action = $data['action'] ?? 'open';
        $extraPayload = $data['payload'] ?? [];

        switch ($type) {
            // --- الرحلات ---
            case self::TYPE_TRIP_STARTED:
                $title = $data['title'] ?? 'بدء الرحلة 🚌';
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $message = $data['message'] ?? "انطلقت الرحلة رقم #{$tripId}، السائق في الطريق الآن.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_DRIVER_ARRIVED:
                $title = $data['title'] ?? 'وصول السائق 📍';
                $message = $data['message'] ?? 'وصل السائق إلى نقطة الانطلاق لتسلم الطلاب.';
                $screen = 'TRIP_TRACKING';
                $entityType = 'trip';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_PICKED_UP:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'ركوب الطالب 🎒';
                $message = $data['message'] ?? "تم صعود الطالب ({$childName}) إلى الحافلة بنجاح.";
                $screen = 'TRIP_LIVE';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_DROPPED_OFF:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'وصول الطالب 🏡';
                $message = $data['message'] ?? "تم نزول الطالب ({$childName}) ووصوله بسلام إلى وجهته.";
                $screen = 'TRIP_LIVE';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_STUDENT_ABSENT:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'تسجيل غياب ⚠️';
                $message = $data['message'] ?? "تم تسجيل غياب الطالب ({$childName}) عن الرحلة الحالية.";
                $screen = 'ATTENDANCE_LOG';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_TRIP_COMPLETED:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'اكتملت الرحلة ✅';
                $message = $data['message'] ?? "تمت الرحلة رقم #{$tripId} بنجاح ووصل جميع الطلاب بسلام.";
                $screen = 'TRIP_SUMMARY';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_TRIP_CANCELLED:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'إلغاء الرحلة ❌';
                $message = $data['message'] ?? "تم إلغاء الرحلة رقم #{$tripId}.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_TRIP_READY:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'الرحلة جاهزة 🚌';
                $message = $data['message'] ?? "تم تجهيز رحلتك رقم #{$tripId} لليوم.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_TRIP_UPCOMING:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'رحلة اليوم قادمة 🕒';
                $message = $data['message'] ?? "رحلة اليوم على وشك الانطلاق، يرجى تجهيز طفلك.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_TRIP_SUSPENDED:
                $tripId = $data['trip_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'تعليق الرحلة ⚠️';
                $message = $data['message'] ?? "تم تعليق الرحلة رقم #{$tripId} مؤقتاً، سيتم إعلامك بالمستجدات.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $tripId;
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_SKIPPED:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'تم تخطي المحطة ⚠️';
                $message = $data['message'] ?? "تم تخطي محطة الطالب ({$childName}) في الرحلة الحالية.";
                $screen = 'TRIP_LIVE';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_MANUAL_PICKUP_CONFIRMED:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'تأكيد استلام يدوي 🖐️';
                $message = $data['message'] ?? "قام ولي الأمر بتأكيد استلام الطالب ({$childName}) يدوياً.";
                $screen = 'TRIP_LIVE';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_ABSENT:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? '⚠️ غياب الطفل';
                $message = $data['message'] ?? "لم يجد السائق الطفل ({$childName}) في محطة الانتظار، تم تسجيل غيابه.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_SKIP:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? '⚠️ تجاوز المحطة';
                $message = $data['message'] ?? "انتهى وقت الانتظار دون استجابة، تحركت الحافلة وتجاوزت محطة ({$childName}).";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_DROPOFF_FAILED:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? '🚨 تعذر تسليم الطفل';
                $message = $data['message'] ?? "تعذر على السائق تسليم ({$childName}) في محطة النزول، يرجى التواصل الفوري.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_CHILD_DIRECT_PARENT_HANDLING:
                $childName = $data['child_name'] ?? 'الطالب';
                $title = $data['title'] ?? 'ℹ️ استلام مباشر من ولي الأمر';
                $message = $data['message'] ?? "تم تسليم ({$childName}) مباشرة لولي الأمر خارج الإجراء المعتاد.";
                $screen = 'TRIP_DETAILS';
                $entityType = 'trip';
                $entityId = $entityId ?? $data['trip_id'] ?? '';
                $action = 'open_trip';
                break;

            case self::TYPE_DRIVER_ABSENCE:
                $title = $data['title'] ?? 'غياب السائق ⚠️';
                $message = $data['message'] ?? 'السائق المسؤول عن رحلتك غائب اليوم، سيتم إعلامك بالبديل إن وجد.';
                $screen = 'TRIP_DETAILS';
                $entityType = 'driver_absence';
                $action = 'open';
                break;

            // --- العقود ---
            case self::TYPE_CONTRACT_CREATED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'عقد جديد 📝';
                $message = $data['message'] ?? "تم إنشاء العقد رقم #{$contractNo} وبانتظار التوقيع.";
                $screen = 'CONTRACT_DETAILS';
                $entityType = 'contract';
                $entityId = $data['contract_id'] ?? $contractNo;
                $action = 'open_contract';
                break;

            case self::TYPE_CONTRACT_SIGNED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'توقيع العقد ✍️';
                $message = $data['message'] ?? "تم توقيع العقد رقم #{$contractNo} بنجاح.";
                $screen = 'CONTRACT_DETAILS';
                $entityType = 'contract';
                $entityId = $data['contract_id'] ?? $contractNo;
                $action = 'open_contract';
                break;

            case self::TYPE_CONTRACT_APPROVED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'اعتماد العقد 🎉';
                $message = $data['message'] ?? "تمت الموافقة واعتماد العقد رقم #{$contractNo}.";
                $screen = 'CONTRACT_DETAILS';
                $entityType = 'contract';
                $entityId = $data['contract_id'] ?? $contractNo;
                $action = 'open_contract';
                break;

            case self::TYPE_CONTRACT_REJECTED:
                $contractNo = $data['contract_number'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'رفض العقد ⚠️';
                $message = $data['message'] ?? "تعذر قبول أو اعتماد العقد رقم #{$contractNo}.";
                $screen = 'CONTRACT_DETAILS';
                $entityType = 'contract';
                $entityId = $data['contract_id'] ?? $contractNo;
                $action = 'open_contract';
                break;

            // --- طلبات الاشتراكات ---
            case self::TYPE_NEW_SUBSCRIPTION_REQUEST:
                $reqId = $data['request_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'طلب اشتراك جديد 🆕';
                $message = $data['message'] ?? "لديك طلب اشتراك جديد رقم #{$reqId} بانتظار المراجعة.";
                $screen = 'SUBSCRIPTION_DETAILS';
                $entityType = 'subscription_request';
                $entityId = $reqId;
                $action = 'open_subscription_request';
                break;

            case self::TYPE_REQUEST_ACCEPTED:
            case self::TYPE_SUBSCRIPTION_APPROVED:
                $reqId = $data['request_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'تم قبول طلب الاشتراك 🟢';
                $message = $data['message'] ?? "تمت الموافقة على طلب الاشتراك رقم #{$reqId}.";
                $screen = 'SUBSCRIPTION_DETAILS';
                $entityType = 'subscription_request';
                $entityId = $reqId;
                $action = 'open_subscription_request';
                break;

            case self::TYPE_REQUEST_REJECTED:
            case self::TYPE_SUBSCRIPTION_REJECTED:
                $reqId = $data['request_id'] ?? $entityId ?? '';
                $title = $data['title'] ?? 'تم رفض طلب الاشتراك 🔴';
                $message = $data['message'] ?? "عذراً، تم رفض طلب الاشتراك رقم #{$reqId}.";
                $screen = 'SUBSCRIPTION_DETAILS';
                $entityType = 'subscription_request';
                $entityId = $reqId;
                $action = 'open_subscription_request';
                break;

            case self::TYPE_SUBSCRIPTION_PAYMENT_REQ:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'مطلوب سداد الاشتراك 💳';
                $message = $data['message'] ?? "تمت الترقية، يرجى سداد المبلغ ({$amount} ر.س) لإكمال الاشتراك.";
                $screen = 'PAYMENT_SCREEN';
                $entityType = 'subscription_request';
                $action = 'open_subscription_request';
                break;

            // --- العمليات المالية والمحفظة ---
            case self::TYPE_RECHARGE_APPROVED:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'شحن المحفظة 💰';
                $message = $data['message'] ?? "تمت إضافة مبلغ ({$amount} ر.س) إلى محفظتك بنجاح.";
                $screen = 'WALLET';
                $entityType = 'wallet';
                $action = 'open_wallet';
                break;

            case self::TYPE_RECHARGE_REJECTED:
                $title = $data['title'] ?? 'رفض شحن المحفظة ⚠️';
                $message = $data['message'] ?? 'تم رفض طلب شحن المحفظة، يرجى مراجعة التفاصيل.';
                $screen = 'WALLET';
                $entityType = 'wallet';
                $action = 'open_wallet';
                break;

            case self::TYPE_WITHDRAWAL_APPROVED:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'سحب الرصيد 💵';
                $message = $data['message'] ?? "تمت الموافقة على سحب مبلغ ({$amount} ر.س) إلى حسابك البنكي.";
                $screen = 'WALLET';
                $entityType = 'wallet';
                $action = 'open_wallet';
                break;

            case self::TYPE_WITHDRAWAL_REJECTED:
                $title = $data['title'] ?? 'رفض سحب الرصيد ⚠️';
                $message = $data['message'] ?? 'تم رفض طلب سحب الرصيد، يرجى التواصل مع الدعم.';
                $screen = 'WALLET';
                $entityType = 'wallet';
                $action = 'open_wallet';
                break;

            case self::TYPE_INVOICE_GENERATED:
                $invNo = $data['invoice_number'] ?? $entityId ?? '';
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'فاتورة جديدة 📄';
                $message = $data['message'] ?? "تم إصدار فاتورة جديدة برقم #{$invNo} بمبلغ ({$amount} ر.س).";
                $screen = 'INVOICE_DETAILS';
                $entityType = 'invoice';
                $entityId = $data['invoice_id'] ?? $invNo;
                $action = 'open_invoice';
                break;

            case self::TYPE_SETTLEMENT_PAID:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'تمت التسوية المالية 💰';
                $message = $data['message'] ?? "تم دفع مبلغ التسوية الشهرية ({$amount} ر.س) بنجاح.";
                $screen = 'INVOICE_DETAILS';
                $entityType = 'invoice';
                $entityId = $data['invoice_id'] ?? $entityId;
                $action = 'open_invoice';
                break;

            case self::TYPE_SETTLEMENT_RECEIVED:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'استلام دفعة تسوية 💵';
                $message = $data['message'] ?? "تم استلام مبلغ التسوية الشهرية ({$amount} ر.س) في محفظتك.";
                $screen = 'WALLET';
                $entityType = 'invoice';
                $entityId = $data['invoice_id'] ?? $entityId;
                $action = 'open_wallet';
                break;

            case self::TYPE_SETTLEMENT_OVERDUE:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'تسوية متأخرة ⚠️';
                $message = $data['message'] ?? "تسوية بمبلغ ({$amount} ر.س) متأخرة عن السداد، يرجى المبادرة بالدفع.";
                $screen = 'INVOICE_DETAILS';
                $entityType = 'invoice';
                $entityId = $data['invoice_id'] ?? $entityId;
                $action = 'open_invoice';
                break;

            case self::TYPE_SETTLEMENT_WARNING:
                $amount = $data['amount'] ?? '';
                $title = $data['title'] ?? 'تذكير بالتسوية القادمة ⏰';
                $message = $data['message'] ?? "تسوية بمبلغ ({$amount} ر.س) مستحقة قريباً.";
                $screen = 'WALLET';
                $entityType = 'invoice';
                $entityId = $data['invoice_id'] ?? $entityId;
                $action = 'open_wallet';
                break;

            // --- حسابات السائقين والإدارة ---
            case self::TYPE_DRIVER_ACCOUNT_APPROVED:
                $title = $data['title'] ?? 'اعتماد حساب السائق 🥳';
                $message = $data['message'] ?? 'تهانينا! تمت الموافقة على حسابك ويمكنك الآن البدء بتلقي الرحلات.';
                $screen = 'DRIVER_HOME';
                $entityType = 'driver_profile_change';
                $action = 'open';
                break;

            case self::TYPE_DRIVER_ACCOUNT_REJECTED:
                $title = $data['title'] ?? 'تحديث بيانات الحساب ⚠️';
                $message = $data['message'] ?? 'نأمل مراجعة وتحديث بيانات التسجيل والمستندات المرفقة.';
                $screen = 'DRIVER_PROFILE';
                $entityType = 'driver_profile_change';
                $action = 'open';
                break;

            // --- إشعارات لوحة تحكم الأدمن ---
            case self::TYPE_NEW_DRIVER_REGISTERED:
                $driverName = $data['driver_name'] ?? 'سائق جديد';
                $title = $data['title'] ?? 'تسجيل سائق جديد 🚐';
                $message = $data['message'] ?? "قام السائق ({$driverName}) بالتسجيل وبانتظار المراجعة.";
                $screen = 'ADMIN_DRIVER_REVIEW';
                $entityType = 'driver';
                $action = 'open_driver_review';
                break;

            case self::TYPE_NEW_COMPLAINT_SUBMITTED:
                $title = $data['title'] ?? 'شكوى جديدة 📩';
                $message = $data['message'] ?? 'تم تقديم شكوى جديدة وتحتاج إلى مراجعة.';
                $screen = 'ADMIN_COMPLAINT_DETAILS';
                $entityType = 'complaint';
                $action = 'open_complaint';
                break;
        }

        return [
            'title'       => $title,
            'message'     => $message,
            'type'        => $type,
            'action_url'  => $actionUrl,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'screen'      => $screen,
            'action'      => $action,
            'payload'     => array_merge([
                'type'        => $type,
                'entity_type' => $entityType,
                'entity_id'   => (string) $entityId,
                'screen'      => $screen,
                'action'      => $action,
            ], $extraPayload),
        ];
    }
}
