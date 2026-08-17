<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinancialLedger extends Model
{
    protected $table = 'financial_ledger';

    protected $fillable = [
        'transaction_id',
        'reference_number',
        'source_account',
        'destination_account',
        'amount',
        'balance_before',
        'balance_after',
        'type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'balance_before' => 'integer',
        'balance_after'  => 'integer',
        'metadata'       => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transaction_id)) {
                $model->transaction_id = (string) Str::uuid();
            }
        });
    }

    public function getAmountDinarAttribute(): float
    {
        return round($this->amount / 100, 2);
    }
}
