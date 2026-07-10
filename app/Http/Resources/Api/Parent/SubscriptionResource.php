<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'preferred_time_slot' => $this->preferred_time_slot,
            'trip_direction'      => $this->trip_direction,
            'pickup_time'         => $this->pickup_time ? \Carbon\Carbon::parse($this->pickup_time)->format('H:i') : null,
            'dropoff_time'        => $this->dropoff_time ? \Carbon\Carbon::parse($this->dropoff_time)->format('H:i') : null,
            'start_date'          => $this->start_date,
            'end_date'            => $this->end_date,
            'subscription_type'   => $this->subscription_type,
        ];
    }
}