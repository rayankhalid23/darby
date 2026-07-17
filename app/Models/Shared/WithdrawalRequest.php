<?php

namespace App\Models\Shared;

use App\Models\Admin\Admin;
use App\Models\Driver\Driver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $table = 'withdrawal_requests';

    protected $fillable = [
        'driver_id',
        'amount',
        'wallet_balance_at_request',
        'status',
        'payment_method_details',
        'admin_id',
        'rejection_reason',
        'processed_at',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'wallet_balance_at_request' => 'decimal:2',
        'payment_method_details' => 'array',
        'processed_at'           => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
