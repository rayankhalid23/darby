<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'admin_name',
        'admin_role',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'result',
        'reason',
        'changes',
        'created_at',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'action_label',
        'action_group',
    ];

    /**
     * خريطة ترجمة وتصنيف الإجراءات (Actions Map)
     */
    public static array $actionMap = [
        // ① قرارات (Decisions)
        'approve_driver'              => ['label' => 'قبول وتفعيل سائق', 'group' => 'decision'],
        'reject_driver'               => ['label' => 'رفض سائق', 'group' => 'decision'],
        'approve_driver_change'       => ['label' => 'قبول تعديل بيانات سائق', 'group' => 'decision'],
        'reject_driver_change'        => ['label' => 'رفض تعديل بيانات سائق', 'group' => 'decision'],
        'review_complaint'            => ['label' => 'مراجعة شكوى', 'group' => 'decision'],
        'approve_withdrawal'          => ['label' => 'قبول طلب سحب', 'group' => 'decision'],
        'reject_withdrawal'           => ['label' => 'رفض طلب سحب', 'group' => 'decision'],
        'complete_recharge'           => ['label' => 'تأكيد شحن رصيد', 'group' => 'decision'],
        'fail_recharge'               => ['label' => 'رفض شحن رصيد', 'group' => 'decision'],
        'resolve_dispute'             => ['label' => 'حل نزاع مالي', 'group' => 'decision'],
        'approve_admin_email'         => ['label' => 'تأكيد تغيير بريد مشرف', 'group' => 'decision'],
        'reject_admin_email'          => ['label' => 'رفض تغيير بريد مشرف', 'group' => 'decision'],

        // ② تعديلات بيانات (Updates)
        'update_driver'               => ['label' => 'تعديل بيانات سائق', 'group' => 'update'],
        'create_admin'                => ['label' => 'إضافة مشرف جديد', 'group' => 'update'],
        'update_admin'                => ['label' => 'تعديل بيانات مشرف', 'group' => 'update'],
        'delete_admin'                => ['label' => 'حذف مشرف', 'group' => 'update'],
        'create_school'               => ['label' => 'إضافة مدرسة جديدة', 'group' => 'update'],
        'update_school'               => ['label' => 'تعديل بيانات مدرسة', 'group' => 'update'],
        'delete_school'               => ['label' => 'حذف مدرسة', 'group' => 'update'],
        'create_municipality'         => ['label' => 'إضافة بلدية جديدة', 'group' => 'update'],
        'update_municipality'         => ['label' => 'تعديل بلدية', 'group' => 'update'],
        'delete_municipality'         => ['label' => 'حذف بلدية', 'group' => 'update'],
        'create_sub_municipality'     => ['label' => 'إضافة محلة جديدة', 'group' => 'update'],
        'update_sub_municipality'     => ['label' => 'تعديل محلة', 'group' => 'update'],
        'delete_sub_municipality'     => ['label' => 'حذف محلة', 'group' => 'update'],
        'create_zone'                 => ['label' => 'إضافة منطقة جديدة', 'group' => 'update'],
        'update_zone'                 => ['label' => 'تعديل منطقة', 'group' => 'update'],
        'delete_zone'                 => ['label' => 'حذف منطقة', 'group' => 'update'],
        'delete_driver_review'        => ['label' => 'حذف تقييم سائق', 'group' => 'update'],

        // ③ عمليات تنفيذية (Operations)
        'release_escrows'             => ['label' => 'تحرير مبالغ الضمان (Escrow)', 'group' => 'operation'],
        'settle_contract_monthly'     => ['label' => 'تسوية العقد الشهرية', 'group' => 'operation'],
        'terminate_contract_mid_month'=> ['label' => 'إنهاء العقد خلال الشهر', 'group' => 'operation'],
        'cancel_trip_with_matrix'     => ['label' => 'إلغاء رحلة بالمصفوفة المالية', 'group' => 'operation'],
        'generate_daily_trips'        => ['label' => 'توليد الرحلات اليومية', 'group' => 'operation'],
    ];

    /**
     * التسمية العربية الصريحة للإجراء
     */
    public function getActionLabelAttribute(): string
    {
        return self::$actionMap[$this->action]['label'] ?? $this->action;
    }

    /**
     * عائلة الإجراء (decision / update / operation)
     */
    public function getActionGroupAttribute(): string
    {
        return self::$actionMap[$this->action]['group'] ?? 'operation';
    }

    /**
     * علاقة المشرف
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * ضمان أن التغييرات ترجع دائماً كمصفوفة فارغة [] بدلاً من null
     */
    public function getChangesAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * منع التعديل على السجل (Immutable)
     */
    public static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            throw new \RuntimeException('لا يمكن تعديل سجلات التدقيق (Immutable Audit Logs).');
        });

        static::deleting(function ($model) {
            throw new \RuntimeException('لا يمكن حذف سجلات التدقيق (Immutable Audit Logs).');
        });
    }
}
