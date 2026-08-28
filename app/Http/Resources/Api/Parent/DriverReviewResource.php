<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => (int) $this->id,
            'driver_id'   => (int) $this->driver_id,
            'rating'      => (int) $this->rating,
            'comment'     => $this->comment,
            'status'      => $this->status,
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
            'parent'      => $this->whenLoaded('parent', function () {
                $user = $this->parent instanceof \App\Models\User
                    ? $this->parent
                    : ($this->parent->user ?? $this->parent);

                return [
                    'id'          => (int) $user->id,
                    'full_name'   => $user->full_name ?? $user->name,
                    'avatar_url'  => !empty($user->avatar_url)
                        ? asset($user->avatar_url)
                        : null,
                ];
            }),
            'driver'      => $this->whenLoaded('driver', function () {
                return [
                    'id'   => (int) $this->driver->id,
                    'name' => optional($this->driver->user)->full_name,
                ];
            }),
        ];
    }
}