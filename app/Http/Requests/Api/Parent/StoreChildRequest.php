<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        $minDate = Carbon::now()->subYears(21)->format('Y-m-d');
        $maxDate = Carbon::now()->subYears(6)->format('Y-m-d');

        return [
            // جعلنا parent_id اختياري هنا لأن الكنترولر يجذبه تلقائياً من التوكن (auth)
            'parent_id'           => 'nullable|exists:parents,id',
            'school_id'           => 'required|exists:schools,id',
            'address_id'          => 'required|exists:addresses,id',
            'full_name'           => ['required', 'string', 'min:8', 'max:150', 'regex:/^[\p{L}]+([\s]+[\p{L}]+){2,}$/u'],
            'birth_date'          => "required|date|after_or_equal:{$minDate}|before_or_equal:{$maxDate}",
            'gender'              => ['required', Rule::in(['male', 'female'])],
            'grade'               => 'required|integer|min:1|max:12',
            'photo'               => 'nullable|image|mimes:jpeg,png,jpg|max:2048', 
            'medical_notes'       => 'nullable|string|max:1000',
            'notification_radius' => 'nullable|integer|min:100|max:5000',

            // البيانات اللوجستية والاشتراك
            'preferred_time_slot' => ['required', Rule::in(['morning', 'evening', 'both'])],
            'trip_direction'      => ['required', Rule::in(['go', 'return', 'both'])],
            'pickup_time'         => 'nullable|date_format:H:i',
            'dropoff_time'        => 'nullable|date_format:H:i',
            'start_date'          => 'required|date|after_or_equal:today',
            'end_date'            => 'required|date|after:start_date',
            'subscription_type'   => ['required', Rule::in(['daily', 'monthly'])],
        ];
    }

    public function messages(): array
    {
        return [
            // رسائل التحقق من الوجود في قاعدة البيانات (تمنع ظهور validation.exists للفرنت)
            'parent_id.exists'           => 'حساب ولي الأمر غير موجود بالنظام.',
            'school_id.required'         => 'يرجى تحديد المدرسة.',
            'school_id.exists'           => 'المدرسة المختارة غير مسجلة في النظام.',
            'address_id.required'        => 'يرجى تحديد عنوان التوصيل.',
            'address_id.exists'          => 'العنوان المختار غير موجود بالنظام.',

            // البيانات الأساسية
            'full_name.required'         => 'الاسم الثلاثي مطلوب.',
            'full_name.regex'            => 'يرجى إدخال الاسم الثلاثي باللغة العربية بشكل صحيح.',
            'birth_date.required'        => 'تاريخ الميلاد مطلوب.',
            'birth_date.after_or_equal'  => 'عمر الطفل لا يمكن أن يتجاوز 21 سنة.',
            'birth_date.before_or_equal' => 'عمر الطفل لا يمكن أن يقل عن 6 سنوات.',
            'gender.required'            => 'يرجى تحديد جنس الطفل.',
            'gender.in'                  => 'يرجى تحديد جنس الطفل (ذكر أو أنثى).',
            'grade.required'             => 'يرجى تحديد الصف الدراسي.',
            'photo.image'                => 'يجب أن يكون الملف المرفوع صورة صالحة.',
            'photo.max'                  => 'حجم الصورة كبير جداً، الحد الأقصى 2 ميجابايت.',
            
            // اللوجستيات
            'preferred_time_slot.required' => 'يجب اختيار الفترة الزمنية المفضلة.',
            'preferred_time_slot.in'       => 'الفترة المختارة غير صالحة.',
            'trip_direction.required'      => 'يجب تحديد اتجاه الرحلة.',
            'trip_direction.in'            => 'اتجاه الرحلة غير صالح.',
            'pickup_time.date_format'      => 'صيغة وقت الالتقاط يجب أن تكون HH:MM.',
            'dropoff_time.date_format'     => 'صيغة وقت التوصيل يجب أن تكون HH:MM.',
            
            // الاشتراكات
            'start_date.required'        => 'تاريخ بدء الاشتراك مطلوب.',
            'start_date.after_or_equal'  => 'تاريخ البدء يجب أن يكون اليوم أو في المستقبل.',
            'end_date.required'          => 'تاريخ انتهاء الاشتراك مطلوب.',
            'end_date.after'             => 'تاريخ الانتهاء يجب أن يأتي بعد تاريخ البدء.',
            'subscription_type.required' => 'يجب اختيار نوع الاشتراك.',
            'subscription_type.in'       => 'نوع الاشتراك غير صحيح (يجب أن يكون يومي أو شهري).',
        ];
    }

    /**
     * معالجة أخطاء الفلترة: تسجيلها بالـ Log وإعادة رد واضح للفرنت إند
     */
    protected function failedValidation(Validator $validator)
    {
        // 1. كتابة تفاصيل الحقول المسببة للخطأ في الـ Log
        Log::warning('StoreChild Validation Error', [
            'user_id'        => auth()->id(),
            'failed_fields'  => $validator->errors()->toArray(),
            'payload_sent'   => $this->except(['photo']),
        ]);

        // 2. إرجاع استجابة مرتبة للفرنت إند كـ JSON بكود 422
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'بيانات مدخلة غير صالحة، يرجى مراجعة الحقول.',
            'errors'  => $validator->errors()
        ], 422));
    }
}