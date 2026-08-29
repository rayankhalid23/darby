<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search_query'  => ['nullable', 'string', 'max:255'],
            'query'         => ['nullable', 'string', 'max:255'],
            'keyword'       => ['nullable', 'string', 'max:255'],
            'name'          => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:255'],
            'driver_gender' => ['nullable', 'string', Rule::in(['male', 'female', 'both'])],
            'has_ac'        => ['nullable', 'boolean'],
            'child_ids'     => ['nullable', 'array'],
            'child_ids.*'   => ['required', 'integer', 'exists:children,id'],
        ];
    }

    /**
     * تجهيز البيانات قبل عملية التحقق (تحويل القيم النصية وتوحيد حقل البحث)
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('search_query')) {
            $searchVal = $this->input('query') 
                ?? $this->input('keyword') 
                ?? $this->input('name') 
                ?? $this->input('phone') 
                ?? $this->input('search');

            if ($searchVal !== null && trim((string) $searchVal) !== '') {
                $this->merge(['search_query' => trim((string) $searchVal)]);
            }
        }

        if ($this->has('has_ac') && $this->input('has_ac') !== null) {
            $this->merge([
                'has_ac' => filter_var($this->has_ac, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'child_ids.array'    => 'قائمة الأطفال يجب أن تكون مصفوفة صحيحة.',
            'child_ids.*.exists' => 'أحد الأطفال المحددين غير موجود في النظام.',
            'driver_gender.in'   => 'جنس السائق المحدد غير صحيح (مسموح: male, female, both).',
            'has_ac.boolean'     => 'قيمة التكييف يجب أن تكون true أو false.',
        ];
    }
}