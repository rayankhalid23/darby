<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverReviewResource extends JsonResource
{
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
                return [
                    'id'        => (int) $this->parent->id,
                    'full_name' => $this->parent->full_name,
                    'avatar_url'=> $this->parent->avatar_url
                        ? asset($this->parent->avatar_url)
                        : null,
                ];
            }),
        ];
    }
}
