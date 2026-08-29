<?php

namespace App\Models\Shared;

use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\User;
use App\Models\Parent\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveSubscription extends Model
{
    protected $table = 'active_subscriptions';

    protected $fillable = [
        'subscription_request_id',
        'child_id',
        'driver_id',
        'route_id',
        'parent_id',
        'pickup_lat',
        'pickup_lng',
        'pickup_label',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_label',
        'pickup_time',
        'dropoff_time',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'pickup_lat'  => 'float',
        'pickup_lng'  => 'float',
        'dropoff_lat' => 'float',
        'dropoff_lng' => 'float',
    ];

    // ============================================================
    // العلاقات
    // ============================================================

    /**
     * للتوافقية مع الاستدعاءات القديمة: يعيد طلب الاشتراك كعقد معتمد
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    /**
     * طلب الاشتراك الأصلي — مصدر كل بيانات الاتفاق (المدة، السعر، الفترة، الاتجاه)
     */
    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * احتساب الحالة الفعلية الدقيقة للاشتراك
     */
    public function resolveState(): array
    {
        $req = $this->subscriptionRequest;
        if ($req) {
            return $req->resolveState($this->child, $this);
        }

        $status = strtolower($this->status ?? 'active');
        return [
            'state'       => $status,
            'status'      => $status,
            'state_label' => match($status) {
                'active'    => 'ساري ومفعل',
                'paused'    => 'متوقف مؤقتاً',
                'cancelled' => 'ملغي',
                'completed' => 'مكتمل',
                default     => $status,
            },
            'status_text' => $status,
            'is_active'   => $status === 'active',
        ];
    }
}