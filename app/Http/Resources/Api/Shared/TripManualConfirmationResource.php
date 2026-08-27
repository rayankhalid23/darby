<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripManualConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'trip_id'       => $this->trip_id,
            'trip_date'     => $this->trip?->trip_date?->format('Y-m-d'),
            'question_type' => $this->question_type,
            'target_status' => $this->target_status,
            'status'        => $this->status,
            'child' => [
                'id'   => $this->child?->id,
                'name' => $this->child?->full_name,
            ],
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
