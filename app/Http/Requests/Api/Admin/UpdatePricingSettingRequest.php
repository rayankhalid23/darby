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
            'min'                                   => 'القيمة المدخلة لا يمكن أن تكون أقل من :min.',
            'max'                                   => 'النسبة المئوية لا يمكن أن تتجاوز 100%.',
        ];
    }
}