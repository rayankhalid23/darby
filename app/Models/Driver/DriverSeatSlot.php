<?php

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSeatSlot extends Model
{
    use HasFactory;

    protected $table = 'driver_seat_slots';

    const MORNING_GO       = 'morning_go';
    const MORNING_RETURN   = 'morning_return';
    const AFTERNOON_GO     = 'afternoon_go';
    const AFTERNOON_RETURN = 'afternoon_return';

    const ALL_SLOTS = [
        self::MORNING_GO,
        self::MORNING_RETURN,
        self::AFTERNOON_GO,
        self::AFTERNOON_RETURN,
    ];

    protected $fillable = [
        'driver_id',
        'slot',
        'total_seats',
        'reserved_seats',
    ];

    protected $casts = [
        'total_seats'    => 'integer',
        'reserved_seats' => 'integer',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * الخاصية المحسوبة للمقاعد المتاحة (تضمن عدم إرجاع قيم سالبة)
     */
    public function getAvailableSeatsAttribute(): int
    {
        return max(0, $this->total_seats - $this->reserved_seats);
    }

    /**
     * فلتر المقاعد المتاحة (available >= minSeats)
     */
    public function scopeWithAvailableSeats($query, int $minSeats = 1)
    {
        return $query->whereRaw('(total_seats - reserved_seats) >= ?', [$minSeats]);
    }
}