<?php

namespace App\Models\Shared;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use App\Models\Parent\ParentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $table = 'complaints';

    protected $fillable = [
        'submitted_by',
        'against_type',
        'against_id',
        'driver_id',
        'trip_id',
        'description',
        'status',
        'resolved_by',
        'resolution_note',
        'action_taken',
        'action_details',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(ParentModel::class, 'submitted_by');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }
}
