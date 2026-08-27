<?php

namespace App\Models\Shared;

use App\Models\Parent\ParentModel;
use App\Models\Driver\Driver;
use App\Models\Parent\School;
use App\Models\Parent\Child;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionRequest extends Model
{
    protected $table = 'requests';
    
    // تم التصحيح إلى true. التعليق السابق ذكر أنه تم التفعيل ولكن القيمة كانت false مما يعطل حفظ تواريخ الإنشاء والتعديل.
    public $timestamps = false;

    // الثوابت لتجنب الخطأ في كتابة النصوص
    const DIRECTION_ONE_WAY_MORNING = 'one_way_morning';
    const DIRECTION_ONE_WAY_EVENING = 'one_way_evening';
    const DIRECTION_TWO_WAY         = 'two_way';

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
        'max_waiting_time',
        'status',
        'distance_km',
        'trip_price',
        'rejection_reason',
        'notes',
        'children_count',
        'children_acceptance_mode',
        'responded_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'total_price'  => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'responded_at' => 'datetime',
    ];

    // ============================================================
    // العلاقات
    // ============================================================

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'request_children', 'request_id', 'child_id')
                    ->withPivot([
                        'subscription_type',
                        'trip_direction',
                        'timing',
                        'start_date',
                        'end_date',
                        'working_days_count',
                     
                        'price_per_child',
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

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(ActiveSubscription::class, 'subscription_request_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'subscription_request_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_request_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DriverReview::class, 'subscription_request_id');
    }

    // ============================================================
    // Scopes للفلترة
    // ============================================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
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
    // Accessors
    // ============================================================

    public function getSubscriptionNumberAttribute(): string
    {
        $year = $this->created_at ? $this->created_at->format('Y') : now()->format('Y');
        
        // حماية من خطأ القيمة Null في حال استدعاء الدالة قبل عملية الـ Save في قاعدة البيانات
        $id = $this->id ?? 0;

        return 'DRBY-' . $year . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING   => 'معلق — بانتظار رد السائق',
            self::STATUS_ACQUIRED  => 'تم الاستحواذ — السائق قيد المراجعة',
            self::STATUS_ACCEPTED  => 'تم القبول — الاشتراك ساري',
            self::STATUS_REJECTED  => 'تم الرفض من السائق',
            self::STATUS_CANCELLED => 'ملغي تلقائياً',
            default                => 'حالة غير معروفة',
        };
    }

    public function getDirectionTextAttribute(): string
    {
        return match($this->direction) {
            self::DIRECTION_ONE_WAY_MORNING => 'ذهاب صباحي فقط',
            self::DIRECTION_ONE_WAY_EVENING => 'عودة مسائية فقط',
            self::DIRECTION_TWO_WAY         => 'ذهاب وإياب',
            default                         => $this->direction ?? 'غير محدد',
        };
    }

    public function getSubscriptionTypeTextAttribute(): string
    {
        return match($this->subscription_type) {
            'single_day' => 'اشتراك يوم واحد',
            'multi_day'  => 'اشتراك عدة أيام',
            'monthly'    => 'اشتراك شهري',
            'term'       => 'اشتراك فصل دراسي',
            'yearly'     => 'اشتراك سنوي',
            default      => $this->subscription_type ?? 'غير محدد',
        };
    }

    public function getTimingTextAttribute(): string
    {
        return match($this->timing) {
            self::TIMING_MORNING => 'صباحي',
            self::TIMING_EVENING => 'مسائي',
            self::TIMING_BOTH    => 'صباحي ومسائي',
            default              => $this->timing ?? 'غير محدد',
        };
    }
}