<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SearchDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parent = DB::table('parents')->where('user_id', auth()->id())->first();
        $parentId = $parent ? $parent->id : 0;

        return [
            'search_query'  => ['nullable', 'string', 'max:255'],
            'driver_gender' => ['nullable', 'string', Rule::in(['male', 'female', 'both'])],
            'has_ac'        => ['nullable'],
            
            // 🔥 جعل الأطفال إجباريين لضمان حساب السعر دائماً
            'child_ids'     => ['required', 'array', 'min:1'],
            'child_ids.*'   => [
                'integer',
                Rule::exists('children', 'id')->where(function ($query) use ($parentId) {
                    $query->where('parent_id', $parentId);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'child_ids.required' => 'يرجى تحديد طفل واحد على الأقل لحساب تكلفة الاشتراك.',
            'child_ids.min'      => 'يجب اختيار طفل واحد على الأقل.',
            'child_ids.*.exists' => 'أحد الأطفال المحددين غير موجود أو لا ينتمي لحسابك.',
            'driver_gender.in'   => 'جنس السائق المحدد غير صحيح.',
        ];
    }
}