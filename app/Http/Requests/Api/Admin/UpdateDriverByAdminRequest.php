<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverByAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $driverId = $this->route('id') ?? $this->route('driver');

        return [
            'full_name'      => ['nullable', 'string', 'max:150'],
            'phone_number'   => ['nullable', 'string', 'regex:/^(09[1-689][0-9]{7}|09[0-9]{8})$/'],
            'national_id'    => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'license_expiry' => ['nullable', 'date'],
            'status'         => ['nullable', 'string', 'in:Pending,Approved,Rejected,Suspended,Active,Inactive,active,approved,rejected,pending'],
            'is_active'      => ['nullable', 'boolean'],
            'reason'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'رقم الهاتف غير صالح، يجب أن يبدأ بـ 09 ويتكون من 10 أرقام.',
            'license_expiry.date'=> 'تاريخ انتهاء الرخصة غير صالح.',
            'status.in'          => 'حالة السائق المختارة غير صالحة.',
        ];
    }
}
