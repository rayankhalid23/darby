<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripEscrowHold extends Model
{
    protected $table = 'trip_escrow_holds';

    protected $fillable = [
        'trip_id',
        'parent_id',
        'driver_id',
        'amount',
        'hold_status',
        'held_at',
        'captured_at',
        'available_at',
        'disputed_at',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'held_at'      => 'datetime',
        'captured_at'  => 'datetime',
        'available_at' => 'datetime',
        'disputed_at'  => 'datetime',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }
}
