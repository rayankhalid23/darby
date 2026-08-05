<?php

namespace App\Models\Shared;

use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Parent\School;
use App\Models\Parent\Child;
use App\Models\Shared\Contract; // تأكد من استيراد كود العقد
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionRequest extends Model
{

    protected $table = 'requests';
    public $timestamps = false;


    // الثوابت لتجنب الخطأ في كتابة النصوص
    const DIRECTION_GO     = 'go';
    const DIRECTION_RETURN = 'return';
    const DIRECTION_BOTH   = 'both';

    const TIMING_MORNING = 'MORNING';
    const TIMING_EVENING = 'EVENING';
    const TIMING_BOTH    = 'BOTH';

    const STATUS_PENDING   = 'pending';
    const STATUS_ACQUIRED  = 'acquired';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    const ACCEPTANCE_MODE_ALL        = 'all';
    const ACCEPTANCE_MODE_INDIVIDUAL = 'individual';

    protected $fillable = [
        'parent_id',
        'driver_id',
        'school_id',
        'subscription_type',
        'direction',
        'timing',
        'start_date',
        'end_date',
        'days_count',
        'total_price',
        'pickup_time',
        'dropoff_time',
        'max_waiting_time',
        'status',
        'rejection_reason',
        'notes',
        'children_count',
        'children_acceptance_mode',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_price'  => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // ============================================================
    // العلاقات
    // ============================================================

    /**
     * الأطفال المرتبطون بهذا الطلب
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'request_children', 'request_id', 'child_id')
                    ->withPivot([
                        'pickup_address_id',
                        'dropoff_address_id',
                        'home_lat',
                        'home_lng',
                        'home_label',
                        'school_lat',
                        'school_lng',
                        'school_label',
                        'price_per_child',
                        'child_notes'
                    ])
                    ->withTimestamps();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class, 'subscription_request_id');
    }

    // ============================================================
    // Scopes للفلترة
    // ============================================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeByParent(Builder $query, int $parentId): Builder
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeByDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    // ============================================================
    // دوال مساعدة (Accessors)
    // ============================================================

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'معلق — بانتظار رد السائق',
            'acquired'  => 'تم الاستحواذ — السائق قيد المراجعة',
            'accepted'  => 'تم القبول — جارٍ إعداد العقد',
            'rejected'  => 'تم الرفض من السائق',
            'cancelled' => 'ملغي تلقائياً',
            default     => 'حالة غير معروفة',
        };
    }

    public function getDirectionTextAttribute(): string
    {
        return match($this->direction) {
            self::DIRECTION_GO     => 'ذهاب فقط',
            self::DIRECTION_RETURN => 'إياب فقط',
            self::DIRECTION_BOTH   => 'ذهاب وإياب',
            default                => $this->direction,
        };
    }

    public function getSubscriptionTypeTextAttribute(): string
    {
        return match($this->subscription_type) {
            'monthly' => 'اشتراك شهري',
            'daily'   => 'اشتراك يومي',
            default   => $this->subscription_type,
        };
    }

    public function getTimingTextAttribute(): string
    {
        return match($this->timing) {
            self::TIMING_MORNING => 'صباحي',
            self::TIMING_EVENING => 'مسائي',
            self::TIMING_BOTH    => 'صباحي ومسائي',
            default              => $this->timing,
        };
    }
}