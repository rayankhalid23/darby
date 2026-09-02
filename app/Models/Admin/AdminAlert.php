<?php

namespace App\Models\Admin;

use App\Models\Driver\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAlert extends Model
{
    protected $table = 'admin_alerts';

    protected $fillable = [
        'driver_id',
        'risk_level',
        'actions_taken',
        'admin_message',
        'reasoning',
        'ai_metrics',
        'evaluated_reviews',
        'is_resolved',
        'alert_type',
        'title',
        'message',
        'severity',
        'action_required',
        'metadata',
        'is_read',
    ];

    protected $casts = [
        'actions_taken'     => 'array',
        'ai_metrics'        => 'array',
        'evaluated_reviews' => 'array',
        'metadata'          => 'array',
        'is_resolved'       => 'boolean',
        'is_read'           => 'boolean',
        'severity'          => 'integer',
    ];

    /**
     * علاقة التنبيه بالسائق المرتبط
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * نطاق جلب التنبيهات غير المقروءة
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * نطاق جلب التنبيهات غير المحسومة
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }
}
