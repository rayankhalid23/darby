<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * تعديل المشرف/الأدمن لبياناته الشخصية بنفسه.
 *
 * الفرق عن UpdateAdminRequest:
 *  - يتجاهل حساب المستخدم الحالي في قواعد التفرّد (لا يحتاج {id} من المسار)
 *  - لا يسمح بتعديل is_active إطلاقاً (لا يوقف المستخدم نفسه ولا يفعّل نفسه)
 *  - يشترط كلمة المرور الحالية عند تغيير كلمة المرور
 */
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
            // كلمة المرور الحالية إجبارية فقط عند إرسال كلمة مرور جديدة
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
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            'avatar'   => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.unique'           => 'هذا الاسم مسجل في النظام مسبقاً.',
            'email.email'                => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'               => 'البريد الإلكتروني مستخدم بالفعل لحساب آخر.',
            'phone_number.digits'        => 'رقم الهاتف يجب أن يتكون من 10 أرقام بالضبط.',
            'phone_number.regex'         => 'رقم الهاتف غير صحيح، يجب أن يبدأ بـ 09.',
            'phone_number.unique'        => 'رقم الهاتف هذا مستخدم لحساب آخر.',
            'current_password.required_with' => 'يجب إدخال كلمة المرور الحالية لتغيير كلمة المرور.',
            'password.min'               => 'يجب ألا تقل كلمة المرور عن 6 خانات.',
            'password.confirmed'         => 'تأكيد كلمة المرور غير مطابق.',
            'avatar.image'               => 'الملف المرفق يجب أن يكون صورة.',
            'avatar.mimes'               => 'يجب أن تكون الصورة بصيغة jpeg, png, أو jpg.',
            'avatar.max'                 => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
            'avatar.uploaded'            => 'تعذر رفع الصورة إلى الخادم. تأكد أن حجمها لا يتجاوز 2 ميجابايت ثم أعد المحاولة.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'  => false,
            'message' => 'عذراً، البيانات المرسلة لتعديل الملف الشخصي تحتوي على أخطاء.',
            'errors'  => $validator->errors()
        ], 422));
    }
}
