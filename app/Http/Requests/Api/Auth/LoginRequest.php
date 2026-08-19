<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class LoginRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * قواعد التحقق المفككة بدقة عالية.
     */
    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'platform'    => ['required', 'string', 'in:ios,android,web'],
            'fcm_token'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'يرجى إدخال البريد الإلكتروني.',
            'email.email'       => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.string'   => 'كلمة المرور يجب أن تكون نصاً صالحاً.',
            'device_name.string'=> 'اسم الجهاز غير صالح.',
            'platform.required' => 'حدث خطأ في تحديد نوع التطبيق، يرجى إعادة تشغيل التطبيق.',
            'platform.in'       => 'منصة الدخول غير مدعومة (ios, android, web فقط).',
        ];
    }
}