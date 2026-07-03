<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class SearchDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ───── البحث النصي (اسم السائق أو رقم هاتفه) ─────
            'search_query'  => 'nullable|string|max:100',

            // ───── فلاتر السائق المباشرة ─────
            'driver_gender' => 'nullable|in:male,female',
            'has_ac'        => 'nullable|boolean',

            // ───── تحديد الأطفال (اختياري – إذا فارغ يعمل على كل الأطفال) ─────
            'child_ids'     => 'nullable|array',
            'child_ids.*'   => [
                'integer',
                // التحقق أن الطفل فعلاً يتبع لولي الأمر الحالي
                'exists:children,id,parent_id,' . auth()->id(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'driver_gender.in'     => 'جنس السائق يجب أن يكون male أو female.',
            'has_ac.boolean'       => 'حقل المكيف يجب أن يكون true أو false.',
            'child_ids.array'      => 'child_ids يجب أن يكون مصفوفة.',
            'child_ids.*.integer'  => 'كل عنصر في child_ids يجب أن يكون رقماً صحيحاً.',
            'child_ids.*.exists'   => 'أحد الأطفال المحددين غير موجود أو لا ينتمي لحسابك.',
        ];
    }
}