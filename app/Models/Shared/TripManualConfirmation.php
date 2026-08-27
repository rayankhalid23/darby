<?php

namespace App\Models\Shared;

use App\Models\Driver\Driver;
use App\Models\Parent\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripManualConfirmation extends Model
{
    protected $table = 'trip_manual_confirmations';

    const QUESTION_PICKUP  = 'pickup';
    const QUESTION_DROPOFF = 'dropoff';

    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_DENIED    = 'denied';

    protected $fillable = [
        'trip_id',
        'trip_stop_id',
        'child_id',
        'parent_id',
        'driver_id',
        'question_type',
        'target_status',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function tripStop(): BelongsTo
    {
        return $this->belongsTo(TripStop::class, 'trip_stop_id');
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
}
