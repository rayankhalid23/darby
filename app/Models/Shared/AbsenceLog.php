<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;

class AbsenceLog extends Model
{
    protected $fillable = ['child_id', 'absence_date', 'absence_type'];

    protected $casts = [
        'absence_date' => 'date',
    ];

    const TYPE_PICKUP  = 'pickup';   // غياب رحلة الذهاب فقط
    const TYPE_DROPOFF = 'dropoff';  // غياب رحلة العودة فقط
    const TYPE_BOTH    = 'both';     // غياب كلتا الرحلتين

    public function child()
    {
        return $this->belongsTo(\App\Models\Parent\Child::class, 'child_id');
    }
}