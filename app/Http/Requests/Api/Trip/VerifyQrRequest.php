<?php

namespace App\Http\Requests\Api\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyQrRequest extends FormRequest
{
    /**
     * تحديد صلاحية الوصول لهذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * شروط التحقق من البيانات (مع دعم الأسماء الكاملة والمختصرة للإحداثيات).
     */
    public function rules(): array
    {
        return [
            'qr_code'   => 'required|string',
            
            // التحقق من خطوط العرض (يدعم latitude أو lat)
            'latitude'  => 'required_without:lat|numeric|between:-90,90',
            'lat'       => 'required_without:latitude|numeric|between:-90,90',
            
            // التحقق من خطوط الطول (يدعم longitude أو lng)
            'longitude' => 'required_without:lng|numeric|between:-180,180',
            'lng'       => 'required_without:longitude|numeric|between:-180,180',
        ];
    }

    /**
     * مصفوفة رسائل الخطأ الشاملة لكافة الاحتمالات والمسميات.
     */
    public function messages(): array
    {
        return [
            // --- رمز الـ QR ---
            'qr_code.required'           => 'رمز الـ QR الخاص بالطفل مطلوب لإتمام عملية التحقق وتوثيق الصعود.',
            'qr_code.string'             => 'رمز الـ QR الممسوح غير صالح، يجب أن يكون نصاً صالحاً.',
            
            // --- إحداثيات خط العرض (الكاملة والمختصرة) ---
            'latitude.required_without'  => 'إحداثيات خط العرض (Latitude) مطلوبة لتوثيق موقع مسح الـ QR.',
            'latitude.numeric'           => 'قيمة إحداثيات خط العرض الجغرافي يجب أن تكون رقماً صحيحاً أو عشرياً.',
            'latitude.between'           => 'إحداثيات خط العرض الجغرافي المرسلة خارج النطاق العالمي المسموح به (-90 إلى 90).',
            
            'lat.required_without'       => 'إحداثيات الموقع (lat) مطلوبة لتوثيق موقع مسح الـ QR.',
            'lat.numeric'                => 'قيمة الـ (lat) المرسلة يجب أن تكون رقماً صحيحاً أو عشرياً.',
            'lat.between'                => 'قيمة الـ (lat) المرسلة خارج النطاق الجغرافي المسموح به (-90 إلى 90).',

            // --- إحداثيات خط الطول (الكاملة والمختصرة) ---
            'longitude.required_without' => 'إحداثيات خط الطول (Longitude) مطلوبة لتوثيق موقع مسح الـ QR.',
            'longitude.numeric'          => 'قيمة إحداثيات خط الطول الجغرافي يجب أن تكون رقماً صحيحاً أو عشرياً.',
            'longitude.between'          => 'إحداثيات خط الطول الجغرافي المرسلة خارج النطاق العالمي المسموح به (-180 إلى 180).',
            
            'lng.required_without'       => 'إحداثيات الموقع (lng) مطلوبة لتوثيق موقع مسح الـ QR.',
            'lng.numeric'                => 'قيمة الـ (lng) المرسلة يجب أن تكون رقماً صحيحاً أو عشرياً.',
            'lng.between'                => 'قيمة الـ (lng) المرسلة خارج النطاق الجغرافي المسموح به (-180 إلى 180).',
        ];
    }

    /**
     * معالجة فشل التحقق لإرجاع استجابة JSON موحدة متوافقة مع تطبيقات الهواتف.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => false,
            'error_code' => 'VALIDATION_ERROR',
            'message'    => 'البيانات المرسلة للتحقق من الـ QR غير صالحة.',
            'errors'     => $validator->errors()
        ], 422));
    }
}