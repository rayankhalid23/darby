<?php

namespace App\Models\Shared;

use App\Models\Parent\Child;
use App\Models\Parent\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStop extends Model
{
    protected $table = 'trip_stops';

    const TYPE_HOME   = 'home';
    const TYPE_SCHOOL = 'school';

    const STATUS_PENDING                 = 'pending';
    const STATUS_ABSENT_PRE              = 'absent_pre';
    const STATUS_ABSENT_LATE             = 'absent_late';
    const STATUS_BOARDED                 = 'boarded';
    const STATUS_DROPPED_OFF_SCHOOL      = 'dropped_off_school';
    const STATUS_DELIVERED_HOME          = 'delivered_home';
    const STATUS_SKIPPED_UNRESPONSIVE    = 'skipped_unresponsive';
    const STATUS_DROPOFF_FAILED          = 'dropoff_failed';
    const STATUS_DIRECT_PARENT_HANDLING  = 'direct_parent_handling';

    /**
     * حالات "غير نهائية" لطفل داخل الرحلة — وجود أي منها يمنع إنهاء الرحلة
     * (صمام أمان: لا يجوز نسيان طفل بحالة boarded داخل الحافلة).
     */
    const NON_FINAL_STATUSES = [self::STATUS_PENDING, self::STATUS_BOARDED];

    protected $fillable = [
        'trip_id',
        'route_stop_id',
        'stop_type',
        'child_id',
        'school_id',
        'lat',
        'lng',
        'label',
        'sequence_order',
        'status',
        'eta_minutes',
        'eta',
    ];

    protected $casts = [
        'lat'            => 'float',
        'lng'            => 'float',
        'sequence_order' => 'integer',
        'eta_minutes'    => 'integer',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'route_stop_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
