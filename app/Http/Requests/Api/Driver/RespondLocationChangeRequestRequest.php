<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RespondLocationChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'           => ['required', 'string', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required'           => 'يجب تحديد قرارك (approved أو rejected).',
            'status.in'                 => 'القرار يجب أن يكون approved أو rejected.',
            'rejection_reason.required_if' => 'يجب توضيح سبب الرفض.',
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
