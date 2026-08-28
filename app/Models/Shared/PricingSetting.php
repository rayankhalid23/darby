<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_one_child',
        'discount_two_children',
        'discount_three_plus_children',
        'platform_commission_rate',
        'price_per_km_ac',      // تم التحديث
        'price_per_km_non_ac',
    ];
}