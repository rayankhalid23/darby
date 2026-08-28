<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateComplaintRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرحًا له بإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من البيانات أثناء التحديث.
     * تمت إضافة قاعدة "sometimes" للسماح بتحديث حقل معين دون إجبارية إرسال باقي الحقول.
     */
    public function rules(): array
    {
        return [
            'title'         => 'sometimes|required|string|max:255',
            'description'   => 'sometimes|required|string|min:10|max:5000',
            'type'          => 'sometimes|required|string|in:driver,trip,app,other',
            'driver_id'     => 'sometimes|nullable|integer|exists:drivers,id',
            'trip_id'       => 'sometimes|nullable|integer|exists:trips,id',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg|max:5120', // الحد الأقصى 5MB للملف
        ];
    }

    /**
     * رسائل التنبيه والخطأ المخصصة باللغة العربية.
     */
    public function messages(): array
    {
        return [
            'title.required'        => 'عنوان الشكوى مطلوب.',
            'title.string'          => 'يجب أن يكون عنوان الشكوى نصاً.',
            'title.max'             => 'عنوان الشكوى لا يتجاوز 255 حرفاً.',

            'description.required'  => 'وصف الشكوى مطلوب.',
            'description.string'    => 'يجب أن يكون وصف الشكوى نصاً.',
            'description.min'       => 'يجب أن لا يقل وصف الشكوى عن 10 أحرف.',
            'description.max'       => 'وصف الشكوى لا يتجاوز 5000 حرف.',

            'type.required'         => 'نوع الشكوى مطلوب.',
            'type.in'               => 'نوع الشكوى المختار غير صالح.',

            'driver_id.integer'     => 'معرف السائق يجب أن يكون رقماً صحيحاً.',
            'driver_id.exists'      => 'السائق المحدد غير موجود في النظام.',

            'trip_id.integer'       => 'معرف الرحلة يجب أن يكون رقماً صحيحاً.',
            'trip_id.exists'        => 'الرحلة المحددة غير موجودة في النظام.',

            'attachments.array'     => 'المرفقات يجب أن تكون في صيغة قائمة.',
            'attachments.max'       => 'لا يمكنك رفع أكثر من 5 ملفات مرفقة.',
            'attachments.*.file'    => 'يجب أن يكون المرفق ملفاً صالحاً.',
            'attachments.*.mimes'   => 'نوع الملف غير مدعوم (الصيغ المسموحة: jpeg, png, jpg).',
            'attachments.*.max'     => 'حجم الملف المرفق لا يجب أن يتجاوز 5 ميجابايت.',
        ];
    }

    /**
     * تخصيص مخرجات أخطاء التحقق لتتناسب مع تطبيقات الجوال (JSON Response).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'البيانات المدخلة غير صالحة.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}