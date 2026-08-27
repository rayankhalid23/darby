<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RespondTripManualConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.required' => 'يجب تحديد إجابتك (نعم أو لا).',
            'confirmed.boolean'  => 'قيمة الإجابة غير صالحة.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => false,
            'error_code' => 'VALIDATION_ERROR',
            'message'    => '',
            'errors'     => $validator->errors(),
        ], 422));
    }
}
