<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * إضافة/تعديل منطقة داخل بلدية.
 *
 * لا يوجد حقل للمحلة إطلاقاً — البلدية تأتي من المسار، والباك يتولى الباقي.
 * فحص تكرار الاسم يتم داخل GeographyService لأنه محصور داخل البلدية الواحدة
 * (يُسمح بوجود "المركز" في أكثر من بلدية).
 */
class StoreAdminZoneRequest extends FormRequest
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
            'name.required' => 'يرجى إدخال اسم المنطقة، هذا الحقل إجباري.',
            'name.string'   => 'يجب أن يكون اسم المنطقة نصاً صحيحاً.',
            'name.min'      => 'اسم المنطقة قصير جداً، يجب ألا يقل عن حرفين.',
            'name.max'      => 'اسم المنطقة طويل جداً، يجب ألا يتجاوز 100 حرف.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، بيانات المنطقة تحتوي على أخطاء.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
