<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id'                  => $this->id,
            'child_id'            => $this->child_id,
            'preferred_time_slot' => $this->preferred_time_slot,
            'trip_direction'      => $this->trip_direction,
            'pickup_time'         => $this->pickup_time,
            'dropoff_time'        => $this->dropoff_time,
            'start_date'          => $this->start_date ? (string) $this->start_date : null,
            'end_date'            => $this->end_date ? (string) $this->end_date : null,
            'subscription_type'   => $this->subscription_type,
            'is_active'           => (bool) $this->is_active,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}