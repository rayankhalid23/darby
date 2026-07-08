<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use App\Models\Parent\Child;

class TripEvent extends Model
{
    public $timestamps = false;
    protected $fillable = [
      'trip_id',
    'child_id',
    'subscription_id', // إذا كنت تستخدمه
    'action_type',
    'trip_type',
    'location_lat', // 👈 تأكد من وجوده هنا
    'location_lng', // 👈 تأكد من وجوده هنا
    'scanned_at',
    'trip_cost',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    // علاقة الحدث بالطفل المعني
    public function child()
    {
        return $this->belongsTo(Child::class, 'child_id');
    }
}