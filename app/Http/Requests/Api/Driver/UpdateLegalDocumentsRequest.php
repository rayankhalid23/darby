<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateLegalDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_active !== 0;
    }

    public function rules(): array
    {
        $driverId = auth()->user()->driver->id ?? null;

        return [
            // البيانات النصية القانونية (تعديل جزئي باستخدام sometimes)
            'national_id'    => ['sometimes', 'numeric', 'digits:12', Rule::unique('drivers', 'national_id')->ignore($driverId)],
            'license_number' => ['sometimes', 'string', 'max:50', Rule::unique('drivers', 'license_number')->ignore($driverId)],
            'license_expiry' => ['sometimes', 'date', 'after:today'],
            'insurance_expiry' => ['sometimes', 'date', 'after:today'],

            // ملفات المستندات (اختياري — أرسل الحقل فقط عند التجديد)
            'doc_license'              => ['sometimes', 'image', 'mimes:jpeg,png,jpg', 'max:4096'],
            'doc_logbook'              => ['sometimes', 'image', 'mimes:jpeg,png,jpg', 'max:4096'],
            'doc_insurance'            => ['sometimes', 'image', 'mimes:jpeg,png,jpg', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.digits'        => 'الرقم الوطني يجب أن يتكون من 12 رقماً بالضبط.',
            'national_id.unique'        => 'الرقم الوطني هذا مسجل مسبقاً لسائق آخر.',
            'license_number.unique'     => 'رقم رخصة القيادة هذا مسجل مسبقاً لسائق آخر.',
            'license_expiry.after'      => 'تاريخ انتهاء الرخصة يجب أن يكون تاريخاً مستقبلياً صالباً.',
            'insurance_expiry.date'     => 'تاريخ انتهاء التأمين غير صالح.',
            'insurance_expiry.after'    => 'تاريخ انتهاء التأمين يجب أن يكون تاريخاً مستقبلياً.',

            'doc_license.image'         => 'يجب أن يكون ملف رخصة القيادة صورة صالحة.',
            'doc_logbook.image'         => 'يجب أن يكون ملف كتيب السيارة صورة صالحة.',
            'doc_insurance.image'       => 'يجب أن يكون ملف وثيقة التأمين صورة صالحة.',
            'doc_license.max'           => 'حجم صورة المستند يجب ألا يتجاوز 4 ميجابايت كحد أقصى.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، وثائق التجديد المرسلة تحتوي على أخطاء تحقق.',
            'errors'  => $validator->errors()
        ], 422));
    }
}