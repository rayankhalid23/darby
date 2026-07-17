<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProcessWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'            => 'الإجراء مطلوب (موافقة أو رفض).',
            'action.in'                  => 'الإجراء يجب أن يكون approve أو reject.',
            'rejection_reason.required_if' => 'سبب الرفض مطلوب.',
        ];
    }
}
