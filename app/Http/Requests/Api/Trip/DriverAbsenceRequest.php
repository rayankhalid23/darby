<?php
namespace App\Http\Requests\Api\Trip;
use Illuminate\Foundation\Http\FormRequest;

class DriverAbsenceRequest extends FormRequest
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
            'dates.required' => 'يجب على السائق تحديد تواريخ الغياب الخاصة به.',
            'dates.array'    => 'صيغة البيانات المرسلة للتواريخ يجب أن تكون مصفوفة.',
            'dates.min'      => 'يجب على السائق إدخال يوم غياب واحد على الأقل.',
            
            'dates.*.required' => 'تاريخ الغياب مطلوب.',
            'dates.*.date'     => 'يوجد تاريخ غير صحيح ضمن القائمة المرسلة.',
            'dates.*.after_or_equal' => 'عذراً كابتن، لا يمكنك تسجيل غياب بأثر رجعي؛ التواريخ يجب أن تبدأ من اليوم فصاعداً.',
        ];
    }
}