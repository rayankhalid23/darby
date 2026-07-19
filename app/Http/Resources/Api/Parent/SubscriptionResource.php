<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SubscriptionResource extends JsonResource
{
    /**
     * تحويل البيانات لتشمل تفاصيل الاشتراك الكاملة لولي الأمر
     */
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'status'         => $this->status,
            'pickup_time'    => $this->pickup_time ? Carbon::parse($this->pickup_time)->format('H:i') : null,
            'dropoff_time'   => $this->dropoff_time ? Carbon::parse($this->dropoff_time)->format('H:i') : null,
            
            // تفاصيل نقاط الركوب والتوصيل الجغرافية
            'pickup_location' => [
                'latitude'  => (float) $this->pickup_lat,
                'longitude' => (float) $this->pickup_lng,
                'label'     => $this->pickup_label,
            ],
            'dropoff_location' => [
                'latitude'  => (float) $this->dropoff_lat,
                'longitude' => (float) $this->dropoff_lng,
                'label'     => $this->dropoff_label,
            ],

            // تفاصيل الطفل والمدرسة المرتبطة
            'child' => $this->whenLoaded('child', function() {
                return [
                    'id'         => $this->child->id,
                    'name'       => $this->child->name,
                    'school_name'=> $this->child->school->name ?? null,
                ];
            }),

            // تفاصيل العقد والتواريخ وقيمة الاشتراك
            'contract' => $this->whenLoaded('contract', function() {
                return [
                    'id'              => $this->contract->id,
                    'contract_number' => $this->contract->contract_number,
                    'start_date'      => $this->contract->start_date,
                    'end_date'        => $this->contract->end_date,
                    'total_price'     => (float) $this->contract->total_price,
                    'status'          => $this->contract->status,
                ];
            }),

            // تفاصيل السائق وبيانات المركبة
            'driver' => $this->whenLoaded('driver', function() {
                return [
                    'id'        => $this->driver->id,
                    'name'      => $this->driver->user->full_name ?? 'غير معروف',
                    'phone'     => $this->driver->user->phone ?? null,
                    'vehicle'   => $this->driver->vehicles->first() ? [
                        'plate_number' => $this->driver->vehicles->first()->plate_number,
                        'brand'        => $this->driver->vehicles->first()->brand,
                        'model'        => $this->driver->vehicles->first()->model,
                        'color'        => $this->driver->vehicles->first()->color,
                    ] : null,
                ];
            }),
            
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}