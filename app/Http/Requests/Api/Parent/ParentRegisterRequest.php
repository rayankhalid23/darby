<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ParentRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // الاسم الثلاثي بحروف عربية فقط
            'full_name' => [
                'required',
                'string',
                'regex:/^[\p{Arabic}]+(\s+[\p{Arabic}]+){2,}$/u'
            ],

            // البريد الإلكتروني
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email'
            ],

            // رقم الهاتف الأساسي (10 خانات ويبدأ بـ 09)
            'phone_number' => [
                'required',
                'string',
                'regex:/^09[0-9]{8}$/',
                'unique:users,phone_number'
            ],

            // رقم الهاتف الاحتياطي (اختياري، 10 خانات، ويبدأ بـ 09)
            'alternative_phone' => [
                'nullable',
                'string',
                'regex:/^09[0-9]{8}$/',
                'different:phone_number'
            ],

            // كلمة المرور (7 خانات على الأقل + أرقام وحروف + منع الرموز الخاصة)
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^(?=.*[0-9])(?=.*[a-zA-Z])(?!.*[!@#$%^&*]).+$/',
            ],

            // تأكيد كلمة المرور
            'password_confirmation' => [
                'required',
                'string',
                'same:password'
            ],

            // كود التحقق
            'otp' => [
                'required',
                'numeric',
                'digits:6'
            ],

            // بيانات اختيارية
            'device_name' => ['nullable', 'string'],
            'platform'    => ['nullable', 'string'],
            'fcm_token'   => ['nullable', 'string'],
            'avatar'      => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            // الاسم الكامل
            'full_name.required' => 'حقل الاسم الكامل مطلوب.',
            'full_name.regex'    => 'الاسم يجب أن يكون ثلاثياً وبحروف عربية فقط.',

            // البريد الإلكتروني
            'email.required' => 'حقل البريد الإلكتروني مطلوب.',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'   => 'البريد الإلكتروني مسجل بالفعل، يرجى استخدام بريد آخر',

            // رقم الهاتف الأساسي
            'phone_number.required' => 'حقل رقم الهاتف الأساسي مطلوب.',
            'phone_number.regex'    => 'رقم الهاتف الأساسي يجب أن يتكون من 10 أرقام ويبدأ بـ 09.',
            'phone_number.unique'   => 'رقم الهاتف الأساسي مسجل لدينا بالفعل.',

            // رقم الهاتف الاحتياطي
            'alternative_phone.regex'     => 'رقم الهاتف الاحتياطي يجب أن يتكون من 10 أرقام ويبدأ بـ 09.',
            'alternative_phone.different' => 'رقم الهاتف الاحتياطي لا يمكن أن يكون مطابقاً لرقم الهاتف الأساسي.',

            // كلمة المرور
            'password.required' => 'حقل كلمة المرور مطلوب.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 6 خانات.',
            'password.regex'    => 'كلمة المرور يجب أن تحتوي على أرقام وحروف، ويُمنع استخدام الرموز الخاصة.',

            // تأكيد كلمة المرور
            'password_confirmation.required' => 'حقل تأكيد كلمة المرور مطلوب.',
            'password_confirmation.same'     => 'تأكيد كلمة المرور غير مطابق لكلمة المرور الرئيسية.',

            // كود التحقق OTP
            'otp.required' => 'حقل رمز التحقق مطلوب.',
            'otp.numeric'  => 'رمز التحقق يجب أن يتكون من أرقام فقط.',
            'otp.digits'   => 'رمز التحقق يجب أن يتكون من 6 أرقام بالضبط.',

            // الصورة الشخصية
            'avatar.image' => 'الملف المرفق يجب أن يكون صورة صحيحة.',
            'avatar.mimes' => 'صيغة الصورة يجب أن تكون: jpeg, png, jpg, gif, أو svg.',
            'avatar.max'   => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    /**
     * توحيد تنسيق أخطاء الـ API تماشياً مع معايير المشروع
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => false,
            'error_code' => 'VALIDATION_ERROR',
            'message'    => 'خطأ في البيانات المرسلة، يرجى تصحيح الحقول وإعادة المحاولة.',
            'errors'     => $validator->errors()
        ], 422));
    }
}