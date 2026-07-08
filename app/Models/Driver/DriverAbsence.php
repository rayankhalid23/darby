<?php

namespace App\Models\Driver;

use Illuminate\Database\Eloquent\Model;

class DriverAbsence extends Model
{
    protected $fillable = ['driver_id', 'absence_date'];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}