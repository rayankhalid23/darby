<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStudentAttendance extends Model
{
    protected $table = 'trip_student_attendance';

    protected $fillable = [
        'trip_id',
        'child_id',
        'attendance_status',
    ];

    protected $casts = [
        'attendance_status' => 'string',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Parent\Child::class, 'child_id');
    }
}
