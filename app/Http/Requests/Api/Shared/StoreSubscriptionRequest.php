<?php

namespace App\Http\Requests\Api\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation (Normalization & Fallback Mapping).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('children') && is_array($this->children)) {
            $children = collect($this->children)->map(function ($child) {
                // تحويل القيم المبعوثة للاتجاه إلى القيم المعتمدة (go, return, both)
                if (isset($child['trip_direction']) || isset($child['direction'])) {
                    $dir = strtolower($child['trip_direction'] ?? $child['direction']);
                    
                    $child['trip_direction'] = match ($dir) {
                        'go', 'morning', 'one_way_morning'     => 'go',
                        'return', 'evening', 'one_way_evening' => 'return',
                        'both', 'two_way'                      => 'both',
                        default                                => $dir,
                    };
                }

                return $child;
            })->toArray();

            $this->merge(['children' => $children]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
            ],
            
            // مصفوفة الأطفال المطلوبة للاشتراك
            'children' => [
                'required',
                'array',
                'min:1',
            ],
            'children.*.child_id' => [
                'required',
                'integer',
                'exists:children,id',
            ],
            'children.*.subscription_type' => [
                'required',
                'string',
                'in:single_day,multi_day',
            ],
            'children.*.trip_direction' => [
                'required',
                'string',
                'in:go,return,both',
            ],
            'children.*.timing' => [
                'nullable',
                'string',
                'max:50',
            ],
            
            // حقول التسعير والمسافة (تأتي عادة من دالة حساب السعر)
            'children.*.distance_km' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'children.*.trip_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'children.*.price_per_child' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            
            'children.*.start_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $startDate = Carbon::parse($value)->startOfDay();
                    $today = Carbon::today();
                    $tomorrow = Carbon::tomorrow();

                    if ($startDate->lt($today)) {
                        $fail('تاريخ بدء الاشتراك لا يمكن أن يكون في الماضي.');
                        return;
                    }

                    if ($startDate->equalTo($tomorrow)) {
                        $now = Carbon::now();
                        if ($now->greaterThanOrEqualTo($today->copy()->endOfDay())) {
                            $fail('عذراً، تم إغلاق استقبال طلبات التوصيل لغدٍ عند الساعة 12:00 منتصف الليل.');
                        }
                    }
                },
            ],
            'children.*.end_date' => [
                'required',
                'date',
                'after_or_equal:children.*.start_date',
            ],
            'children.*.days_of_week' => [
                'nullable',
                'array',
            ],
            'children.*.days_of_week.*' => [
                'string',
                'in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            ],

            // السعر الإجمالي الكلي اختياري (لأنه يحسب في Service)
            'total_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            // الحقول العامة للاشتراك
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'driver_id.required' => 'يرجى تحديد السائق المطلوب.',
            'driver_id.exists'   => 'السائق المحدد غير موجود بالمنظومة.',

            'children.required'  => 'يجب إضافة طفل واحد على الأقل للاشتراك.',
            'children.array'     => 'صيغة بيانات الأطفال غير صحيحة.',
            'children.min'       => 'يجب تحديد طفل واحد على الأقل.',

            'children.*.child_id.required'           => 'معرف الطفل مطلوب.',
            'children.*.child_id.exists'             => 'أحد الأطفال المحددین غير موجود.',

            'children.*.subscription_type.required' => 'نوع الاشتراك مطلوب لكل طفل.',
            'children.*.subscription_type.in'       => 'نوع الاشتراك غير صالح (مسموح فقط: single_day أو multi_day).',
            'children.*.trip_direction.required'    => 'اتجاه الرحلة مطلوب لكل طفل.',
            'children.*.trip_direction.in'          => 'اتجاه الرحلة غير صالح (مسموح فقط: go للذهاب، return للإياب، both للاتجاهين).',

            'children.*.start_date.required' => 'تاريخ بدء الاشتراك مطلوب لكل طفل.',
            'children.*.start_date.date'     => 'صيغة تاريخ بدء الاشتراك غير صحيحة.',

            'children.*.end_date.required'       => 'تاريخ نهاية الاشتراك مطلوب لكل طفل.',
            'children.*.end_date.date'           => 'صيغة تاريخ نهاية الاشتراك غير صحيحة.',
            'children.*.end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون مساوياً أو بعد تاريخ البدء.',

            'total_price.numeric' => 'يجب أن يكون السعر الإجمالي رقماً.',
            'total_price.min'     => 'لا يمكن أن يكون السعر الإجمالي أقل من صفر.',
        ];
    }
}