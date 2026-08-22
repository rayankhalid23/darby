<?php

namespace App\Http\Requests\Api\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.string'   => 'البريد الإلكتروني يجب أن يكون نصاً.',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.max'      => 'البريد الإلكتروني يجب ألا يتجاوز 100 حرف.',
        ];
    }

    /**
     * توحيد تنسيق أخطاء الـ API تماشياً مع معايير المشروع
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => false,
            'error_code' => 'VALIDATION_ERROR',
            'message'    => 'خطأ في البيانات المرسلة، يرجى تصحيح الحقول وإعادة المحاولة.',
            'errors'     => $validator->errors()
        ], 422));
    }
}