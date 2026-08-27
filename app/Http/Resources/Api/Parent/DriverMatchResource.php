<?php

namespace App\Http\Resources\Api\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DriverMatchResource extends JsonResource
{
    private function calculatePricingForDriver(Driver $driver, Collection $children, array $childrenDistances = []): array
    {
        $activeVehicle = $driver->vehicles->where('status', 'Active')->first() ?? $driver->vehicles->first();
        $hasAc = $activeVehicle ? (bool) $activeVehicle->has_ac : false;
        $pricePerKm = $hasAc ? self::PRICE_PER_KM_AC : self::PRICE_PER_KM_NO_AC;
    
        $totalPrice = 0.0;
        $breakdown = [];
    
        foreach ($children as $child) {
            $logistics = $child->logistics;
            $subscriptionType = (strtolower(trim($logistics?->subscription_type ?? 'multi_day')) === 'single_day') ? 'single_day' : 'multi_day';
            $startDate = $logistics?->start_date ?? null;
            $endDate = $logistics?->end_date ?? null;
            $tripDir = strtolower(trim($logistics?->trip_direction ?? 'go'));
    
            // تجهيز المفاتيح لتوافق DriverMatchResource 100%
            $childEntry = [
                'child_id'            => $child->id,
                'child_name'          => $child->full_name ?? '',
                'gender'              => $child->gender,
                'school_stage'        => $child->school_stage ?? null,
                'school_name'         => $child->school?->name ?? null,
                'subscription_type'   => $subscriptionType,
                'preferred_time_slot' => $logistics?->preferred_time_slot ?? null,
                'trip_direction'      => $tripDir,
                'start_date'          => $startDate,
                'end_date'            => $endDate,
            ];
    
            if (!$child->address || !$child->school || !$child->address->lat || !$child->school->lat) {
                $childEntry['error'] = 'بيانات الموقع أو إحداثيات الإقامة/المدرسة ناقصة';
                $childEntry['trip_price'] = 0.0;
                $childEntry['child_total_price'] = 0.0;
                $breakdown[] = $childEntry;
                continue;
            }
    
            $distanceKm = $childrenDistances[$child->id] ?? $this->getRouteDistanceInKm(
                $child->address->lat,
                $child->address->lng,
                $child->school->lat,
                $child->school->lng
            );
    
            $effectiveDistance = max($distanceKm, 4.0);
            $tripMultiplier = ($tripDir === 'both') ? 2 : 1;
    
            $singleLegPrice = round($effectiveDistance * $pricePerKm, 2);
            $dailyPrice = round($singleLegPrice * $tripMultiplier, 2);
            $workingDays = ($subscriptionType === 'single_day') ? 1 : $this->calculateWorkingDays($startDate, $endDate);
            $childTotalPrice = round($dailyPrice * $workingDays, 2);
            $totalPrice += $childTotalPrice;
    
            // مطابقة الحقول مع الـ Resource
            $childEntry['distance_km']       = round($distanceKm, 2);
            $childEntry['working_days']      = $workingDays;
            $childEntry['trip_multiplier']   = $tripMultiplier;
            $childEntry['trip_price']        = $singleLegPrice; // 🔥 نفس المسمى المطلوب في Resource
            $childEntry['child_total_price'] = $childTotalPrice;
    
            $breakdown[] = $childEntry;
        }
    
        return [
            'total'     => round($totalPrice, 2),
            'breakdown' => $breakdown
        ];
    }
}