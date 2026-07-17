<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:5|max:50000',
            'payment_method_details' => 'nullable|array',
            'payment_method_details.bank_name' => 'nullable|string|max:100',
            'payment_method_details.account_number' => 'nullable|string|max:100',
            'payment_method_details.account_name' => 'nullable|string|max:100',
            'payment_method_details.mobile_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'المبلغ المطلوب سحبه مطلوب.',
            'amount.numeric'  => 'المبلغ يجب أن يكون رقماً.',
            'amount.min'      => 'الحد الأدنى للسحب هو 5 دنانير.',
            'amount.max'      => 'الحد الأقصى للسحب هو 50,000 دينار.',
        ];
    }
}
