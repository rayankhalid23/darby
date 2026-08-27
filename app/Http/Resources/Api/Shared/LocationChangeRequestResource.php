<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'active_subscription_id' => $this->active_subscription_id,
            'point_type'             => $this->point_type,
            'status'                 => $this->status,
            'rejection_reason'       => $this->rejection_reason,
            'new_location'           => [
                'lat'   => $this->new_lat,
                'lng'   => $this->new_lng,
                'label' => $this->new_label,
            ],
            'child' => [
                'id'   => $this->child?->id,
                'name' => $this->child?->full_name,
            ],
            'driver' => [
                'id'   => $this->driver?->id,
                'name' => $this->driver?->user?->full_name,
            ],
            'parent' => [
                'id'   => $this->parent?->id,
                'name' => $this->parent?->full_name,
            ],
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
