<?php

namespace App\Http\Requests\Api\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportBreakdownRequest extends FormRequest
{
    /**
     * تحديد صلاحية الوصول لهذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * توحيد وتجهيز الحقول المختلفة للإحداثيات وسبب العطل القادمة من الفرونت قبل الفحص.
     */
    protected function prepareForValidation(): void
    {
        $lat = $this->input('latitude') 
            ?? $this->input('lat') 
            ?? $this->input('breakdown_latitude') 
            ?? $this->input('breakdown_lat')
            ?? data_get($this->all(), 'location.latitude')
            ?? data_get($this->all(), 'location.lat')
            ?? data_get($this->all(), 'breakdown_location.latitude')
            ?? data_get($this->all(), 'breakdown_location.lat');

        $lng = $this->input('longitude') 
            ?? $this->input('lng') 
            ?? $this->input('breakdown_longitude') 
            ?? $this->input('breakdown_lng')
            ?? data_get($this->all(), 'location.longitude')
            ?? data_get($this->all(), 'location.lng')
            ?? data_get($this->all(), 'breakdown_location.longitude')
            ?? data_get($this->all(), 'breakdown_location.lng');

        $reason = $this->input('reason') 
            ?? $this->input('notes') 
            ?? $this->input('description')
            ?? $this->input('details');

        $this->merge([
            'latitude'  => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
            'lat'       => $lat !== null ? (float) $lat : null,
            'lng'       => $lng !== null ? (float) $lng : null,
            'reason'    => $reason,
        ]);
    }

    /**
     * شروط التحقق من صحة مدخلات الإبلاغ عن العطل وموقع التعطل.
     */
    public function rules(): array
    {
        return [
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lng'         => 'nullable|numeric|between:-180,180',
            'reason'      => 'nullable|string|max:500',
            'notes'       => 'nullable|string|max:500',
            'accuracy'    => 'nullable|numeric|min:0',
            'speed'       => 'nullable|numeric|min:0',
            'heading'     => 'nullable|numeric|between:0,360',
            'address'     => 'nullable|string|max:255',
            'recorded_at' => 'nullable|date',
        ];
    }

    /**
     * رسائل الخطأ المخصصة باللغة العربية.
     */
    public function messages(): array
    {
        return [
            'latitude.numeric'    => 'يجب أن تكون إحداثيات خط العرض (Latitude) رقماً صحيحاً أو عشرياً.',
            'latitude.between'    => 'إحداثيات خط العرض المرسلة خارج النطاق الجغرافي المسموح به (-90 إلى 90).',
            'longitude.numeric'   => 'يجب أن تكون إحداثيات خط الطول (Longitude) رقماً صحيحاً أو عشرياً.',
            'longitude.between'   => 'إحداثيات خط الطول المرسلة خارج النطاق الجغرافي المسموح به (-180 إلى 180).',
            'reason.string'       => 'يجب أن يكون سبب العطل نصاً صالحاً.',
            'reason.max'          => 'سبب العطل لا يمكن أن يتجاوز 500 حرف.',
            'accuracy.numeric'    => 'دقة الإشارة الجغرافية يجب أن تكون رقماً.',
            'accuracy.min'        => 'دقة الإشارة الجغرافية لا يمكن أن تكون سالبة.',
        ];
    }

    /**
     * معالجة مخصصة لإرجاع الأخطاء بتنسيق JSON موحد.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status'     => 'error',
            'error_code' => 'VALIDATION_ERROR',
            'message'    => 'البيانات المرسلة غير صالحة، يرجى مراجعة الأخطاء.',
            'errors'     => $validator->errors()
        ], 422));
    }
}
