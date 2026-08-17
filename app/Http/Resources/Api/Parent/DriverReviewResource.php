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
                return [
                    'id'          => (int) $this->parent->id,
                    // الوصول لاسم المستخدم الحقيقي من جدول users عبر علاقة user() داخل ParentModel
                    'full_name'   => optional($this->parent->user)->full_name,
                    'avatar_url'  => optional($this->parent->user)->avatar_url
                        ? asset($this->parent->user->avatar_url)
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