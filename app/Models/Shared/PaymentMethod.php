<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'target_audience', // parent, driver, both
        'processing_type', // instant_simulation, manual_proof
        'account_name',
        'account_number',
        'iban',
        'wallet_number',
        'icon_url',
        'min_amount',
        'max_amount',
        'instructions_ar',
        'instructions_en',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForParents(Builder $query): Builder
    {
        return $query->whereIn('target_audience', ['parent', 'both']);
    }

    public function scopeForDrivers(Builder $query): Builder
    {
        return $query->whereIn('target_audience', ['driver', 'both']);
    }

    public function driverRecharges(): HasMany
    {
        return $this->hasMany(\App\Models\Driver\DriverRechargeRequest::class, 'payment_method_id');
    }

    public function parentRecharges(): HasMany
    {
        return $this->hasMany(\App\Models\Shared\RechargeRequest::class, 'payment_method_id');
    }
}
