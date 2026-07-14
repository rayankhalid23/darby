<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|min:10|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'وصف الشكوى مطلوب.',
            'description.min'      => 'يجب أن لا يقل وصف الشكوى عن 10 أحرف.',
            'description.max'      => 'وصف الشكوى لا يتجاوز 5000 حرف.',
        ];
    }
}
