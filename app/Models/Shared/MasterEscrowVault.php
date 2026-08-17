<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Model;

class MasterEscrowVault extends Model
{
    protected $table = 'master_escrow_vault';

    protected $fillable = [
        'parents_escrow_pool',
        'driver_pending_pool',
        'driver_available_pool',
        'platform_revenue_pool',
        'penalty_pool',
    ];

    protected $casts = [
        'parents_escrow_pool'   => 'integer',
        'driver_pending_pool'   => 'integer',
        'driver_available_pool' => 'integer',
        'platform_revenue_pool' => 'integer',
        'penalty_pool'          => 'integer',
    ];

    public static function getVault(): self
    {
        return static::firstOrCreate([], [
            'parents_escrow_pool'   => 0,
            'driver_pending_pool'   => 0,
            'driver_available_pool' => 0,
            'platform_revenue_pool' => 0,
            'penalty_pool'          => 0,
        ]);
    }
}
