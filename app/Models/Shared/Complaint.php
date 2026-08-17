<?php

namespace App\Models\Shared;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    // تحديد اسم الجدول الصريح في قاعدة البيانات لضمان عدم حدوث تداخل
    protected $table = 'complaints';

    // الحقول المسموح بتعبئتها جماعياً لرفع مستوى الأمان وحماية البيانات
    protected $fillable = [
        'submitted_by',
        'against_type',
        'against_id',
        'driver_id',
        'trip_id',
        'description',
        'status',
        'resolved_by',
        'resolution_note',
        'action_taken',
        'action_details',
        'resolved_at',
        'ai_action',
        'ai_confidence',
        'ai_severity',
        'ai_analysis_message',
    ];

    // تحويل التواريخ تلقائياً إلى كائنات Carbon للتعامل معها باحترافية
    protected $casts = [
        'resolved_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'ai_confidence' => 'float',
        'ai_severity'   => 'integer',
    ];

    /**
     * علاقة الشكوى بالمستخدم (ولي الأمر) الذي قام بتقديمها
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'submitted_by');
    }

    /**
     * علاقة الشكوى بالسائق المشتكى عليه
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * علاقة الشكوى بالرحلة المعنية (تم التأكد من استدعاء الكلاس من نفس المجلد المشترك)
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    /**
     * علاقة الشكوى بالمشرف (الآدمن) الذي قام بحلها والبت فيها
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }
}