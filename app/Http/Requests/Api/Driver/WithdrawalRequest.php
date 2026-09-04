<?php

namespace App\Http\Requests\Api\Driver;

use App\Services\Shared\FinancialLedgerService;
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
            // الحد الأدنى يُشتق من ثابت النظام المالي بدل تكراره رقماً هنا وفي الخدمة.
            'amount' => 'required|numeric|min:' . (FinancialLedgerService::MIN_WITHDRAWAL_AMOUNT / 100) . '|max:50000',
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
            'amount.min'      => 'الحد الأدنى للسحب هو ' . (FinancialLedgerService::MIN_WITHDRAWAL_AMOUNT / 100) . ' د.ل.',
            'amount.max'      => 'الحد الأقصى للسحب هو 50,000 دينار.',
        ];
    }
}
