<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Models\Admin\Admin;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        // اسم المتغير في المسار هو {id} وليس {admin}، لذلك نقرأ الاثنين معاً حمايةً من أي تغيير لاحق
        $adminParam = $this->route('admin') ?? $this->route('id');
        $admin = $adminParam instanceof Admin ? $adminParam : Admin::find($adminParam);
        $userId = $admin ? $admin->user_id : null;

        return [
            'full_name' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('users', 'full_name')->ignore($userId),
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $words = explode(' ', trim(preg_replace('/\s+/', ' ', $value)));
                        if (count($words) < 3) {
                            $fail('الرجاء إدخال الاسم الثلاثي بالكامل.');
                        }
                    }
                }
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
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
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:6'
            ],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.unique'   => 'هذا الاسم مسجل في النظام مسبقاً.',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'       => 'البريد الإلكتروني مستخدم بالفعل لحساب آخر.',
            'phone_number.digits'=> 'رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط.',
            'phone_number.regex' => 'رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09.',
            'phone_number.unique'=> 'رقم الهاتف هذا مستخدم لحساب آخر.',
            'password.min'       => 'يجب ألا تقل كلمة المرور عن 6 خانات.',
            'avatar.image'       => 'الملف المرفق يجب أن يكون صورة.',
            'avatar.mimes'       => 'يجب أن تكون الصورة بصيغة jpeg, png, أو jpg.',
            'avatar.max'         => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'avatar.uploaded'    => 'تعذر رفع الصورة إلى الخادم. تأكد أن حجمها لا يتجاوز 2 ميجابايت ثم أعد المحاولة.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، البيانات المرسلة لتعديل المشرف تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}