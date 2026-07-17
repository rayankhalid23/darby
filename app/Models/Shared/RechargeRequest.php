<?php

namespace App\Models\Shared;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeRequest extends Model
{
    protected $table = 'recharge_requests';

    protected $fillable = [
        'parent_id',
        'amount',
        'payment_method',
        'reference_number',
        'status',
        'notes',
        'admin_id',
        'completed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
