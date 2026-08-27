<?php

namespace App\Models\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $table = 'contracts';

    protected $fillable = [
        'contract_number',
        'subscription_type',
        'direction',
        'timing',
        'start_date',
        'end_date',
        'days_count',
        'total_price',
        'clauses',
        'subscription_request_id',
        'parent_id',
        'driver_id',
        'pickup_time',
        'dropoff_time',
        'max_waiting_time',
        'status',
        'pdf_path',
        'signed_at',
    ];

    protected $casts = [
        'clauses'          => 'array',
        'start_date'       => 'date',
        'end_date'         => 'date',
        'signed_at'        => 'datetime',
        'total_price'      => 'decimal:2',
        'days_count'       => 'integer',
        'max_waiting_time' => 'integer',
    ];

    public function subscriptionRequest(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function driverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(ActiveSubscription::class, 'contract_id');
    }

    public static function generateContractNumber(): string
    {
        return 'CNT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
