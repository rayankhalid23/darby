<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 🏛️ البلدية — المستوى الأول في الهرم الجغرافي
 */
class MunicipalityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            // عدد المحلات التابعة مباشرة
            'sub_municipalities_count' => $this->whenNotNull($this->sub_municipalities_count ?? null),
            // إجمالي المناطق في كل محلات هذه البلدية
            'zones_count'              => $this->whenNotNull($this->zones_count ?? null),
            // تظهر فقط عند طلب ?with_children=1 أو عند عرض بلدية واحدة
            'sub_municipalities'       => SubMunicipalityResource::collection(
                $this->whenLoaded('subMunicipalities')
            ),
            'created_at'               => $this->created_at?->toDateTimeString(),
            'updated_at'               => $this->updated_at?->toDateTimeString(),
        ];
    }
}
