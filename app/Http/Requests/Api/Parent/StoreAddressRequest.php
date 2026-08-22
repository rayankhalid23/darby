<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parentId = auth()->id();

        return [
            'label' => [
                'required',
                'string',
                'max:100',
                // شرط ديناميكي: يمنع تكرار الاسم لنفس ولي الأمر عند الإضافة
                \Illuminate\Validation\Rule::unique('addresses', 'label')->where(function ($query) use ($parentId) {
                    return $query->where('parent_id', $parentId)->whereNull('deleted_at');
                })
            ],
            'lat' => [
                'required',
                'numeric',
                'between:-90,90',
                // شرط ديناميكي مشترك: يمنع تكرار نفس الإحداثيات لنفس ولي الأمر
                \Illuminate\Validation\Rule::unique('addresses', 'lat')->where(function ($query) use ($parentId) {
                    return $query->where('parent_id', $parentId)->where('lng', $this->lng);
                })
            ],
            'lng' => 'required|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'يرجى تحديد مسمى للعنوان (مثل: المنزل، بيت الجد).',
            'label.unique'   => 'لديك عنوان مسجل مسبقاً بنفس هذا الاسم، يرجى اختيار اسم آخر.',
            'lat.required'   => 'إحداثيات خط العرض مطلوبة لتعيين الموقع على الخريطة.',
            'lat.unique'     => 'هذا الموقع الجغرافي (الإحداثيات) مضاف لديك بالفعل في قائمة عناوينك.',
            'lng.required'   => 'إحداثيات خط الطول مطلوبة لتعيين الموقع على الخريطة.',
            'lat.between'    => 'إحداثيات خط العرض المرسلة غير صالحة جغرافياً.',
            'lng.between'    => 'إحداثيات خط الطول المرسلة غير صالحة جغرافياً.',
        ];
    }
}