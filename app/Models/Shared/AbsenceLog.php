<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;

class AbsenceLog extends Model
{
    protected $fillable = ['child_id', 'absence_date'];

    public function child()
    {
        return $this->belongsTo(\App\Models\Parent\Child::class, 'child_id');
    }
}