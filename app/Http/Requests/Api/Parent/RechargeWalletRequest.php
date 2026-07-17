<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class RechargeWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'          => 'required|numeric|min:1|max:50000',
            'payment_method'  => 'required|string|in:ncb,libyana,almadar',
            'reference_number' => 'required_if:payment_method,ncb|string|max:100|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'           => 'مبلغ الشحن مطلوب.',
            'amount.numeric'            => 'المبلغ يجب أن يكون رقماً.',
            'amount.min'                => 'الحد الأدنى للشحن هو دينار واحد.',
            'amount.max'                => 'الحد الأقصى للشحن هو 50,000 دينار.',
            'payment_method.required'   => 'طريقة الدفع مطلوبة.',
            'payment_method.in'         => 'طريقة الدفع يجب أن تكون: المصرف التجاري، ليبيانا، أو المدار.',
            'reference_number.required_if' => 'رقم الإحالة مطلوب للتحويل البنكي.',
        ];
    }
}
