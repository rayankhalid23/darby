<?php

namespace App\Models\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\Address;
use App\Models\Parent\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationChangeRequest extends Model
{
    protected $table = 'location_change_requests';

    const POINT_TYPE_PICKUP  = 'pickup';
    const POINT_TYPE_DROPOFF = 'dropoff';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'active_subscription_id',
        'child_id',
        'parent_id',
        'driver_id',
        'point_type',
        'new_address_id',
        'new_lat',
        'new_lng',
        'new_label',
        'status',
        'rejection_reason',
        'responded_at',
    ];

    protected $casts = [
        'new_lat'      => 'float',
        'new_lng'      => 'float',
        'responded_at' => 'datetime',
    ];

    public function activeSubscription(): BelongsTo
    {
        return $this->belongsTo(ActiveSubscription::class, 'active_subscription_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function newAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'new_address_id');
    }
}
