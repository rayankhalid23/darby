<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // حماية إضافية في حال كان الكائن فارغاً
        if (!$this->resource) {
            return [];
        }

        return [
            'id'             => (int) $this->id,
            'description'    => $this->description,
            'status'         => $this->status,
            'action_taken'   => $this->action_taken,
            'action_details' => $this->action_details,
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
            'resolved_at'    => $this->resolved_at?->format('Y-m-d H:i:s'),
            'driver'         => $this->whenLoaded('driver', function () {
                return [
                    'id'   => (int) $this->driver->id,
                    'name' => $this->driver->user?->full_name ?? 'غير معروف',
                ];
            }),
            'trip'           => $this->whenLoaded('trip', function () {
                return [
                    'id'        => (int) $this->trip->id,
                    'trip_date' => $this->trip->trip_date,
                    'trip_type' => $this->trip->trip_type,
                    'status'    => $this->trip->status,
                ];
            }),
            'resolved_by'    => $this->whenLoaded('resolvedBy', function () {
                return [
                    'id'   => (int) $this->resolvedBy->id,
                    'name' => $this->resolvedBy->name ?? 'غير معروف',
                ];
            }),
        ];
    }
}