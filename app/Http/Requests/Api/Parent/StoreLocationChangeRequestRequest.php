<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLocationChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active_subscription_id' => ['required', 'integer', 'exists:active_subscriptions,id'],
            'point_type'             => ['required', 'string', 'in:pickup,dropoff'],
            'address_id'             => ['nullable', 'integer', 'exists:addresses,id', 'required_without_all:lat,lng'],
            'lat'                    => ['nullable', 'numeric', 'between:-90,90', 'required_without:address_id'],
            'lng'                    => ['nullable', 'numeric', 'between:-180,180', 'required_without:address_id'],
            'label'                  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'active_subscription_id.required' => 'يجب تحديد الاشتراك/الرحلة المعنية.',
            'active_subscription_id.exists'    => 'الاشتراك المحدد غير موجود.',
            'point_type.required'              => 'يجب تحديد نوع النقطة (استلام أو تسليم).',
            'point_type.in'                    => 'نوع النقطة يجب أن يكون استلام (pickup) أو تسليم (dropoff).',
            'address_id.exists'                => 'العنوان المحدد غير موجود.',
            'address_id.required_without_all'  => 'يجب اختيار عنوان محفوظ أو إدخال إحداثيات الموقع الجديد.',
            'lat.required_without'             => 'يجب إدخال خط العرض عند عدم اختيار عنوان محفوظ.',
            'lng.required_without'             => 'يجب إدخال خط الطول عند عدم اختيار عنوان محفوظ.',
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
