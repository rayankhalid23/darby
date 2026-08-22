<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'full_name' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[\x{0600}-\x{06FF}\s]+$/u',
                Rule::unique('users', 'full_name')->ignore($userId),
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $words = explode(' ', trim(preg_replace('/\s+/', ' ', $value)));
                        if (count($words) < 3) {
                            $fail('الاسم يجب أن يكون ثلاثياً على الأقل.');
                        }
                    }
                }
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:filter',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'numeric',
                'digits:10',
                'regex:/^09/',
                Rule::unique('users', 'phone_number')->ignore($userId)
            ],
            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !Hash::check($value, $this->user()->password_hash)) {
                        $fail('كلمة المرور الحالية غير صحيحة.');
                    }
                }
            ],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:6',
                'regex:/[a-zA-Z]/',
                'confirmed'
            ],
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.regex'               => 'الاسم يجب أن يكون باللغة العربية فقط وبدون رموز أو أرقام.',
            'full_name.unique'              => 'الاسم مُسجّل مسبقاً.',

            'email.email'                   => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'                  => 'البريد الإلكتروني مُسجّل مسبقاً.',

            'phone_number.numeric'          => 'رقم الهاتف يجب أن يتكون من أرقام فقط.',
            'phone_number.digits'           => 'رقم الهاتف يجب أن يكون 10 أرقام.',
            'phone_number.regex'            => 'رقم الهاتف يجب أن يبدأ بـ 09.',
            'phone_number.unique'           => 'رقم الهاتف مُسجّل مسبقاً.',

            'current_password.required_with' => 'يجب إدخال كلمة المرور الحالية لتغيير كلمة المرور.',
            
            'password.min'                  => 'كلمة المرور يجب ألا تقل عن 6 خانات.',
            'password.regex'                => 'كلمة المرور يجب أن تحتوي على حرف واحد على الأقل.',
            'password.confirmed'            => 'تأكيد كلمة المرور غير مطابق.',

            'avatar.image'                  => 'الملف يجب أن يكون صورة.',
            'avatar.mimes'                  => 'الصيغ المسموحة: jpeg, png, jpg.',
            'avatar.max'                    => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'بيانات المدخلات غير صحيحة.',
            'errors'  => $validator->errors()
        ], 422));
    }
}