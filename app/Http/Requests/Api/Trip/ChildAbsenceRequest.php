<?php
namespace App\Http\Requests\Api\Trip;
use Illuminate\Foundation\Http\FormRequest;

class ChildAbsenceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'dates'   => 'required|array|min:1',
            'dates.*' => 'required|date|after_or_equal:today',
        ];
    }

    public function messages(): array {
        return [
            'dates.required' => 'يجب إرسال تواريخ الغياب المراد جدولتها.',
            'dates.array'    => 'يجب أن يتم إرسال التواريخ على هيئة مصفوفة (Array).',
            'dates.min'      => 'يجب تحديد تاريخ واحد على الأقل لتسجيل الغياب.',
            
            'dates.*.required' => 'يوجد حقل تاريخ فارغ داخل قائمة التواريخ المرسلة.',
            'dates.*.date'     => 'أحد التواريخ المرسلة غير صالحة أو صيغتها غير مدعومة.',
            'dates.*.after_or_equal' => 'لا يمكنك تحديد تاريخ غياب قد مضى؛ يجب أن يكون التاريخ اليوم أو في المستقبل.',
        ];
    }
}