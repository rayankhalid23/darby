<?php

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shared\Trip;
use App\Models\User;

class DriverAbsence extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'driver_id',
        'absence_date',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'absence_date' => 'date',
        'reviewed_at'  => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'driver_absence_trips', 'driver_absence_id', 'trip_id')
            ->withTimestamps();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}