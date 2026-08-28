<?php

namespace App\Http\Resources\Api\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'status'      => $this->status ?? 'pending',
            'total_price' => (float) ($this->total_price ?? 0),
            'discount_amount'             => (float) ($this->discount_amount ?? 0),             // إجمالي الخصم للطلب الرئيسي
            'total_amount_after_discount' => (float) ($this->total_amount_after_discount ?? 0), // إجمالي السعر بعد الخصم للطلب الرئيسي
            'notes'       => $this->notes,
    
            'driver' => [
                'id'    => $this->driver_id,
                'name'  => $this->driver?->user?->full_name ?? $this->driver?->user?->name ?? 'غير محدد',
                'phone' => $this->driver?->user?->phone_number ?? $this->driver?->user?->phone ?? null,
                'photo' => $this->driver?->user?->profile_photo_url ?? null,
            ],
    
            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    $pivot = $child->pivot;
                    
                    $homeAddr   = $child->address;
                    $schoolAddr = $child->school;
    
                    return [
                        'id'      => $child->id,
                        'name'    => $child->full_name ?? $child->name,
                        'photo'   => $child->photo_url ? asset($child->photo_url) : null,
                        'details' => [
                            'subscription_type'           => $pivot?->subscription_type,
                            'trip_direction'              => $pivot?->trip_direction ?? 'both',
                            'timing'                      => $pivot?->timing ?? 'BOTH',
                            'start_date'                  => $pivot?->start_date,
                            'end_date'                    => $pivot?->end_date,
                            'working_days_count'          => (int) ($pivot?->working_days_count ?? 0),
                            'distance_km'                 => (float) ($pivot?->distance_km ?? 0),
                            'trip_price'                  => (float) ($pivot?->trip_price ?? 0),
                            'price_per_child'             => (float) ($pivot?->price_per_child ?? 0),
                            'discount_amount'             => (float) ($pivot?->discount_amount ?? 0),             // ✅ قيمة الخصم للطفل
                            'total_amount_after_discount' => (float) ($pivot?->total_amount_after_discount ?? 0), // ✅ السعر المخفض (أو الأصلي لو مافيش)
                            'driver_net_price'            => (float) ($pivot?->driver_net_price ?? 0),            // ✅ صافي السائق بعد عمولة المنصة
                        ],
                        'pickup_location' => [
                            'id'        => $homeAddr?->id,
                            'address'   => $homeAddr?->address_line ?? $homeAddr?->label ?? 'منزل ولي الأمر',
                            'latitude'  => (float) ($homeAddr?->lat ?? 32.8872),
                            'longitude' => (float) ($homeAddr?->lng ?? 13.1913),
                        ],
                        'dropoff_location' => [
                            'id'        => $schoolAddr?->id,
                            'name'      => $schoolAddr?->name ?? 'المدرسة',
                            'address'   => $schoolAddr?->address_line ?? 'عنوان المدرسة',
                            'latitude'  => (float) ($schoolAddr?->lat ?? 32.8700),
                            'longitude' => (float) ($schoolAddr?->lng ?? 13.1800),
                        ],
                    ];
                });
            }),
    
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}