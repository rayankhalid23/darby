<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ActiveSubscribedChildResource extends JsonResource
{
    /**
     * تحويل كائن الطفل المعني بالاشتراك النشط إلى مصفوفة JSON
     */
    public function toArray(Request $request): array
    {
        $rawPhoto = (!empty($this->photo_url) && !empty(trim($this->photo_url))) ? trim($this->photo_url) : null;
        $photoUrl = $rawPhoto ? (str_starts_with($rawPhoto, 'http') ? $rawPhoto : Storage::url($rawPhoto)) : asset('assets/images/default-child.png');

        return [
            'id'        => $this->id,
            'name'      => $this->full_name ?? $this->name,
            'photo_url' => $photoUrl,
            'image'     => $photoUrl,
        ];
    }
}
