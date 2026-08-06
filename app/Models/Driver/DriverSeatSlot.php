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
     * يحول (timing, direction) إلى قائمة الـ shift slots المطلوبة، بترتيب ثابت
     * (morning_go, morning_return, afternoon_go, afternoon_return) — أول عنصر هو الـ slot الأساسي (Primary).
     */
    public static function resolveSlots(string $timing, string $direction): array
    {
        $timingUp = strtoupper($timing);
        $dirLow   = strtolower($direction);

        $requiredSlots = [];

        if (in_array($timingUp, ['MORNING', 'BOTH'])) {
            if (in_array($dirLow, ['go', 'both']))     $requiredSlots[] = self::MORNING_GO;
            if (in_array($dirLow, ['return', 'both']))  $requiredSlots[] = self::MORNING_RETURN;
        }
        if (in_array($timingUp, ['EVENING', 'AFTERNOON', 'BOTH'])) {
            if (in_array($dirLow, ['go', 'both']))     $requiredSlots[] = self::AFTERNOON_GO;
            if (in_array($dirLow, ['return', 'both']))  $requiredSlots[] = self::AFTERNOON_RETURN;
        }

        return $requiredSlots;
    }

    public static function slotLabels(): array
    {
        return [
            self::MORNING_GO       => 'صباحي - ذهاب',
            self::MORNING_RETURN   => 'صباحي - إياب',
            self::AFTERNOON_GO     => 'مسائي - ذهاب',
            self::AFTERNOON_RETURN => 'مسائي - إياب',
        ];
    }

    /**
     * هل هذا الـ slot من نوع "ذهاب" (منازل -> مدرسة) أم "إياب" (مدرسة -> منازل)؟
     */
    public static function isGoSlot(string $slot): bool
    {
        return in_array($slot, [self::MORNING_GO, self::AFTERNOON_GO], true);
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