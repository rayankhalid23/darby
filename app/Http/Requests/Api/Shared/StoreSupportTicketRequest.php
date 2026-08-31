<?php

namespace App\Http\Requests\Api\Shared;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'    => ['required', 'string', Rule::in(['general', 'technical', 'financial', 'trip', 'party', 'driver', 'parent'])],
            'description' => 'required|string|min:3|max:5000',

            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',

            // حقول مساعدة اختيارية (إن وجدت يتم ربطها)
            'financial_reference_type' => ['nullable', Rule::in(['invoice', 'transaction'])],
            'financial_reference_id'   => 'nullable|integer',

            'target_user_id' => 'nullable|integer|exists:users,id',
            'trip_id'        => 'nullable|integer|exists:trips,id',
        ];
    }

    public function messages(): array
    {
        return [
            'category.required'    => 'نوع المشكلة مطلوب.',
            'category.in'          => 'نوع المشكلة المحدد غير صالح.',
            'description.required' => 'نص المشكلة مطلوب.',
            'description.min'      => 'يجب ألا يقل نص المشكلة عن 3 أحرف.',
            'description.max'      => 'نص المشكلة لا يتجاوز 5000 حرف.',

            'attachments.array'   => 'المرفقات يجب أن تكون في صيغة قائمة.',
            'attachments.max'     => 'لا يمكن رفع أكثر من 5 ملفات مرفقة.',
            'attachments.*.file'  => 'يجب أن يكون المرفق ملفاً صالحاً.',
            'attachments.*.mimes' => 'نوع الملف غير مدعوم (الصيغ المسموحة: jpeg, png, jpg, pdf).',
            'attachments.*.max'   => 'حجم الملف المرفق لا يتجاوز 5 ميجابايت.',

            'target_user_id.exists' => 'المستخدم المحدد غير موجود.',
            'trip_id.exists'        => 'الرحلة المحددة غير موجودة.',
        ];
    }
}
