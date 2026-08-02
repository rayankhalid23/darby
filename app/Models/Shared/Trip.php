<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use App\Models\Driver\Driver;
use App\Models\Shared\Route;
use App\Models\Shared\ActiveSubscription;

class Trip extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'route_id',
        'trip_type',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'scheduled_start_time',
        'actual_start_time',
        'trip_date',
        'created_at',
    ];
    
    protected $casts = [
        'scheduled_at'         => 'datetime',
        'started_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'scheduled_start_time' => 'datetime',
        'actual_start_time'    => 'datetime',
        'trip_date'            => 'date',
        'created_at'           => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    public function events()
    {
        return $this->hasMany(TripEvent::class, 'trip_id');
    }

    public function tracking()
    {
        return $this->hasMany(TripTracking::class, 'trip_id');
    }
    public function activeSubscriptions()
{
    // إذا كنت تريد جلب الاشتراكات المرتبطة بنفس مسار الرحلة (route_id)
    return $this->hasMany(ActiveSubscription::class, 'route_id', 'route_id');
}
}