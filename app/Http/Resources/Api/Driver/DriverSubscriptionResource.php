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

        // حساب أسعار الأطفال وصافي السائق بدقة مع Fallback للسجلات القديمة
        $totalRawPrice = (float) ($this->total_price ?? 0);
        $totalDiscount = (float) ($this->discount_amount ?? 0);
        $totalAfterDiscount = (float) ($this->total_amount_after_discount ?? max(0, $totalRawPrice - $totalDiscount));
        
        $netTotalAmount = 0;
        if ($this->relationLoaded('children') && $this->children) {
            $netTotalAmount = (float) $this->children->sum(function ($child) {
                $pivot = $child->pivot ?? null;
                if (!$pivot) return 0;
                $net = (float) ($pivot->driver_net_price ?? 0);
                if ($net <= 0) {
                    $raw = (float) ($pivot->price_per_child ?? $pivot->trip_price ?? 0);
                    $disc = (float) ($pivot->discount_amount ?? 0);
                    $afterDisc = (float) ($pivot->total_amount_after_discount ?? max(0, $raw - $disc));
                    $net = round($afterDisc * 0.92, 2);
                }
                return $net;
            });
        } else {
            $netTotalAmount = round($totalAfterDiscount * 0.92, 2);
        }

        return [
            'id'                     => $this->id,
            'status'                 => [
                'value' => $this->status,
            ],
            'notes'                  => $this->notes ?? $this->general_notes ?? null, 
            'total_amount'           => round($netTotalAmount, 2), // صافي مستحقات السائق للطلب
            'driver_net_total'       => round($netTotalAmount, 2),
            'original_total'         => round($totalRawPrice, 2),
            'discount_total'         => round($totalDiscount, 2),
            'total_after_discount'   => round($totalAfterDiscount, 2),
            'currency'               => 'د.ل', 
            'children_count'         => (int) ($this->children_count ?? ($this->relationLoaded('children') ? $this->children->count() : 1)),

            'parent' => [
                'id'       => optional($this->parent)->id,
                'name'     => $parentUser->full_name ?? $parentUser->name ?? 'غير محدد',
                'phone'    => $parentUser->phone_number ?? $parentUser->phone ?? null,
                'email'    => $parentUser->email ?? null,
                'avatar'   => $parentUser->avatar_url ?? null,
            ],

            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    $pivot   = $child->pivot ?? null;
                    $school  = optional($child->school);
                    $address = optional($child->address);

                    $rawChildPrice = (float) ($pivot->price_per_child ?? $pivot->trip_price ?? 0);
                    $tripPrice      = (float) ($pivot->trip_price ?? $rawChildPrice);
                    $discountAmt    = (float) ($pivot->discount_amount ?? 0);
                    $afterDiscount  = (float) ($pivot->total_amount_after_discount ?? max(0, $rawChildPrice - $discountAmt));
                    if ($afterDiscount <= 0 && $rawChildPrice > 0) {
                        $afterDiscount = max(0, $rawChildPrice - $discountAmt);
                    }

                    $driverNetPrice = (float) ($pivot->driver_net_price ?? 0);
                    if ($driverNetPrice <= 0 && $afterDiscount > 0) {
                        $driverNetPrice = round($afterDiscount * 0.92, 2);
                    }
                    $platformFee = max(0, round($afterDiscount - $driverNetPrice, 2));

                    return [
                        'id'         => $child->id,
                        'name'       => $child->full_name ?? $child->name,
                        'gender'     => $child->gender,
                        'age'        => $child->age,
                        'grade'      => $child->grade ?? $child->class_name ?? 'غير محدد',
                        'photo_url'  => $child->photo_url,

                        'notes' => [
                            'child_notes' => $child->medical_notes ?? null,
                        ],

                        'pricing' => [
                            'trip_price'                  => $tripPrice,
                            'original_price'              => $rawChildPrice,
                            'price_per_child'             => $rawChildPrice,
                            'discount_amount'             => $discountAmt,
                            'total_amount_after_discount' => $afterDiscount,
                            'platform_commission'         => $platformFee,
                            'driver_net_price'            => $driverNetPrice,
                            'total_price'                 => $driverNetPrice, // صافي السائق
                        ],

                        'subscription_period' => [
                            'start_date'         => $pivot->start_date ?? null,
                            'end_date'           => $pivot->end_date ?? null,
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

            'created_at'           => $this->created_at?->toIso8601String(),
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}