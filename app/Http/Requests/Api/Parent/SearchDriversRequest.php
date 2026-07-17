<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SearchDriversRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مخولاً لإجراء هذا الطلب.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قواعد التحقق من البيانات
     */
    public function rules(): array
    {
        // جلب الـ parent_id الفعلي المقابل للمستخدم المسجل حالياً لضمان دقة التحقق
        $parent = DB::table('parents')->where('user_id', auth()->id())->first();
        $parentId = $parent ? $parent->id : 0;

        return [
            'search_query'  => ['nullable', 'string', 'max:255'],
            'driver_gender' => ['nullable', 'string', Rule::in(['male', 'female', 'both'])],
            'has_ac'        => ['nullable'],
            
            // تحقق ذكي وصارم للتأكد من وجود الأطفال وتبعيّتهم الفعليه لولي الأمر الحالي
            'child_ids'     => ['nullable', 'array'],
            'child_ids.*'   => [
                'integer',
                // 🔥 تم إضافة "use ($parentId)" هنا لكي يرى الكود المتغير بالداخل بدون مشاكل
                Rule::exists('children', 'id')->where(function ($query) use ($parentId) {
                    $query->where('parent_id', $parentId);
                }),
            ],
        ];
    }

    /**
     * تخصيص رسائل الخطأ لتظهر بشكل احترافي في الفرونت إند
     */
    public function messages(): array
    {
        return [
            'child_ids.*.exists' => 'أحد الأطفال المحددين غير موجود أو لا ينتمي لحسابك.',
            'driver_gender.in'   => 'جنس السائق المحدد غير صحيح.',
        ];
    }
}