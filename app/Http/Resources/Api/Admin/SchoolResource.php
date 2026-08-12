<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subMuni = ($this->relationLoaded('zone') && $this->zone) ? $this->zone->subMunicipality : null;
        $muni    = ($subMuni && $subMuni->relationLoaded('municipality')) ? $subMuni->municipality : null;

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'lat'            => (float) $this->lat,
            'lng'            => (float) $this->lng,
            'address'        => $this->address,
            'status'         => $this->status,
            'zone'           => [
                'id'                  => $this->zone_id,
                'name'                => $this->zone->name ?? null,
                'sub_municipality_id' => $subMuni->id ?? null,
                'sub_municipality'    => $subMuni->name ?? null,
                'municipality_id'     => $muni->id ?? null,
                'municipality'        => $muni->name ?? null,
            ],
            'children_count' => $this->relationLoaded('children') ? $this->children->count() : ($this->children_count ?? null),
        ];
    }
}