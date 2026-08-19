<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // بيانات السائق - تم توحيد الرقم الوطني ليكون 12 رقماً بالضبط بالتوافق مع ملف التحديث
            'national_id'    => 'required|numeric|digits:12',
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'required|date|after:today',
            'insurance_expiry' => 'required|date|after:today',

            // بيانات المركبة
            'plate_number'    => 'required|string|max:20',
            'brand'           => 'required|string|max:50',
            'model'           => 'required|string|max:50',
            'year'            => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'color'           => 'required|string|max:30',
            'type'            => 'required|string|max:30',
            'capacity_manual' => 'required|integer|min:1|max:60',
            'vehicle_image'   => 'required|image|mimes:jpeg,png,jpg|max:4096',
            'has_ac'          => 'required|boolean',

            // صور المستندات
            'doc_license'              => 'required|image|mimes:jpeg,png,jpg|max:4096',
            'doc_logbook'              => 'required|image|mimes:jpeg,png,jpg|max:4096',
            'doc_insurance'            => 'required|image|mimes:jpeg,png,jpg|max:4096',
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.required'    => 'رقم الهوية الوطنية مطلوب.',
            'national_id.digits'      => 'الرقم الوطني يجب أن يتكون من 12 رقماً بالضبط.',
            'license_number.required' => 'رقم رخصة القيادة مطلوب.',
            'license_expiry.after'    => 'تاريخ انتهاء الرخصة يجب أن يكون في المستقبل.',
            'insurance_expiry.required' => 'تاريخ انتهاء التأمين مطلوب.',
            'insurance_expiry.date'     => 'تاريخ انتهاء التأمين غير صالح.',
            'insurance_expiry.after'    => 'تاريخ انتهاء التأمين يجب أن يكون في المستقبل.',
            'plate_number.required'   => 'رقم لوحة المركبة مطلوب.',
            'year.min'                => 'سنة صنع المركبة يجب أن تكون من عام 2000 فما فوق.',
            'year.max'                => 'سنة صنع المركبة غير منطقية.',
            'capacity_manual.min'     => 'سعة المركبة يجب أن تكون على الأقل مقعد واحد.',
            'capacity_manual.max'     => 'سعة الركاب القصوى المتاحة للتسجيل هي 60 راكباً.',
            'vehicle_image.required'              => 'يرجى إرفاق صورة المركبة.',
            'vehicle_image.image'                 => 'يجب أن تكون صورة المركبة ملف صورة صالح.',
            'vehicle_image.mimes'                 => 'يُسمح فقط بصيغ jpeg, png, jpg لصورة المركبة.',
            'vehicle_image.max'                   => 'حجم صورة المركبة يجب ألا يتجاوز 4 ميجابايت.',
            'doc_license.required'                => 'يرجى إرفاق صورة رخصة القيادة.',
            'doc_license.image'                   => 'ملف رخصة القيادة يجب أن يكون صورة صالحة.',
            'doc_logbook.required'                => 'يرجى إرفاق صورة دفتر المركبة.',
            'doc_logbook.image'                   => 'ملف دفتر المركبة يجب أن يكون صورة صالحة.',
            'doc_insurance.required'              => 'يرجى إرفاق صورة وثيقة التأمين.',
            'doc_insurance.image'                 => 'ملف وثيقة التأمين يجب أن يكون صورة صالحة.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'بيانات إكمال الملف الشخصي غير مكتملة أو تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}