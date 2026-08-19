<?php

namespace App\Http\Requests\Api\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\driver\DriverShift;
use App\Enums\Shared\SchoolStage;
use App\Enums\Shared\SubscriptionDuration;

class UpdateDriverPreferencesRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق المطبقة على الطلب
     */
    public function rules(): array
    {
        return [
            'morning_go'        => ['required', 'boolean'],
            'morning_return'    => ['required', 'boolean'],
            'afternoon_go'      => ['required', 'boolean'],
            'afternoon_return'  => ['required', 'boolean'],
            'subscription_type' => ['required', 'string', Rule::in(SubscriptionDuration::driverValues())],
            'school_stages'     => ['required', 'array', 'min:1'],
            'school_stages.*'   => ['required', 'string', Rule::in(array_column(SchoolStage::cases(), 'value'))],
            'zones'             => ['required', 'array', 'min:1'],
            'zones.*'           => ['required', 'integer', 'exists:zones,id'],
        ];
    }

    /**
     * تحقق إضافي: يجب اختيار فترة عمل واحدة على الأقل
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $data = $this->validated();
            if (
                empty($data['morning_go']) &&
                empty($data['morning_return']) &&
                empty($data['afternoon_go']) &&
                empty($data['afternoon_return'])
            ) {
                $v->errors()->add('shift_slots', 'يجب اختيار فترة عمل واحدة على الأقل (صباحي ذهاب أو إياب أو مسائي ذهاب أو إياب).');
            }
        });
    }
}