<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => 'required|integer|exists:drivers,id',
            'trip_id'   => 'nullable|integer|exists:trips,id',
            'description' => 'required|string|min:10|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.required'    => 'معرّف السائق مطلوب.',
            'driver_id.exists'      => 'السائق المحدد غير موجود.',
            'trip_id.exists'        => 'الرحلة المحددة غير موجودة.',
            'description.required'  => 'وصف الشكوى مطلوب.',
            'description.min'       => 'يجب أن لا يقل وصف الشكوى عن 10 أحرف.',
            'description.max'       => 'وصف الشكوى لا يتجاوز 5000 حرف.',
        ];
    }
}
