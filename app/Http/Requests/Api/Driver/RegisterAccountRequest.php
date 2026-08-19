<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'    => 'required|string|min:10|max:100',
            'email'        => 'required|email|unique:users,email',
            'phone_number' => 'required|digits:10|unique:users,phone_number|regex:/^09[0-9]{8}$/',
            'gender'       => 'required|in:male,female', // الحقل الجديد
            'password'     => ['required', 'string', 'min:8', 'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/'],
            'avatar_url'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',

            'alternative_phone' => 'nullable|string|min:7',
            'device_name'       => 'nullable|string',
            'platform'          => 'nullable|string',
            'fcm_token'         => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'     => 'الاسم الكامل مطلوب.',
            'full_name.min'          => 'يرجى إدخال الاسم الثلاثي على الأقل.',
            'email.required'         => 'البريد الإلكتروني مطلوب.',
            'email.email'            => 'تنسيق البريد الإلكتروني غير صحيح.',
            'email.unique'           => 'هذا البريد الإلكتروني مسجل مسبقاً في النظام.',
            'phone_number.required'  => 'رقم الهاتف مطلوب.',
            'phone_number.digits'    => 'يجب أن يتكون رقم الهاتف من 10 أرقام.',
            'phone_number.unique'    => 'رقم الهاتف هذا مستخدم من قبل سائق آخر.',
            'phone_number.regex'     => 'يجب أن يبدأ رقم الهاتف بـ 09 (مثال: 0910000000).',
            'gender.required'        => 'يرجى تحديد الجنس.',
            'gender.in'              => 'القيمة المختارة للجنس غير صحيحة.',
            'password.required'      => 'كلمة المرور مطلوبة.',
            'password.min'           => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            'password.regex'         => 'كلمة المرور يجب أن تحتوي على حرف إنجليزي ورقم على الأقل.',
            'avatar_url.image'       => 'الملف المرفوع يجب أن يكون صورة.',
            'avatar_url.mimes'       => 'يسمح فقط بالصور بصيغ jpeg, png, jpg.',
            'avatar_url.max'         => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'alternative_phone.min'  => 'رقم الهاتف الاحتياطي يجب ألا يقل عن 7 أرقام.',
            'device_name.string'     => 'اسم الجهاز يجب أن يكون نصاً صالحاً.',
            'platform.string'        => 'نوع المنصة يجب أن يكون نصاً صالحاً.',
            'fcm_token.string'       => 'رمز الإشعارات (FCM Token) يجب أن يكون نصاً صالحاً.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، بيانات التسجيل تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}