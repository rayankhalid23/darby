<?php

namespace App\Http\Resources\Api\Driver;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DriverSubscriptionResource extends JsonResource
{
    /**
     * تحويل البيانات لتشمل تفاصيل الاشتراك الكاملة للسائق بدون بيانات السائق نفسه
     */
    public function toArray($request)
    {
        // 1. حساب عدد أيام العمل بين تاريخ البداية والنهاية (مستبعداً الجمعة والسبت)
        $workingDaysCount = 0;
        $startDate = optional($this->contract)->start_date ?? ($this->start_date ?? null);
        $endDate   = optional($this->contract)->end_date   ?? ($this->end_date   ?? null);

        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate);
                $end   = Carbon::parse($endDate);
                
                if ($start->lte($end)) {
                    $period = CarbonPeriod::create($start, $end);
                    foreach ($period as $date) {
                        // 5 = الجمعة، 6 = السبت في Carbon
                        if (!in_array($date->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY])) {
                            $workingDaysCount++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $workingDaysCount = 0;
            }
        }

        // 2. دالة مساعدة لاستخراج أول إحداثي غير صفري لمنع ظهور الرقم 0
        $getValidCoord = function (...$values) {
            foreach ($values as $val) {
                if (!is_null($val) && (float)$val != 0) {
                    return (float)$val;
                }
            }
            return 0.0;
        };

        // استخراج إحداثيات المنزل بدقة
        $homeLat = $getValidCoord(
            $this->pickup_lat,
            optional(optional($this->child)->address)->lat,
            optional(optional(optional($this->parent)->addresses)->first())->lat
        );

        $homeLng = $getValidCoord(
            $this->pickup_lng,
            optional(optional($this->child)->address)->lng,
            optional(optional(optional($this->parent)->addresses)->first())->lng
        );

        // استخراج إحداثيات المدرسة بدقة
        $schoolLat = $getValidCoord(
            $this->dropoff_lat,
            optional($this->school)->lat,
            optional(optional($this->child)->school)->lat
        );

        $schoolLng = $getValidCoord(
            $this->dropoff_lng,
            optional($this->school)->lng,
            optional(optional($this->child)->school)->lng
        );

        // 3. بناء هيكل الـ JSON المرجع للـ API
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'pickup_time'    => $this->pickup_time ? Carbon::parse($this->pickup_time)->format('H:i') : null,
            'dropoff_time'   => $this->dropoff_time ? Carbon::parse($this->dropoff_time)->format('H:i') : null,
            
            // نوع الرحلة: ذهاب، إياب، أو الزوز
            'trip_type'      => $this->direction ?? ($this->subscription_type ?? 'both'),
            
            // نقاط الركوب والتوصيل
            'pickup_location' => [
                'latitude'  => $homeLat,
                'longitude' => $homeLng,
                'label'     => $this->pickup_label ?? 'المنزل',
            ],
            'dropoff_location' => [
                'latitude'  => $schoolLat,
                'longitude' => $schoolLng,
                'label'     => $this->dropoff_label ?? 'المدرسة',
            ],

            // إحداثيات الطول والعرض المفصلة للمنزل والمدرسة
            'coordinates' => [
                'home' => [
                    'latitude'  => $homeLat,
                    'longitude' => $homeLng,
                ],
                'school' => [
                    'latitude'  => $schoolLat,
                    'longitude' => $schoolLng,
                ],
            ],

            // تفاصيل ولي الأمر (لأن السائق يحتاج لمعرفة من هو ولي أمر الطالب وطريقة التواصل معه)
            'parent' => $this->whenLoaded('parent', function() {
                $parentUser = optional($this->parent->user);
                return [
                    'id'    => optional($this->parent)->id,
                    'name'  => $parentUser->full_name ?? ($parentUser->name ?? 'غير معروف'),
                    'phone' => $parentUser->phone ?? ($parentUser->phone_number ?? null),
                    'email' => $parentUser->email ?? null,
                ];
            }),

            // تفاصيل الطفل الكاملة (شاملة كافة البيانات المتاحة عن الطفل)
            'child' => $this->whenLoaded('child', function() {
                return [
                    'id'            => $this->child->id,
                    'name'          => $this->child->full_name ?? ($this->child->name ?? null),
                    'first_name'    => $this->child->first_name ?? null,
                    'last_name'     => $this->child->last_name ?? null,
                    'age'           => $this->child->age ?? null,
                    'gender'        => $this->child->gender ?? null,
                    'grade'         => $this->child->grade ?? ($this->child->school_grade ?? null),
                    'photo_url'     => $this->child->photo_url ?? null,
                    'notes'         => $this->child->notes ?? ($this->child->medical_notes ?? null),
                    'school'        => $this->whenLoaded('school', function() {
                        return [
                            'id'      => optional($this->school)->id,
                            'name'    => optional($this->school)->name,
                            'address' => optional($this->school)->address,
                        ];
                    }, [
                        'id'      => optional($this->child->school)->id,
                        'name'    => optional($this->child->school)->name ?? (optional($this->school)->name ?? null),
                        'address' => optional($this->child->school)->address ?? null,
                    ]),
                ];
            }),

            // تفاصيل العقد وأيام العمل
            'contract' => $this->whenLoaded('contract', function() use ($workingDaysCount) {
                return [
                    'id'                 => $this->contract->id,
                    'contract_number'    => $this->contract->contract_number,
                    'start_date'         => $this->contract->start_date,
                    'end_date'           => $this->contract->end_date,
                    'total_working_days' => $workingDaysCount,
                    'total_price'        => (float) $this->contract->total_price,
                    'status'             => $this->contract->status,
                ];
            }),
            
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}