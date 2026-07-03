<?php

namespace App\Models\Shared;

use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveSubscription extends Model
{
    protected $table = 'active_subscriptions';

    protected $fillable = [
        'contract_id',
        'child_id',
        'driver_id',
        'parent_id',
        'pickup_lat',
        'pickup_lng',
        'pickup_label',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_label',
        'pickup_time',
        'dropoff_time',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }
}
