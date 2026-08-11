<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 🏘️ المحلة — المستوى الثاني، تتبع بلدية وتحتوي مناطق
 */
class SubMunicipalityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'municipality_id'    => $this->municipality_id,
            'municipality_name'  => $this->whenLoaded('municipality', fn () => $this->municipality?->name),
            'zones_count'        => $this->whenNotNull($this->zones_count ?? null),
            'zones'              => ZoneResource::collection($this->whenLoaded('zones')),
            'created_at'         => $this->created_at?->toDateTimeString(),
            'updated_at'         => $this->updated_at?->toDateTimeString(),
        ];
    }
}
