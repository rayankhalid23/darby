<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'discount_one_child'           => (float) $this->discount_one_child,
            'discount_two_children'        => (float) $this->discount_two_children,
            'discount_three_plus_children' => (float) $this->discount_three_plus_children,
            'platform_commission_rate'    => (float) $this->platform_commission_rate,
            'price_per_km_ac'              => (float) $this->price_per_km_ac,
            'price_per_km_non_ac'          => (float) $this->price_per_km_non_ac,
            'updated_at'                   => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}