<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DriverFilterRequest extends FormRequest
{
    /**
     * التحقق من الصلاحية الأمنية للأدمن
     */
    public function authorize(): bool
    {
        // نتحقق من أن المستخدم مسجل دخول وله رتبة أدمن (يمكنك تعديل الشرط حسب نظام الـ Roles لديك)
        return auth()->check() && auth()->user()->role_id !== null; 
    }

    /**
     * قواعد التحقق لفلترة السائقين
     */
    public function rules(): array
    {
        return [
            // الفلترة حسب الحالات الست الموجودة في enum قاعدة البيانات — حساسة لحالة الأحرف
            'status'   => ['nullable', 'string', 'in:Pending,Approved,Suspended,Rejected,Offline,ON_TRIP'],
            'search'   => ['nullable', 'string', 'max:100'], // البحث باسم السائق أو بريده أو هاتفه
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'], // عدد النتائج لكل صفحة
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية
     */
    public function messages(): array
    {
        return [
            'status.in'       => 'الحالة المحددة للفلترة غير صحيحة، القيم المسموحة: Pending, Approved, Suspended, Rejected, Offline, ON_TRIP.',
            'search.max'      => 'نص البحث طويل جداً، يرجى الاختصار.',
            'per_page.integer' => 'عدد النتائج يجب أن يكون رقماً صحيحاً.',
            'per_page.min'    => 'عدد النتائج يجب أن يكون 1 على الأقل.',
            'per_page.max'    => 'عدد النتائج لا يمكن أن يتجاوز 100.',
        ];
    }

    /**
     * رد موحد في حال فشل التحقق للـ APIs المتوافقة مع لوحة التحكم
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، مدخلات الفلترة أو البحث تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}