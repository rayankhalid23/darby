<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'  => 1,
            'role_id'    => 2, // دور "مشرف" في جدول roles
            // منشئ الحساب هو المستخدم صاحب التوكن الحالي، ونرجع للمدير الأساسي كقيمة احتياطية
            'created_by' => $this->user()?->id ?? 1,
        ]);
    }

    public function rules(): array
    {
        return [
            // يدعم العربية والانجليزية ويشترط 3 مقاطع على الأقل لتوثيق الهوية
    'full_name' => [
    'required',
    'string',
    'regex:/^[\x{0600}-\x{06FF}\s]+$/u', // يمنع الإنجليزية، الأرقام، والرموز ويقبل أحرف عربية ومسافات فقط
    'unique:users,full_name',
    function ($attribute, $value, $fail) {
        $words = explode(' ', trim(preg_replace('/\s+/', ' ', $value)));
        if (count($words) < 3) {
            $fail('الاسم يجب أن يكون ثلاثياً على الأقل.');
        }
    }
],
'email' => [
    'required',
    'email:filter', // فحص دقيق لصيغة البريد
    'unique:users,email'
],
            'phone_number' => [
                'required',
                'numeric',
                'digits:10',
                'regex:/^09/',
                'unique:users,phone_number'
            ],
            'password' => [
    'nullable',
    'string',
    'min:6',
    'regex:/[a-zA-Z]/' // يشترط وجود حرف إنجليزي واحد على الأقل
],
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048'
            ],
            'is_active'  => 'required|boolean',
            'role_id'    => 'required|integer',
            'created_by' => 'required|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'حقل الاسم مطلوب.',
'full_name.regex'    => 'الاسم يجب أن يكون باللغة العربية فقط وبدون رموز أو أرقام.',
'full_name.unique'   => 'الاسم مُسجّل مسبقاً.',

'email.required'     => 'البريد الإلكتروني مطلوب.',
'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة.',
'email.unique'       => 'البريد الإلكتروني مُسجّل مسبقاً.',

'phone_number.required' => 'رقم الهاتف مطلوب.',
'phone_number.numeric'  => 'رقم الهاتف يجب أن يتكون من أرقام فقط.',
'phone_number.digits'   => 'رقم الهاتف يجب أن يكون 10 أرقام.',
'phone_number.regex'    => 'رقم الهاتف يجب أن يبدأ بـ 09.',
'phone_number.unique'   => 'رقم الهاتف مُسجّل مسبقاً.',
'password.min'   => 'كلمة المرور يجب ألا تقل عن 6 خانات.',
'password.regex' => 'كلمة المرور يجب أن تحتوي على حرف واحد على الأقل.',

'avatar.image'          => 'الملف يجب أن يكون صورة.',
'avatar.mimes'          => 'الصيغ المسموحة: jpeg, png, jpg.',
'avatar.max'            => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، مدخلات إنشاء الحساب تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}