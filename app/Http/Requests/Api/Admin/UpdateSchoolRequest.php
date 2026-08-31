<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // الكنترولر لا يستخدم Route Model Binding (البارامتر $school بلا type-hint)،
        // لذا route('school') يعود نصاً لا كائناً؛ قراءة ->id منه مباشرة كانت
        // ترمي "Attempt to read property on string" وتُرجع 500 بدل التحقق الطبيعي.
        $routeSchool = $this->route('school');
        $schoolId = $routeSchool instanceof \App\Models\Parent\School
            ? $routeSchool->id
            : $routeSchool;
    
        return [
            'name'     => 'sometimes|required|string|max:150|unique:schools,name,' . $schoolId,
            'lat'      => 'sometimes|required|numeric|between:-90,90',
            'lng'      => 'sometimes|required|numeric|between:-180,180',
            'address'  => 'sometimes|required|string|max:255',
            'zone_id'  => 'sometimes|required|exists:zones,id',
            'status'   => 'sometimes|required|in:active,approved,pending'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'اسم المدرسة حقل مطلوب.',
            'name.unique'       => 'اسم المدرسة مسجل مسبقاً في النظام.',
            'lat.required'      => 'إحداثيات خط العرض مطلوبة.',
            'lng.required'      => 'إحداثيات خط الطول مطلوبة.',
            'address.required'  => 'عنوان المدرسة التفصيلي مطلوب.',
            'zone_id.required'  => 'يجب اختيار المنطقة الجغرافية (البلدية) للمدرسة.',
            'zone_id.exists'    => 'المنطقة المختارة غير صالحة أو غير موجودة.'
        ];
    }
}