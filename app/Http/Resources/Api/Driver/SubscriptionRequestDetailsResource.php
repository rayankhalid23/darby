<?php

namespace App\Http\Resources\Api\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionRequestDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        // دمج مرن لجلب بيانات ولي الأمر (سواء كان User مباشرة أو عبر علاقة parent)
        $parentUser = optional($this->parent)->user ?? $this->parent;
        $discountFactor = 0.92; 

        // السعر الإجمالي
        $rawTotal = (float) ($this->total_price ?? $this->price ?? optional($this->subscriptionRequest)->total_price ?? 0);
        $netTotalAmount = round($rawTotal * $discountFactor, 2);

        // جلب الأطفال: سواء كانت علاقة مجموعة (children) أو طفل منفرد (child)
        $childrenList = collect();
        if ($this->relationLoaded('children') && $this->children) {
            $childrenList = $this->children;
        } elseif ($this->child) {
            $childrenList = collect([$this->child]);
        }

        return [
            'id' => $this->id,
            'status' => [
                'value' => is_array($this->status) ? ($this->status['value'] ?? $this->status) : $this->status,
            ],
            'notes'          => $this->notes ?? $this->general_notes ?? optional($this->subscriptionRequest)->notes ?? null, 
            'total_amount'   => $netTotalAmount,
            'currency'       => 'د.ل', 
            'children_count' => (int) ($this->children_count ?? ($childrenList->count() ?: 1)),

            'parent' => [
                'id'     => optional($parentUser)->id,
                'name'   => optional($parentUser)->full_name ?? optional($parentUser)->name ?? 'غير محدد',
                'phone'  => optional($parentUser)->phone_number ?? optional($parentUser)->phone ?? null,
                'email'  => optional($parentUser)->email ?? null,
                'avatar' => optional($parentUser)->avatar_url ?? optional($parentUser)->photo_url ?? null,
            ],

            'children' => $childrenList->map(function ($child) use ($discountFactor) {
                $pivot   = $child->pivot ?? null;
                $school  = optional($child->school ?? $this->school);
                $address = optional($child->address);

                $rawChildTotal = (float) ($pivot->price_per_child ?? $pivot->total_price ?? $this->price ?? $child->price ?? 0);
                $rawTripPrice  = (float) ($pivot->trip_price ?? ($rawChildTotal > 0 ? $rawChildTotal / 40 : 0)); // 20 يوم * رحلتين

                $netChildTotal = round($rawChildTotal * $discountFactor, 2);
                $netTripPrice  = round($rawTripPrice * $discountFactor, 2);

                $subReq = $this->subscriptionRequest ?? $this;

                return [
                    'id'        => $child->id,
                    'name'      => $child->full_name ?? $child->name,
                    'gender'    => $child->gender,
                    'age'       => $child->age,
                    'grade'     => $child->grade ?? $child->class_name ?? 'غير محدد',
                    'photo_url' => $child->photo_url,

                    'notes' => [
                        'child_notes' => $child->medical_notes ?? $pivot->child_notes ?? null,
                    ],

                    'pricing' => [
                        'trip_price'  => $netTripPrice,
                        'total_price' => $netChildTotal,
                    ],

                    'subscription_period' => [
                        'start_date'         => $pivot->start_date ?? $subReq->start_date ?? null,
                        'end_date'           => $pivot->end_date ?? $subReq->end_date ?? null,
                        'working_days_count' => (int) ($pivot->working_days_count ?? $subReq->days_count ?? 20),
                    ],

                    'trip_details' => [
                        'subscription_type' => $pivot->subscription_type ?? $subReq->subscription_type ?? 'monthly',
                        'trip_direction'    => $pivot->trip_direction ?? $subReq->direction ?? 'two_way',
                        'timing'            => $pivot->timing ?? $subReq->timing ?? null,
                    ],

                    'school' => [
                        'id'      => $school->id,
                        'name'    => $school->name,
                        'address' => $school->address,
                        'lat'     => (float) ($school->lat ?? $school->latitude ?? $this->dropoff_lat ?? 0),
                        'lng'     => (float) ($school->lng ?? $school->longitude ?? $this->dropoff_lng ?? 0),
                    ],

                    'home' => [
                        'address' => $this->pickup_label ?? $address->label ?? $address->address ?? 'منزل ولي الأمر',
                        'lat'     => (float) ($this->pickup_lat ?? $address->lat ?? $address->latitude ?? 0),
                        'lng'     => (float) ($this->pickup_lng ?? $address->lng ?? $address->longitude ?? 0),
                    ],
                ];
            })->values(),

            'created_at'           => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}