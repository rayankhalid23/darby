<?php
namespace App\Http\Requests\Api\Trip;
use Illuminate\Foundation\Http\FormRequest;

class StartTripRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        return [
            'trip_type' => 'required|in:morning,evening',
        ];
    }

    public function messages(): array {
        return [
            'trip_type.required' => 'نوع الرحلة حقل إجباري ولا يمكن تركه فارغاً.',
            'trip_type.in'       => 'نوع الرحلة يجب أن يكون إما صباحية (morning) أو مسائية (evening) فقط.',
        ];
    }
}