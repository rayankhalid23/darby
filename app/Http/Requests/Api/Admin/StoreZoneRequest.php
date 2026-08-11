<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZoneRequest extends FormRequest
{
    /**
     * سماح بالوصول للمسار
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق الصارمة ومنع التكرار
     */
    public function rules(): array
    {
        // جلب معرف المنطقة من الرابط في حال كانت العملية تحديث (Update) ليتخطى نفسه
        $zoneId = $this->route('zone') ?? $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                // قيد منع تكرار اسم المنطقة في قاعدة البيانات
                Rule::unique('zones', 'name')->ignore($zoneId)
            ],
            'sub_municipality_id' => ['nullable', 'exists:sub_municipalities,id'],
        ];
    }

    /**
     * رسائل الأخطاء المتوقعة باللغة العربية بطريقة تجارية واحترافية
     */
    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال اسم المنطقة، هذا الحقل إجباري.',
            'name.string'   => 'يجب أن يكون اسم المنطقة عبارة عن نص صحيح.',
            'name.min'      => 'اسم المنطقة قصير جداً، يجب أن لا يقل عن 3 حروف.',
            'name.max'      => 'اسم المنطقة طويل جداً، يجب أن لا يتجاوز 100 حرف.',
            'name.unique'   => 'فشلت العملية: هذه المنطقة (الاسم) مسجلة مسبقاً في النظام ولا يمكن تكرارها.',
        ];
    }
}