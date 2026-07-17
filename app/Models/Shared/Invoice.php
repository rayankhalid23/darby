<?php

namespace App\Models\Shared;

use App\Models\User;
use App\Models\Driver\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'contract_id',
        'parent_id',
        'driver_id',
        'invoice_number',
        'amount',
        'type',
        'status',
        'due_date',
        'subscription_type',
        'total_trips',
        'completed_trips',
        'driver_absences',
        'student_absences',
        'calculated_amount',
        'action_taken',
        'paid_at',
        'resolved_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'calculated_amount' => 'decimal:2',
        'due_date'          => 'date',
        'paid_at'           => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
