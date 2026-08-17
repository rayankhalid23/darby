<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    /**
     * تحويل الموديل إلى مصفوفة JSON منسقة
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'zone_name'           => $this->name,
            'name'                => $this->name,
            'sub_municipality_id' => $this->sub_municipality_id,
            'sub_municipality'    => $this->relationLoaded('subMunicipality') && $this->subMunicipality ? [
                'id'                => $this->subMunicipality->id,
                'name'              => $this->subMunicipality->name,
                'municipality_id'   => $this->subMunicipality->municipality_id,
                'municipality_name' => $this->subMunicipality->relationLoaded('municipality') ? $this->subMunicipality->municipality?->name : null,
            ] : null,
            'created_at'          => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}