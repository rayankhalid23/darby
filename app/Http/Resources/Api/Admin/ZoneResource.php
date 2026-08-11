<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 📍 المنطقة — المستوى الثالث، تتبع محلة واحدة.
 *
 * نُرجع معها اسم المحلة واسم البلدية معاً حتى تعرض الواجهة المسار الكامل
 * (طرابلس المركز ← النوفليين ← بن عاشور) بلا استدعاءات إضافية.
 */
class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $municipality = $this->subMunicipality?->municipality;

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,

            'sub_municipality_id'   => $this->sub_municipality_id,
            'sub_municipality_name' => $this->subMunicipality?->name,
            'municipality_id'       => $municipality?->id,
            'municipality_name'     => $municipality?->name,
            // المسار الكامل جاهز للعرض في الواجهة
            'full_path'             => $this->buildFullPath(),

            // إحصاءات الاستخدام — تتيح تعطيل زر الحذف مسبقاً
            'drivers_count'         => $this->whenNotNull($this->drivers_count ?? null),
            'schools_count'         => $this->whenNotNull($this->schools_count ?? null),
            'addresses_count'       => $this->whenNotNull($this->addresses_count ?? null),
            'can_delete'            => $this->whenNotNull($this->can_delete ?? null),

            'created_at'            => $this->created_at?->toDateTimeString(),
            'updated_at'            => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function buildFullPath(): ?string
    {
        $sub = $this->subMunicipality;

        if (!$sub) {
            return $this->name;
        }

        $parts = array_filter([
            $sub->municipality?->name,
            $sub->name,
            $this->name,
        ]);

        return implode(' ← ', $parts);
    }
}
