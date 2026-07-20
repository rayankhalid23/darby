<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'full_name'    => $this->full_name,
            'phone_number' => $this->phone_number,
            'role_id'      => $this->role_id,
            'is_active'    => (bool)$this->is_active,
        ];
    }
}