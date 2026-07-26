<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $driverUser = optional(optional($this->driver)->user);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString() ?? $this->start_date,
            'working_days_count' => (int) ($this->days_count ?? 0),
            'total_amount' => (float) ($this->total_price ?? 0),
            'children_count' => (int) ($this->children_count ?? $this->children()->count()),
            
            'driver' => [
                'id' => optional($this->driver)->id,
                'name' => $driverUser->full_name ?? null,
                'phone' => $driverUser->phone_number ?? null,
            ],

            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    // جلب بيانات الاشتراك الخاصة بالطفل إذا كانت مرتبطة عبر جدول وسيط أو علاقة
                    $pivot = $child->pivot ?? null;
                    
                    // جلب المدرسة الخاصة بالطفل
                    $school = optional($child->school);

                    // جلب عنوان المنزل المرتبط بالطفل أو بولي الأمر
                    $address = optional($child->address);

                    return [
                        'id' => $child->id,
                        'name' => $child->full_name,
                        'price' => (float) ($pivot->price_per_child ?? 0),
                        'gender' => $child->gender,
                        'age' => $child->age,
                        'photo_url' => $child->photo_url,
                        'subscription' => [
                            'type' => $pivot->subscription_type ?? $this->subscription_type ?? 'monthly',
                            'trip_type' => $pivot->trip_type ?? 'two_way',
                            'start_date' => $pivot->start_date ?? $this->start_date?->toDateString(),
                            'end_date' => $pivot->end_date ?? null,
                            'working_days_count' => (int) ($pivot->working_days_count ?? $this->working_days_count ?? 0),
                        ],
                        'school' => [
                            'id' => $school->id,
                            'name' => $school->name,
                            'address' => $school->address,
                        ],
                        'home' => [
                            'address' => $address->address ?? $address->label ?? 'منزل ولي الأمر',
                        ],
                    ];
                });
            }),

            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}