<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parentId = auth()->id();
        $addressId = $this->route('address') ? ($this->route('address') instanceof \App\Models\Parent\Address ? $this->route('address')->id : $this->route('address')) : $this->route('id');

        return [
            'label' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                // يمنع تكرار الاسم مع عناوين ولي الأمر الأخرى، ويتخطى العنوان الحالي نفسه أثناء التحديث
                \Illuminate\Validation\Rule::unique('addresses', 'label')
                    ->where(function ($query) use ($parentId) {
                        return $query->where('parent_id', $parentId)->whereNull('deleted_at');
                    })->ignore($addressId)
            ],
            'lat' => [
                'sometimes',
                'required',
                'numeric',
                'between:-90,90',
                // يمنع تكرار نفس الإحداثيات الجغرافية مع العناوين الأخرى لنفس العميل
                \Illuminate\Validation\Rule::unique('addresses', 'lat')
                    ->where(function ($query) use ($parentId) {
                        return $query->where('parent_id', $parentId)->where('lng', $this->lng ?? ($this->route('address')->lng ?? null));
                    })->ignore($addressId)
            ],
            'lng' => 'sometimes|required|numeric|between:-180,180',
        ];
    }

    /**
     * تخصيص رسائل الخطأ بالكامل لتظهر بشكل تجاري واحترافي
     */
    public function messages(): array
    {
        return [
            'label.required' => 'يرجى تحديد مسمى للعنوان (مثل: المنزل، بيت الجد).',
            'label.unique'   => 'تعذر التعديل: لديك عنوان آخر مسجل مسبقاً بنفس هذا الاسم.',
            'lat.required'   => 'إحداثيات خط العرض مطلوبة لتعيين الموقع.',
            'lat.unique'     => 'تعذر التعديل: هذا الموقع الجغرافي مضاف لديك بالفعل في عنوان آخر.',
            'lng.required'   => 'إحداثيات خط الطول مطلوبة لتعيين الموقع.',
            'lat.between'    => 'إحداثيات خط العرض المرسلة غير صالحة جغرافياً.',
            'lng.between'    => 'إحداثيات خط الطول المرسلة غير صالحة جغرافياً.',
        ];
    }
}