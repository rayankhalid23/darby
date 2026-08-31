<?php

namespace App\Http\Requests\Api\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLocationRequest extends FormRequest
{
    /**
     * تحديد صلاحية الوصول لهذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * توحيد الأسماء البديلة (lat/lng) القادمة من بعض إصدارات التطبيق مع latitude/longitude قبل الفحص.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'latitude'  => $this->input('latitude', $this->input('lat')),
            'longitude' => $this->input('longitude', $this->input('lng')),
        ]);
    }

    /**
     * شروط التحقق الشاملة والدقيقة لبيانات الـ GPS.
     */
    public function rules(): array
    {
        return [
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'speed'       => 'nullable|numeric|min:0',
            'accuracy'    => 'nullable|numeric|min:0',
            'heading'     => 'nullable|numeric|between:0,360',
            'recorded_at' => 'nullable|date',
        ];
    }

    /**
     * مصفوفة رسائل الخطأ الكاملة والمخصصة لكل حقل وكل حالة فشل.
     */
    public function messages(): array
    {
        return [
            // --- خط العرض (Latitude) ---
            'latitude.required'   => 'إحداثيات خط العرض (Latitude) مطلوبة لتحديث الموقع.',
            'latitude.numeric'    => 'يجب أن تكون إحداثيات خط العرض رقماً صحيحاً أو عشرياً.',
            'latitude.between'    => 'إحداثيات خط العرض المرسلة خارج النطاق الجغرافي العالمي المسموح به (-90 إلى 90).',
            
            // --- خط الطول (Longitude) ---
            'longitude.required'  => 'إحداثيات خط الطول (Longitude) مطلوبة لتحديث الموقع.',
            'longitude.numeric'   => 'يجب أن تكون إحداثيات خط الطول رقماً صحيحاً أو عشرياً.',
            'longitude.between'   => 'إحداثيات خط الطول المرسلة خارج النطاق الجغرافي العالمي المسموح به (-180 إلى 180).',
            
            // --- السرعة (Speed) ---
            'speed.numeric'       => 'يجب أن تكون قيمة السرعة عبارة عن رقم صحيح أو عشري.',
            'speed.min'           => 'لا يمكن أن تكون قيمة سرعة المركبة بالسالب.',
            
            // --- اتجاه المركبة (Heading) ---
            'heading.numeric'     => 'يجب أن تكون قيمة اتجاه المركبة (Heading) رقماً.',
            'heading.between'     => 'اتجاه المركبة يجب أن يكون بين 0 و 360 درجة.',

            // --- دقة الإشارة (Accuracy) ---
            'accuracy.numeric'    => 'يجب أن تكون قيمة دقة إشارة الجي بي إس (Accuracy) عبارة عن رقم.',
            'accuracy.min'        => 'لا يمكن أن تكون قيمة دقة الإشارة الجغرافية بالسالب.',
            
            // --- وقت تسجيل الإحداثية (Recorded At) ---
            'recorded_at.date'    => 'صيغة تاريخ ووقت تسجيل الموقع (Recorded At) غير صالحة أو غير متوافقة مع معايير التواريخ.',
        ];
    }

    /**
     * معالجة مخصصة لإرجاع الأخطاء بتنسيق JSON موحد ومناسب لتطبيقات الهواتف ولوحات التحكم.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => false,
            'error_code' => 'VALIDATION_ERROR',
            'message'    => 'البيانات المرسلة غير صالحة، يرجى مراجعة الأخطاء.',
            'errors'     => $validator->errors()
        ], 422));
    }
}