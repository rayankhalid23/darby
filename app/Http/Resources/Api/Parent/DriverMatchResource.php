<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DriverMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user          = $this->user;
        $activeVehicle = $this->vehicles->where('status', 'Active')->first()
                      ?? $this->vehicles->first();

        // ── حساب المقاعد المتاحة ──
        $totalCapacity        = $activeVehicle?->capacity_manual ?? 0;
        $activeSubscriptions  = $this->active_subs_count ?? 0;
        $availableSeats       = max(0, $totalCapacity - $activeSubscriptions);

        // ── بيانات التسعير (محسوبة مسبقاً في الـ Service) ──
        $pricingBreakdown = $this->pricing_breakdown ?? [];
        $totalPrice       = $this->estimated_total_price ?? 0;

        // ── بيانات الأطفال المرفقة ──
        $children = $this->children_context ?? collect();

        return [
            // ── بيانات السائق الأساسية ──
            'id'               => $this->id,
            'full_name'        => $user?->full_name ?? 'غير متوفر',
            'phone_number'     => $user?->phone_number ?? 'غير متوفر',
            'alternative_phone'=> $user?->alternative_phone,
            'avatar_url'       => ($user?->avatar_url)
                                    ? asset(Storage::url($user->avatar_url))
                                    : null,
            'gender'           => $this->gender,
            'accepted_gender'  => $this->accepted_gender,
            'shift'            => $this->shift,
            'rating'           => round((float)($this->rating_avg ?? 5.0), 1),
            'completed_trips'  => $this->completed_trips_count ?? 0,
            'status'           => $this->status,

            // ── بيانات المركبة ──
            'vehicle' => $activeVehicle ? [
                'brand'           => $activeVehicle->brand,
                'model'           => $activeVehicle->model,
                'year'            => $activeVehicle->year,
                'color'           => $activeVehicle->color,
                'type'            => $activeVehicle->type,
                'has_ac'          => (bool) $activeVehicle->has_ac,
                'capacity_manual' => $activeVehicle->capacity_manual,
                'plate_number'    => $activeVehicle->plate_number,
            ] : null,

            // ── المقاعد ──
            'available_seats' => $availableSeats,

            // ── المناطق التي يغطيها السائق ──
            'working_zones' => $this->zones->map(fn($z) => [
                'id'   => $z->id,
                'name' => $z->name,
            ])->values(),

            // ── السعر الإجمالي التقديري ──
            'pricing' => [
                'total_price'     => number_format($totalPrice, 2) . ' د.ل',
                'total_price_raw' => $totalPrice,
                'has_ac'          => $this->pricing_breakdown[0]['price_per_km'] ?? null
                                     ? (($this->pricing_breakdown[0]['price_per_km'] ?? 0) == 2.00)
                                     : null,
                'price_per_km'    => $pricingBreakdown[0]['price_per_km'] ?? null,
                'children_count'  => count($pricingBreakdown),
                'breakdown'       => collect($pricingBreakdown)->map(fn($item) => [
                    'child_id'         => $item['child_id'] ?? null,
                    'child_name'       => $item['child_name'] ?? null,
                    'school_name'      => $item['school_name'] ?? null,
                    'distance_km'      => $item['distance_km'] ?? null,
                    'price_per_km'     => $item['price_per_km'] ?? null,
                    'subscription_type'=> $item['subscription_type'] ?? null,
                    'working_days'     => $item['working_days'] ?? null,
                    'child_price'      => isset($item['child_price'])
                                          ? number_format($item['child_price'], 2) . ' د.ل'
                                          : null,
                    'child_price_raw'  => $item['child_price'] ?? 0,
                    'error'            => $item['error'] ?? null,
                ])->values(),
            ],
        ];
    }
}