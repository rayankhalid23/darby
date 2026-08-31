<?php

namespace App\Http\Resources\Api\Admin;

use App\Models\Shared\PricingSetting;
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

            'location_change_fee'           => (float) ($this->location_change_fee ?? PricingSetting::DEFAULT_LOCATION_CHANGE_FEE),
            'location_change_fee_under_2km' => (float) ($this->location_change_fee_under_2km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_UNDER_2KM]),
            'location_change_fee_2_to_6km'  => (float) ($this->location_change_fee_2_to_6km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_2_TO_6KM]),
            'location_change_fee_6_to_10km' => (float) ($this->location_change_fee_6_to_10km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_6_TO_10KM]),
            'location_change_fee_tiers'     => [
                [
                    'tier'         => PricingSetting::TIER_UNDER_2KM,
                    'label'        => PricingSetting::TIER_LABELS[PricingSetting::TIER_UNDER_2KM],
                    'min_km'       => 0,
                    'max_km'       => 2,
                    'max_inclusive'=> false,
                    'fee'          => (float) ($this->location_change_fee_under_2km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_UNDER_2KM]),
                ],
                [
                    'tier'         => PricingSetting::TIER_2_TO_6KM,
                    'label'        => PricingSetting::TIER_LABELS[PricingSetting::TIER_2_TO_6KM],
                    'min_km'       => 2,
                    'max_km'       => 6,
                    'max_inclusive'=> true,
                    'fee'          => (float) ($this->location_change_fee_2_to_6km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_2_TO_6KM]),
                ],
                [
                    'tier'         => PricingSetting::TIER_6_TO_10KM,
                    'label'        => PricingSetting::TIER_LABELS[PricingSetting::TIER_6_TO_10KM],
                    'min_km'       => 6,
                    'max_km'       => 10,
                    'max_inclusive'=> true,
                    'fee'          => (float) ($this->location_change_fee_6_to_10km ?? PricingSetting::DEFAULT_TIER_FEES[PricingSetting::TIER_6_TO_10KM]),
                ],
            ],
            'max_location_change_distance_km' => PricingSetting::MAX_LOCATION_CHANGE_DISTANCE_KM,
            'currency'                     => 'د.ل',
            'updated_at'                   => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}