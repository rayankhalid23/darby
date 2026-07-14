<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['warning', 'suspension', 'dismiss'])],
            'action_details' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'الإجراء المطلوب (تحذير / إيقاف / تجاهل) مطلوب.',
            'action.in'       => 'الإجراء يجب أن يكون واحداً من: تحذير، إيقاف، تجاهل.',
            'action_details.max' => 'تفاصيل الإجراء لا تتجاوز 2000 حرف.',
        ];
    }
}
