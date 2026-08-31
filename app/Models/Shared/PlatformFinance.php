<?php

namespace App\Models\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformFinance extends Model
{
    use HasFactory;

    protected $table = 'platform_finances';

    public const STATUS_HELD               = 'held';
    public const STATUS_COMPLETED          = 'completed';
    public const STATUS_REFUNDED           = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_DISPUTED           = 'disputed';

    public const NOMINAL_FUEL_COMPENSATION = 3.00; // 3 دنانير تعويض رمزي للسائق عن المشوار والوقود عند الإلغاء بعد التحرك

    protected $fillable = [
        'subscription_request_id',
        'active_subscription_id',
        'trip_id',
        'parent_id',
        'driver_id',
        'total_amount',
        'platform_commission_rate',
        'platform_commission_amount',
        'driver_net_amount',
        'expected_trips_count',
        'settled_trips_count',
        'settled_amount',
        'refunded_amount',
        'compensation_fee',
        'status',
        'held_at',
        'settled_at',
        'refunded_at',
        'notes',
    ];

    protected $casts = [
        'total_amount'               => 'decimal:2',
        'platform_commission_rate'   => 'decimal:2',
        'platform_commission_amount' => 'decimal:2',
        'driver_net_amount'          => 'decimal:2',
        'expected_trips_count'       => 'integer',
        'settled_trips_count'        => 'integer',
        'settled_amount'             => 'decimal:2',
        'refunded_amount'            => 'decimal:2',
        'compensation_fee'           => 'decimal:2',
        'held_at'                    => 'datetime',
        'settled_at'                 => 'datetime',
        'refunded_at'                => 'datetime',
    ];

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function activeSubscription(): BelongsTo
    {
        return $this->belongsTo(ActiveSubscription::class, 'active_subscription_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
