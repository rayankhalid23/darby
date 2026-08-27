<?php

namespace App\Http\Resources\Api\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        $parentUser = optional(optional($this->parent)->user);
        $discountFactor = 0.92; 

        $rawTotal = (float) ($this->total_price ?? 0);
        $netTotalAmount = round($rawTotal * $discountFactor, 2);

        return [
            'id'                   => $this->id,
            'status'               => [
                'value' => $this->status,
            ],
            // الملاحظة العامة لطلب الاشتراك (تأكد من اسم الحقل في جدول subscription_requests إذا كان notes أو general_notes)
            'notes'                => $this->notes ?? $this->general_notes ?? null, 
            'total_amount'         => $netTotalAmount,
            'currency'             => 'د.ل', 
            'children_count'       => (int) ($this->children_count ?? ($this->relationLoaded('children') ? $this->children->count() : 1)),

            'parent' => [
                'id'       => optional($this->parent)->id,
                'name'     => $parentUser->full_name ?? $parentUser->name ?? 'غير محدد',
                'phone'    => $parentUser->phone_number ?? $parentUser->phone ?? null,
                'email'    => $parentUser->email ?? null,
                'avatar'   => $parentUser->avatar_url ?? null,
            ],

            'children' => $this->whenLoaded('children', function () use ($discountFactor) {
                return $this->children->map(function ($child) use ($discountFactor) {
                    $pivot   = $child->pivot ?? null;
                    $school  = optional($child->school);
                    $address = optional($child->address);

                    $rawChildTotal = (float) ($pivot->price_per_child ?? $pivot->total_price ?? $child->price ?? 0);
                    $rawTripPrice  = (float) ($pivot->trip_price ?? ($rawChildTotal / 2));

                    $netChildTotal = round($rawChildTotal * $discountFactor, 2);
                    $netTripPrice  = round($rawTripPrice * $discountFactor, 2);

                    return [
                        'id'         => $child->id,
                        'name'       => $child->full_name ?? $child->name,
                        'gender'     => $child->gender,
                        'age'        => $child->age,
                        'grade'      => $child->grade ?? $child->class_name ?? 'غير محدد',
                        'photo_url'  => $child->photo_url,

                        // ملاحظات الطفل (ملاحظات الطلب الخاصة بالطفل من جدول الـ pivot + الملاحظات الطبية من جدول children)
                        'notes' => [
                          
                            'child_notes' => $child->medical_notes ?? null,
                        ],

                        'pricing' => [
                            'trip_price'  => $netTripPrice,
                            'total_price' => $netChildTotal,
                        ],

                        'subscription_period' => [
                            'start_date' => $pivot->start_date ?? null,
                            'end_date'   => $pivot->end_date ?? null,
                            'working_days_count' => (int) ($pivot->working_days_count ?? 0),
                        ],

                        'trip_details' => [
                            'subscription_type' => $pivot->subscription_type ?? 'monthly',
                            'trip_direction'    => $pivot->trip_direction ?? 'two_way',
                            'timing'            => $pivot->timing ?? null,
                        ],

                        'school' => [
                            'id'      => $school->id,
                            'name'    => $school->name,
                            'address' => $school->address,
                            'lat'     => (float) ($school->lat ?? 0),
                            'lng'     => (float) ($school->lng ?? 0),
                        ],

                        'home' => [
                            'address' => $address->label ?? 'منزل ولي الأمر',
                            'lat'     => (float) ($address->lat ?? 0),
                            'lng'     => (float) ($address->lng ?? 0),
                        ],
                    ];
                });
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}