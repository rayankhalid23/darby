<?php

namespace App\Http\Requests\Api\Parent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'التقييم مطلوب.',
            'rating.min'      => 'أقل تقييم هو نجمة واحدة.',
            'rating.max'      => 'أعلى تقييم هو 5 نجوم.',
            'comment.max'     => 'التعليق لا يتجاوز 2000 حرف.',
        ];
    }
}
