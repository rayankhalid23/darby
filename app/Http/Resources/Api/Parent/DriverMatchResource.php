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

        // ── حساب المقاعد المتاحة من seatSlots الفعلية ──
        // ✅ إصلاح: active_subs_count كان دائماً null (لم يُحمَّل في الاستعلام)
        // الصحيح: أقل عدد مقاعد متاحة عبر جميع فترات السائق (أكثر تحفظاً وأدق للعرض)
        $seatSlots      = $this->seatSlots ?? collect();
        $availableSeats = $seatSlots->isNotEmpty()
            ? (int) $seatSlots->min(fn($s) => max(0, $s->total_seats - $s->reserved_seats))
            : max(0, $activeVehicle?->capacity_manual ?? 0);

        // ── بيانات التسعير (محسوبة مسبقاً في الـ Service) ──
        $pricingBreakdown = $this->pricing_breakdown ?? [];
        $totalPrice       = $this->estimated_total_price ?? 0;

        // ── بيانات الأطفال المرفقة ──
        $children = $this->children_context ?? collect();

        // ✅ إصلاح: has_ac مباشرة من المركبة وليس عبر مقارنة price_per_km
        $vehicleHasAc = $activeVehicle ? (bool) $activeVehicle->has_ac : null;

        return [
            // ── المعرفات الصريحة (لمنع الاختلاط في الفرونت إند) ──
            'id'               => (int) $this->id,
            'driver_id'        => (int) $this->id,
            'user_id'          => $user?->id ? (int) $user->id : null,

            // ── بيانات السائق الأساسية ──
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

            // ── المقاعد (محسوبة من seatSlots الفعلية) ──
            'available_seats' => $availableSeats,

            // ── تفاصيل المقاعد لكل فترة ──
            'seat_slots' => $seatSlots->map(fn($s) => [
                'slot'            => $s->slot,
                'total_seats'     => $s->total_seats,
                'reserved_seats'  => $s->reserved_seats,
                'available_seats' => max(0, $s->total_seats - $s->reserved_seats),
            ])->values(),

            // ── المناطق التي يغطيها السائق ──
            'working_zones' => $this->zones->map(fn($z) => [
                'id'   => $z->id,
                'name' => $z->name,
            ])->values(),

            // ── السعر الإجمالي التقديري ──
            'pricing' => [
                'total_price'     => number_format($totalPrice, 2) . ' د.ل',
                'total_price_raw' => $totalPrice,
                'has_ac'          => $vehicleHasAc, // ✅ مباشر من المركبة
                'price_per_km'    => $vehicleHasAc === true ? 2.00 : ($vehicleHasAc === false ? 1.50 : null),
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