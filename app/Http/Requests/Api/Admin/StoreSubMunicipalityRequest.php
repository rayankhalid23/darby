<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * إضافة/تعديل محلة داخل بلدية.
 *
 * البلدية تأتي من المسار وليست حقلاً في الجسم.
 * فحص تكرار الاسم يتم في GeographyService لأنه محصور داخل البلدية الواحدة
 * (يُسمح بوجود "المركز" في أكثر من بلدية).
 */
class StoreSubMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم المحلة، هذا الحقل إجباري.',
            'name.string'   => 'يجب أن يكون اسم المحلة نصاً صحيحاً.',
            'name.min'      => 'اسم المحلة قصير جداً، يجب ألا يقل عن حرفين.',
            'name.max'      => 'اسم المحلة طويل جداً، يجب ألا يتجاوز 100 حرف.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، بيانات المحلة تحتوي على أخطاء.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
