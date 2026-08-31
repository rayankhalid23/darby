<?php

namespace App\Http\Requests\Api\Trip;

use App\Models\Shared\Trip;
use App\Models\Shared\TripBreakdownDispatch;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class DriverAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // دعم النمط الجديد: date + trip_ids + reason
            'date'        => 'required_without:dates|nullable|date|after_or_equal:today',
            'trip_ids'    => 'required_without:dates|nullable|array|min:1',
            'trip_ids.*'  => 'required|integer|exists:trips,id',
            'reason'      => 'nullable|string|max:1000',

            // دعم النمط القديم للتوافقية العكسية: dates array
            'dates'       => 'required_without:trip_ids|nullable|array|min:1',
            'dates.*'     => 'required|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required_without'          => 'يرجى تحديد تاريخ الغياب.',
            'date.date'                      => 'صيغة تاريخ الغياب غير صحيحة.',
            'date.after_or_equal'            => 'عذراً كابتن، لا يمكنك تسجيل غياب بأثر رجعي؛ التاريخ يجب أن يبدأ من اليوم فصاعداً.',

            'trip_ids.required_without'      => 'يرجى إرسال أرقام الرحلات (trip_ids) المراد الغياب عنها.',
            'trip_ids.array'                 => 'أرقام الرحلات يجب أن تكون مصفوفة.',
            'trip_ids.min'                   => 'يجب تحديد رحلة واحدة على الأقل لطلب الغياب عنها.',
            'trip_ids.*.required'            => 'رقم الرحلة مطلوب.',
            'trip_ids.*.integer'             => 'رقم الرحلة يجب أن يكون رقماً صحيحاً.',
            'trip_ids.*.exists'              => 'إحدى الرحلات المحددة غير موجودة في النظام.',

            'reason.string'                  => 'سبب الغياب يجب أن يكون نصاً.',
            'reason.max'                     => 'سبب الغياب لا يمكن أن يتجاوز 1000 حرف.',

            'dates.required_without'         => 'يجب على السائق تحديد تواريخ الغياب الخاصة به.',
            'dates.array'                    => 'صيغة البيانات المرسلة للتواريخ يجب أن تكون مصفوفة.',
            'dates.min'                      => 'يجب على السائق إدخال يوم غياب واحد على الأقل.',
            'dates.*.required'               => 'تاريخ الغياب مطلوب.',
            'dates.*.date'                   => 'يوجد تاريخ غير صحيح ضمن القائمة المرسلة.',
            'dates.*.after_or_equal'         => 'عذراً كابتن، لا يمكنك تسجيل غياب بأثر رجعي؛ التواريخ يجب أن تبدأ من اليوم فصاعداً.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $driver = $user?->driver;

            if (!$driver) {
                $validator->errors()->add('driver', 'حساب السائق غير موجود أو غير مرتبط بهذا المستخدم.');
                return;
            }

            $tripIds = $this->input('trip_ids');
            $dateInput = $this->input('date');

            if (!empty($tripIds) && is_array($tripIds)) {
                $targetDate = $dateInput ? Carbon::parse($dateInput)->toDateString() : null;

                foreach ($tripIds as $tripId) {
                    $trip = Trip::find($tripId);
                    if (!$trip) {
                        $validator->errors()->add('trip_ids', "الرحلة رقم ({$tripId}) غير موجودة في النظام.");
                        continue;
                    }

                    // 1. التحقق من أن الرحلة معينة فعلياً لهذا السائق
                    if ($trip->driver_id !== $driver->id) {
                        $validator->errors()->add('trip_ids', "الرحلة رقم ({$tripId}) غير معينة لك كابتن.");
                        continue;
                    }

                    // 2. التحقق من مطابقة تاريخ الرحلة لتاريخ الغياب المطلوب
                    $tripDate = $trip->trip_date ? Carbon::parse($trip->trip_date)->toDateString() : null;
                    if ($targetDate && $tripDate && $tripDate !== $targetDate) {
                        $validator->errors()->add('trip_ids', "الرحلة رقم ({$tripId}) مجدولة في تاريخ ({$tripDate}) ولا تطابق تاريخ الغياب المحدد ({$targetDate}).");
                        continue;
                    }

                    // 3. التحقق الأمني: لم يتم تعيينها مسبقاً لسائق بديل
                    $hasSubstitute = TripBreakdownDispatch::where('trip_id', $tripId)
                        ->whereNotNull('substitute_driver_id')
                        ->whereIn('status', ['accepted', 'completed', 'in_progress'])
                        ->exists();

                    if ($hasSubstitute) {
                        $validator->errors()->add('trip_ids', "الرحلة رقم ({$tripId}) تم تعيينها مسبقاً لسائق بديل ولا يمكن طلب الغياب عنها.");
                        continue;
                    }

                    // 4. لا يمكن طلب الغياب لرحلة تم اكتمالها أو إلغاؤها بالفعل
                    if (in_array($trip->status, ['completed', 'cancelled'])) {
                        $validator->errors()->add('trip_ids', "الرحلة رقم ({$tripId}) منتهية أو ملغاة بالفعل ولا يمكن تسجيل الغياب عنها.");
                        continue;
                    }
                }
            }
        });
    }
}