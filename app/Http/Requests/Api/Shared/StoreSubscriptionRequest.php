<?php

namespace App\Http\Requests\Api\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Shared\SubscriptionRequest;
use App\Enums\Shared\SubscriptionDuration;
use Carbon\Carbon;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        // تفعيل الصلاحية (يمكنك ربطها بـ Guard ولي الأمر لاحقاً)
        return true; 
    }

    /**
     * قواعد التحقق الصارمة لضمان سلامة البيانات ومنع التلاعب.
     */
    public function rules(): array
{
    return [
        'driver_id'         => 'required|integer|exists:drivers,id',
        'school_id'         => 'required|integer|exists:schools,id',
        'subscription_type' => ['required', 'string', Rule::in(SubscriptionDuration::childValues())],
        
        // تم توحيد القيم لتطابق الموديل
        'direction' => 'required|string|in:' . 
    SubscriptionRequest::DIRECTION_GO . ',' . 
    SubscriptionRequest::DIRECTION_RETURN . ',' . 
    SubscriptionRequest::DIRECTION_BOTH,
        
        'timing'            => 'required|string|in:MORNING,EVENING,BOTH',
        'start_date'        => 'required|date',
        'end_date'          => 'nullable|date',
        
        'children'          => 'required|array|min:1',
        'children.*.child_id'         => 'required|integer|exists:children,id',
        'children.*.pickup_address_id'  => 'required|integer|exists:addresses,id',
        'children.*.dropoff_address_id' => 'required|integer',
        'children.*.price_per_child'    => 'required|numeric|min:0',
    ];
}
    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [
            'driver_id.exists'              => 'السائق المحدد غير موجود في النظام.',
            'school_id.exists'              => 'المدرسة المحددة غير مسجلة لدينا.',
            'timing.in'                     => 'التوقيت غير صحيح، يجب أن يكون MORNING أو EVENING أو BOTH.',
            'children.required'             => 'يجب تحديد طفل واحد على الأقل لإتمام طلب الاشتراك.',
            'children.*.child_id.exists'    => 'أحد الأطفال المحددين غير موجود في النظام.',
            'children.*.price_per_child.required' => 'سعر الاشتراك مطلوب لكل طفل.',
            'children.*.price_per_child.numeric'  => 'سعر الطفل يجب أن يكون رقماً.',
        ];
    }

    /**
     * شروط التواريخ لطلب الاشتراك الفعلي
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $type  = $this->input('subscription_type');
            $start = $this->input('start_date') ? Carbon::parse($this->input('start_date'))->startOfDay() : null;
            $end   = $this->input('end_date')   ? Carbon::parse($this->input('end_date'))->startOfDay()   : null;
            $now   = Carbon::now();

            if (!$start) {
                return;
            }

            // 1. لا تواريخ ماضية
            if ($start->lt($now->copy()->startOfDay())) {
                $v->errors()->add('start_date', 'لا يمكن اختيار تاريخ في الماضي.');
                return;
            }

            // 2. حد الإغلاق الليلي — بعد 22:00 يُضاف يوم إضافي
            $minStart = $now->copy()->addDay()->startOfDay();
            if ($now->hour >= 22) {
                $minStart->addDay();
            }
            while ($minStart->isFriday() || $minStart->isSaturday()) {
                $minStart->addDay();
            }

            if ($start->lt($minStart)) {
                $msg = $now->hour >= 22
                    ? 'بعد الساعة 10 مساءً لا يُقبل الحجز إلا من بعد غد على الأقل.'
                    : 'يجب أن يكون تاريخ البدء غداً على الأقل لإتاحة الوقت للسائق.';
                $v->errors()->add('start_date', $msg);
                return;
            }

            // 3. لا بداية في عطلة
            if ($start->isFriday() || $start->isSaturday()) {
                $v->errors()->add('start_date', 'لا يمكن أن يبدأ الاشتراك في يوم عطلة (جمعة أو سبت).');
                return;
            }

            // 4. حد أقصى للحجز المسبق: 60 يوماً
            if ($start->gt($now->copy()->addDays(60)->startOfDay())) {
                $v->errors()->add('start_date', 'لا يمكن الحجز لأكثر من 60 يوماً مقدماً.');
                return;
            }

            if (!$end) {
                return;
            }

            // 5. النهاية لا تسبق البداية
            if ($end->lt($start)) {
                $v->errors()->add('end_date', 'تاريخ الانتهاء لا يمكن أن يكون قبل تاريخ البدء.');
                return;
            }

            // 6. single_day: البداية = النهاية
            if ($type === SubscriptionDuration::SINGLE_DAY->value && !$start->eq($end)) {
                $v->errors()->add('end_date', 'اشتراك يوم واحد يجب أن يكون تاريخ البدء والانتهاء نفس اليوم.');
                return;
            }

            // 7. multi_day: يومي عمل على الأقل + حد 120 يوم
            if ($type === SubscriptionDuration::MULTI_DAY->value) {
                if ($start->eq($end)) {
                    $v->errors()->add('end_date', 'اشتراك عدة أيام يجب أن يكون تاريخ الانتهاء بعد تاريخ البدء.');
                    return;
                }

                $workingDays = 0;
                $cur = $start->copy();
                while ($cur->lte($end)) {
                    if (!$cur->isFriday() && !$cur->isSaturday()) {
                        $workingDays++;
                    }
                    $cur->addDay();
                }
                if ($workingDays < 2) {
                    $v->errors()->add('end_date', 'اشتراك عدة أيام يجب أن يحتوي على يومي عمل على الأقل.');
                    return;
                }

                if ($end->gt($start->copy()->addDays(120))) {
                    $v->errors()->add('end_date', 'مدة الاشتراك لا يمكن أن تتجاوز 120 يوماً (4 أشهر).');
                }
            }
        });
    }
}