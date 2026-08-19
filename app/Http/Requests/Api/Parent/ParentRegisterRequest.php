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
            'full_name'             => 'required|string|min:3',
            'email'                 => 'required|email|unique:users,email',
            'phone_number'          => 'required|string|min:7|unique:users,phone_number',
            'alternative_phone'     => 'nullable|string|min:7',
            'password'              => 'required|string|min:7|regex:/^(?=.*[0-9])(?=.*[a-zA-Z])(?!.*[!@#$%^&*]).+$/',
            'password_confirmation' => 'required|same:password',
            'otp'                   => 'required|numeric|digits:6',

            'device_name'           => 'nullable|string',
            'platform'              => 'nullable|string',
            'fcm_token'             => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            // الاسم
            'full_name.required'         => 'عذراً، خانة الاسم الكامل لا يمكن أن تكون فارغة.',
            'full_name.min'              => 'الاسم يجب أن يتكون من 3 أحرف على الأقل.',
            
            // البريد الإلكتروني (تم تعديل رسالة المكرر بناءً على طلبك)
            'email.required'             => 'عذراً، خانة البريد الإلكتروني لا يمكن أن تكون فارغة.',
            'email.email'                => 'صيغة البريد الإلكتروني غير صحيحة، يرجى كتابته بشكل سليم.',
            'email.unique'               => 'هذا البريد الإلكتروني مسجل لدينا بالفعل. هل نسيت كلمة المرور؟ يمكنك استعادتها مباشرة.',
            
            // كلمة المرور
            'password.required'          => 'برجاء إدخال كلمة المرور، لا يمكن ترك الخانة فارغة.',
            'password.min'               => 'كلمة المرور يجب ألا تقل عن 7 خانات لحماية حسابك.',
            'password.regex'             => 'كلمة المرور يجب أن تحتوي على أرقام وحروف، ويُمنع استخدام الرموز الخاصة.',
            
            // تأكيد كلمة المرور (إضافة الرسالة المفقودة)
            'password_confirmation.required' => 'برجاء تأكيد كلمة المرور، لا يمكن ترك الخانة فارغة.',
            'password_confirmation.same'     => 'تأكيد كلمة المرور لا يطابق كلمة المرور المدخلة.',
            
            // كود التحقق (OTP)
            'otp.required'               => 'برجاء إدخال رمز التحقق، لا يمكن ترك الخانة فارغة.',
            'otp.numeric'                => 'عذراً، يجب أن يتكون رمز التحقق من أرقام فقط (يُمنع استخدام الحروف أو الرموز).',
            'otp.digits'                 => 'يجب أن يتكون رمز التحقق من 6 أرقام بالضبط.',

            // رقم الهاتف (الرسائل المخصصة الجديدة)
            'phone_number.required'      => 'عذراً، خانة رقم الهاتف الأساسي لا يمكن أن تكون فارغة.',
            'phone_number.min'           => 'رقم الهاتف يجب ألا يقل عن 7 أرقام.',
            'phone_number.unique'        => 'رقم الهاتف هذا مسجل لدينا بالفعل لحساب آخر، يرجى استخدام رقم مختلف أو تسجيل الدخول.',

            // الصورة الشخصية
'avatar.image' => 'الملف المرفق يجب أن يكون صورة صحيحة.',
'avatar.mimes' => 'صيغة الصورة يجب أن تكون: jpeg, png, jpg, gif, أو svg.',
'avatar.max'   => 'حجم الصورة الشخصية يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    /**
     * توحيد تنسيق أخطاء الـ API تماشياً مع معايير مشروع Darby
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