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

        $subReq = $this->subscriptionRequest ?? $this;

        // جلب الأطفال: سواء كانت علاقة مجموعة (children) أو طفل منفرد (child)
        $childrenList = collect();
        if ($this->relationLoaded('children') && $this->children) {
            $childrenList = $this->children;
        } elseif (isset($this->child) && $this->child) {
            $childrenList = collect([$this->child]);
        }

        // حساب السعر الإجمالي وصافي السائق
        $rawTotal = (float) ($this->total_price ?? $this->price ?? optional($this->subscriptionRequest)->total_price ?? 0);
        $totalDiscount = (float) ($this->discount_amount ?? optional($this->subscriptionRequest)->discount_amount ?? 0);
        $totalAfterDiscount = (float) ($this->total_amount_after_discount ?? optional($this->subscriptionRequest)->total_amount_after_discount ?? max(0, $rawTotal - $totalDiscount));

        $netTotalAmount = 0.0;
        if ($childrenList->isNotEmpty()) {
            $netTotalAmount = (float) $childrenList->sum(function ($child) {
                $pivot = $child->pivot ?? null;
                if (!$pivot) return 0;
                $net = (float) ($pivot->driver_net_price ?? 0);
                if ($net <= 0) {
                    $raw = (float) ($pivot->price_per_child ?? $pivot->trip_price ?? 0);
                    $disc = (float) ($pivot->discount_amount ?? 0);
                    $afterDisc = (float) ($pivot->total_amount_after_discount ?? max(0, $raw - $disc));
                    $net = round($afterDisc * (1 - \App\Models\Shared\PricingSetting::commissionRateFraction()), 2);
                }
                return $net;
            });
        }
        if ($netTotalAmount <= 0) {
            $netTotalAmount = round($totalAfterDiscount * (1 - \App\Models\Shared\PricingSetting::commissionRateFraction()), 2);
        }

        $resolvedState = $subReq instanceof \App\Models\Shared\SubscriptionRequest 
            ? $subReq->resolveState() 
            : ($this->resource instanceof \App\Models\Shared\SubscriptionRequest ? $this->resource->resolveState() : ['state' => 'active', 'status' => 'active', 'state_label' => 'ساري ومفعل', 'status_text' => 'اشتراك نشط وساري', 'is_active' => true]);

        return [
            'id'                      => $this->id,
            'subscription_id'         => $this->id,
            'subscription_request_id' => $this->id,
            'state'                   => $resolvedState['state'],
            'state_label'             => $resolvedState['state_label'],
            'status'                  => [
                'value' => $resolvedState['status'],
                'label' => $resolvedState['state_label'],
            ],
            'status_value'            => $resolvedState['status'],
            'status_text'             => $resolvedState['status_text'],
            'is_active'               => $resolvedState['is_active'],
            'notes'                   => $this->notes ?? $this->general_notes ?? optional($this->subscriptionRequest)->notes ?? null, 
            'total_amount'            => round($netTotalAmount, 2), // صافي مستحقات السائق للطلب بالكامل
            'driver_net_total'     => round($netTotalAmount, 2),
            'original_total'       => round($rawTotal, 2),
            'discount_total'       => round($totalDiscount, 2),
            'total_after_discount' => round($totalAfterDiscount, 2),
            'currency'             => 'د.ل', 
            'children_count'       => (int) ($this->children_count ?? ($childrenList->count() ?: 1)),

            'parent' => [
                'id'     => optional($parentUser)->id,
                'name'   => optional($parentUser)->full_name ?? optional($parentUser)->name ?? 'غير محدد',
                'phone'  => optional($parentUser)->phone_number ?? optional($parentUser)->phone ?? null,
                'email'  => optional($parentUser)->email ?? null,
                'avatar' => optional($parentUser)->avatar_url ?? optional($parentUser)->photo_url ?? null,
            ],

            'children' => $childrenList->map(function ($child) use ($subReq) {
                $pivot   = $child->pivot ?? null;
                $school  = optional($child->school ?? $this->school);
                $address = optional($child->address);

                $rawChildPrice = (float) ($pivot->price_per_child ?? $pivot->trip_price ?? 0);
                $tripPrice     = (float) ($pivot->trip_price ?? $rawChildPrice);
                $discountAmt   = (float) ($pivot->discount_amount ?? 0);
                $afterDiscount = (float) ($pivot->total_amount_after_discount ?? max(0, $rawChildPrice - $discountAmt));
                if ($afterDiscount <= 0 && $rawChildPrice > 0) {
                    $afterDiscount = max(0, $rawChildPrice - $discountAmt);
                }
                $discountPercent = $rawChildPrice > 0 ? round(($discountAmt / $rawChildPrice) * 100, 2) : 0.0;

                $driverNetPrice = (float) ($pivot->driver_net_price ?? 0);
                if ($driverNetPrice <= 0 && $afterDiscount > 0) {
                    $driverNetPrice = round($afterDiscount * (1 - \App\Models\Shared\PricingSetting::commissionRateFraction()), 2);
                }
                $platformFeeAmount  = max(0, round($afterDiscount - $driverNetPrice, 2));
                $platformFeePercent = $afterDiscount > 0 ? round(($platformFeeAmount / $afterDiscount) * 100, 2) : round(\App\Models\Shared\PricingSetting::commissionRateFraction() * 100, 2);

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
                        'trip_price'                  => $tripPrice,          // 1. سعر الرحلة الواحدة
                        'original_price'              => $rawChildPrice,      // 2. إجمالي المبلغ للطفل قبل التخفيض
                        'price_per_child'             => $rawChildPrice,      // 2. إجمالي المبلغ للطفل
                        'discount_percentage'         => $discountPercent,    // 3. نسبة التخفيض %
                        'discount_amount'             => $discountAmt,        // 4. قيمة التخفيض
                        'total_amount_after_discount' => $afterDiscount,      // 5. السعر بعد التخفيض
                        'platform_commission_rate'    => $platformFeePercent, // 6. نسبة عمولة المنصة %
                        'platform_commission_amount'  => $platformFeeAmount,  // 7. قيمة عمولة المنصة
                        'platform_commission'         => $platformFeeAmount,
                        'driver_net_price'            => $driverNetPrice,     // 8. إجمالي السعر للسائق بعد التخفيض وعمولة المنصة
                        'total_price'                 => $driverNetPrice,     // صافي السائق
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