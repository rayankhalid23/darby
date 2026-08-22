<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class AbandonRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.integer' => 'معرّف المستخدم يجب أن يكون رقماً صحيحاً.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'بيانات الإلغاء غير صالحة.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
