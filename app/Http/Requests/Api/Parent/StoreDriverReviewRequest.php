<?php

namespace App\Http\Requests\Api\Parent;

use App\Models\Shared\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => [
                'required',
                'integer',
                'exists:drivers,id',
                Rule::unique('driver_reviews', 'driver_id')
                    ->where('parent_id', auth()->id())
                    ->whereNull('deleted_at'),
            ],
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'driver_id.required'  => 'معرّف السائق مطلوب.',
            'driver_id.exists'    => 'السائق المحدد غير موجود في النظام.',
            'driver_id.unique'    => 'لقد قيمت هذا السائق مسبقاً. يمكنك تعديل تقييمك بدلاً من إضافة تقييم جديد.',
            'rating.required'    => 'التقييم مطلوب.',
            'rating.min'         => 'أقل تقييم هو نجمة واحدة.',
            'rating.max'         => 'أعلى تقييم هو 5 نجوم.',
            'comment.max'        => 'التعليق لا يتجاوز 2000 حرف.',
        ];
    }
}
