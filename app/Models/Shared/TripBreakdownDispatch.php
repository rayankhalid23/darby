<?php

namespace App\Models\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\Child;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripBreakdownDispatch extends Model
{
    protected $table = 'trip_breakdown_dispatches';

    public const STATUS_PENDING       = 'pending';
    public const STATUS_BROADCASTED   = 'broadcasted';
    public const STATUS_ACCEPTED      = 'accepted';
    public const STATUS_DECLINED_ALL  = 'declined_all';
    public const STATUS_EXPIRED       = 'expired';
    public const STATUS_UNRESOLVED    = 'unresolved';
    public const STATUS_COMPLETED     = 'completed';
    public const STATUS_CANCELLED     = 'cancelled';

    protected $fillable = [
        'trip_id',
        'original_driver_id',
        'substitute_driver_id',
        'substitute_trip_id',
        'status',
        'breakdown_lat',
        'breakdown_lng',
        'reason',
        'stranded_children_ids',
        'stranded_children_count',
        'candidate_driver_ids',
        'rejected_driver_ids',
        'trip_fare_amount',
        'financial_settled',
        'settled_at',
        'dispatched_at',
        'accepted_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'breakdown_lat'           => 'float',
        'breakdown_lng'           => 'float',
        'stranded_children_ids'   => 'array',
        'stranded_children_count' => 'integer',
        'candidate_driver_ids'    => 'array',
        'rejected_driver_ids'     => 'array',
        'trip_fare_amount'        => 'decimal:2',
        'financial_settled'       => 'boolean',
        'settled_at'              => 'datetime',
        'dispatched_at'           => 'datetime',
        'accepted_at'             => 'datetime',
        'completed_at'            => 'datetime',
        'expires_at'              => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function originalDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'original_driver_id');
    }

    public function substituteDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'substitute_driver_id');
    }

    public function substituteTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'substitute_trip_id');
    }
}
