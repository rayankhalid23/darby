<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // افترضنا السماح فقط للأدمن، يمكنك تعديل التحقق حسب الصلاحيات لديك
        return true; 
    }

    public function rules(): array
    {
        return [
            'discount_one_child'           => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_two_children'        => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_three_plus_children' => ['required', 'numeric', 'min:0', 'max:100'],
            'platform_commission_rate'    => ['required', 'numeric', 'min:0', 'max:100'],
            'price_per_km_ac'              => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'price_per_km_non_ac'          => ['required', 'numeric', 'min:0.1', 'max:1000'],

            // رسوم تغيير العنوان حسب المسافة بين الموقع الجديد وموقع الطفل الحالي.
            // اختيارية حتى لا تنكسر الشاشات القديمة التي ترسل حقول التسعير الستة فقط.
            'location_change_fee'           => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'location_change_fee_under_2km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'location_change_fee_2_to_6km'  => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'location_change_fee_6_to_10km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_one_child.required'           => 'نسبة خصم الطفل الواحد مطلوبة.',
            'discount_two_children.required'        => 'نسبة خصم الطفلين مطلوبة.',
            'discount_three_plus_children.required' => 'نسبة خصم 3 أطفال أو أكثر مطلوبة.',
            'platform_commission_rate.required'    => 'نسبة عمولة المنصة مطلوبة.',
            'price_per_km_ac.required'              => 'سعر الكيلومتر للسيارة المكيفة مطلوب.',
            'price_per_km_non_ac.required'          => 'سعر الكيلومتر للسيارة غير المكيفة مطلوب.',

            'location_change_fee_under_2km.numeric' => 'رسوم تغيير العنوان لمسافة أقل من 2 كم يجب أن تكون رقماً.',
            'location_change_fee_under_2km.min'     => 'رسوم تغيير العنوان لمسافة أقل من 2 كم لا يمكن أن تكون سالبة.',
            'location_change_fee_2_to_6km.numeric'  => 'رسوم تغيير العنوان لمسافة من 2 إلى 6 كم يجب أن تكون رقماً.',
            'location_change_fee_2_to_6km.min'      => 'رسوم تغيير العنوان لمسافة من 2 إلى 6 كم لا يمكن أن تكون سالبة.',
            'location_change_fee_6_to_10km.numeric' => 'رسوم تغيير العنوان لمسافة من 6 إلى 10 كم يجب أن تكون رقماً.',
            'location_change_fee_6_to_10km.min'     => 'رسوم تغيير العنوان لمسافة من 6 إلى 10 كم لا يمكن أن تكون سالبة.',

            'min'                                   => 'القيمة المدخلة لا يمكن أن تكون أقل من :min.',
            'max'                                   => 'القيمة المدخلة تتجاوز الحد الأقصى المسموح (:max).',
        ];
    }
}