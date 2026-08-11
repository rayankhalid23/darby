<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * إضافة/تعديل بلدية
 */
class StoreMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // عند التعديل نتجاهل البلدية نفسها في فحص التكرار
        $municipalityId = $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('municipalities', 'name')->ignore($municipalityId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم البلدية، هذا الحقل إجباري.',
            'name.string'   => 'يجب أن يكون اسم البلدية نصاً صحيحاً.',
            'name.min'      => 'اسم البلدية قصير جداً، يجب ألا يقل عن حرفين.',
            'name.max'      => 'اسم البلدية طويل جداً، يجب ألا يتجاوز 100 حرف.',
            'name.unique'   => 'هذه البلدية مسجلة مسبقاً في النظام.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، بيانات البلدية تحتوي على أخطاء.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
