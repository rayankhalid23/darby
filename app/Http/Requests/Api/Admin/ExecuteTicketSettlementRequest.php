<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteTicketSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['credit', 'debit'])], // credit = تحويل إلى الطرف، debit = خصم منه
            'party_role' => ['required', Rule::in(['driver', 'parent'])],
            'party_user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'direction.required'      => 'يجب تحديد نوع الحركة (تحويل إلى / خصم من).',
            'party_role.required'     => 'يجب تحديد الطرف المستهدف (سائق أو ولي أمر).',
            'party_user_id.required'  => 'يجب تحديد المستخدم المستهدف بالتسوية.',
            'party_user_id.exists'    => 'المستخدم المحدد غير موجود.',
            'amount.required'         => 'المبلغ مطلوب.',
            'amount.min'              => 'يجب أن يكون المبلغ أكبر من صفر.',
        ];
    }
}
