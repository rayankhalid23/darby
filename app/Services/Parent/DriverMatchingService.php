<?php

namespace App\Services\Parent;

use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\Zone;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DriverMatchingService
{
    private const PRICE_PER_KM_AC    = 2.00;
    private const PRICE_PER_KM_NO_AC = 1.50;

   

    private function resolveChildren(array $childIds, int $parentId): Collection
    {
        $query = Child::with(['school.zone.subMunicipality', 'address', 'logistics'])
            ->where('parent_id', $parentId);

        if (!empty($childIds)) {
            $query->whereIn('id', $childIds);
        }

        return $query->get();
    }

    public function matchDrivers(array $filters, int $parentId): LengthAwarePaginator
{
    // 1. استرجاع بيانات الأطفال المحددين بناءً على child_ids الإلزامية
    $children = $this->resolveChildren($filters['child_ids'] ?? [], $parentId);

    // 2. الاستعلام الأساسي واستثناء السائقين ذوي الوثائق المنتهية
    $query = Driver::query()
        ->select('drivers.*')
        ->whereIn('drivers.status', ['Approved', 'Active'])
        ->whereDoesntHave('documents', function ($q) {
            $q->whereIn('doc_type', ['LICENSE', 'INSURANCE', 'STAMP', 'TECHNICAL_INSPECTION'])
              ->where('status', 'Expired');
        })
        ->with(['user', 'vehicles', 'zones', 'seatSlots']);

    // 3. التحقق من وجود بحث بالاسم أو رقم الهاتف
    $hasSearchQuery = !empty($filters['search_query']);

    if ($hasSearchQuery) {
        $this->applyTextSearch($query, $filters['search_query']);
    }

    if (!empty($filters['driver_gender'])) {
        $query->where('drivers.gender', $filters['driver_gender']);
    }

    if (isset($filters['has_ac']) && $filters['has_ac'] !== null && $filters['has_ac'] !== '') {
        $hasAc = filter_var($filters['has_ac'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($hasAc !== null) {
            $query->whereHas('vehicles', fn($q) => $q->where('has_ac', $hasAc));
        }
    }

    // 4. تطبيق الفلترة الذكية (السعة، المناطق، الجنس) فقط عند عدم وجود بحث نصي
    if ($children->isNotEmpty() && !$hasSearchQuery) {
        $this->applyChildrenSmartFilters($query, $children);
    }

    // 5. الترتيب والتصفح
    $drivers = $query
        ->orderByDesc('rating_avg')
        ->orderByDesc('completed_trips_count')
        ->paginate(15);

    // 6. حساب مسافات الأطفال والتسعير الفعلي لكل سائق (سواء بحث عادي أو بالنص)
    if ($children->isNotEmpty()) {
        // حل مشكلة N+1: حساب مسافة كل طفل مرة واحدة فقط قبل التكرار
        $childrenDistances = [];
        foreach ($children as $child) {
            if ($child->address?->lat && $child->school?->lat) {
                $childrenDistances[$child->id] = $this->getRouteDistanceInKm(
                    $child->address->lat,
                    $child->address->lng,
                    $child->school->lat,
                    $child->school->lng
                );
            } else {
                $childrenDistances[$child->id] = 0.0;
            }
        }

        $drivers->getCollection()->transform(function (Driver $driver) use ($children, $childrenDistances) {
            $priceDetails = $this->calculatePricingForDriver($driver, $children, $childrenDistances);
            $driver->estimated_total_price = $priceDetails['total'];
            $driver->pricing_breakdown     = $priceDetails['breakdown'];
            $driver->children_context      = $children;
            return $driver;
        });
    } else {
        $drivers->getCollection()->transform(function (Driver $driver) {
            $driver->estimated_total_price = 0.0;
            $driver->pricing_breakdown     = [];
            $driver->children_context      = collect();
            return $driver;
        });
    }

    return $drivers;
}

    private function applyTextSearch($query, string $keyword): void
    {
        $keyword = trim($keyword);
        $normalizedKeyword = str_replace(['أ', 'إ', 'آ'], 'ا', $keyword);

        $query->whereHas('user', function ($q) use ($keyword, $normalizedKeyword) {
            $q->where(function ($sub) use ($keyword, $normalizedKeyword) {
                $sub->where('users.full_name', 'like', "%{$keyword}%")
                    ->orWhere('users.full_name', 'like', "%{$normalizedKeyword}%")
                    ->orWhere('users.phone_number', 'like', "%{$keyword}%")
                    ->orWhere('users.alternative_phone', 'like', "%{$keyword}%");
            });
        });
    }

    private function applyChildrenSmartFilters($query, Collection $children): void
    {
        $genders = $children->pluck('gender')->unique()->values()->toArray();
        if (count($genders) > 1) {
            $query->where('drivers.accepted_gender', 'both');
        } else {
            $query->whereIn('drivers.accepted_gender', [$genders[0], 'both']);
        }

        $subscriptionTypes = $children->map(fn($c) => optional($c->logistics)->subscription_type)->filter()->unique()->values()->toArray();
        if (!empty($subscriptionTypes)) {
            $query->where(function ($q) use ($subscriptionTypes) {
                $q->where('drivers.subscription_type', 'both');
                foreach ($subscriptionTypes as $type) {
                    $q->orWhere('drivers.subscription_type', $type);
                }
            });
        }

        $this->applySeatsAvailabilityFilter($query, $children);
        $this->applyZoneFilter($query, $children);
    }

    private function applySeatsAvailabilityFilter($query, Collection $children): void
    {
        $slotDemand = [];
        foreach ($children as $child) {
            $timing = strtoupper($child->logistics?->preferred_time_slot ?? 'MORNING');
            $direction = $child->logistics?->trip_direction ?? 'go';
            $slots = \App\Models\Driver\DriverSeatSlot::resolveSlots($timing, $direction);

            foreach ($slots as $slot) {
                $slotDemand[$slot] = ($slotDemand[$slot] ?? 0) + 1;
            }
        }

        foreach ($slotDemand as $slot => $needed) {
            $query->whereHas('seatSlots', function ($q) use ($slot, $needed) {
                $q->where('slot', $slot)
                  ->whereRaw('(total_seats - reserved_seats) >= ?', [$needed]);
            });
        }
    }

    private function applyZoneFilter($query, Collection $children): void
    {
        $zoneIds = $children->map(fn($c) => optional($c->school)->zone_id)->filter()->unique()->values()->toArray();
        if (empty($zoneIds)) {
            return;
        }

        $query->whereHas('zones', function ($q) use ($zoneIds) {
            $q->whereIn('zones.id', $zoneIds);
        });
    }
   

    private function calculatePricingForDriver(Driver $driver, Collection $children, array $childrenDistances = []): array
    {
        // 1. تحديد تكييف المركبة النشطة للسائق
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
            
            // تنظيف ومطابقة اتجاه الرحلة بحزم (go, back, both)
            $tripDir = strtolower(trim($logistics?->trip_direction ?? 'go'));
    
            $childEntry = [
                'child_id'          => $child->id,
                'child_name'        => $child->full_name ?? '',
                'gender'            => $child->gender,
                'subscription_type' => $subscriptionType,
                'trip_direction'    => $tripDir,
            ];
    
            // 2. التحقق من اكتمال إحداثيات منزل ومدرسة الطفل
            if (!$child->address || !$child->school || !$child->address->lat || !$child->school->lat) {
                $childEntry['error'] = 'بيانات الموقع أو إحداثيات الإقامة/المدرسة ناقصة';
                $childEntry['child_total_price'] = 0.0;
                $breakdown[] = $childEntry;
                continue;
            }
    
            // 3. قراءة المسافة الخاصة بهذا الطفل بالذات
            $distanceKm = $childrenDistances[$child->id] ?? $this->getRouteDistanceInKm(
                $child->address->lat,
                $child->address->lng,
                $child->school->lat,
                $child->school->lng
            );
    
            // 4. تطبيق الحد الأدنى للمسافة (4 كم) لضمان جدوى المشوار
            $effectiveDistance = max($distanceKm, 4.0);
    
            // 5. معامل الاتجاه (ذهاب وعودة = 2، اتجاه واحد = 1)
            $tripMultiplier = ($tripDir === 'both') ? 2 : 1;
    
            // --- الحسابات المالية الدقيقة ---
            // أ) سعر المشوار الفردي (التوصيلة الواحدة فقط)
            $singleLegPrice = round($effectiveDistance * $pricePerKm, 2);
            
            // ب) السعر اليومي للطفل (سعر المشوار الفردي × عدد الاتجاهات)
            $dailyPrice = round($singleLegPrice * $tripMultiplier, 2);
    
            // ج) عدد أيام العمل الفعلية التي حددها ولي الأمر
            $workingDays = ($subscriptionType === 'single_day') ? 1 : $this->calculateWorkingDays($startDate, $endDate);
    
            // د) الإجمالي النهائي للطفل خلال الفترة المحددة
            $childTotalPrice = round($dailyPrice * $workingDays, 2);
            $totalPrice += $childTotalPrice;
    
            // 6. تجهيز تفاصيل الفاتورة للفرونت إند
            $childEntry['distance_km']           = round($distanceKm, 2);
            $childEntry['effective_distance_km'] = round($effectiveDistance, 2);
            $childEntry['price_per_km']          = $pricePerKm;
            $childEntry['single_leg_price']      = $singleLegPrice;   // سعر الرحلة الواحدة
            $childEntry['daily_price']           = $dailyPrice;        // سعر اليوم الكامل للطفل
            $childEntry['working_days']          = $workingDays;       // عدد الأيام المطلوبة
            $childEntry['child_total_price']     = $childTotalPrice;   // إجمالي هذا الطفل
    
            $breakdown[] = $childEntry;
        }
    
        return [
            'total'     => round($totalPrice, 2), // الإجمالي الكلي لجميع الأطفال
            'breakdown' => $breakdown
        ];
    }

    /**
     * جلب إحداثيات ومسار الطريق (GeoJSON Geometry) لرسم المنحنيات على الخريطة
     */
    public function getRouteGeometry(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): array
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return [];
        }

        try {
            // overview=full & geometries=geojson لإرجاع جميع نقاط المنحنيات والشوارع
            $osrmUrl = "http://localhost:5000/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}?overview=full&geometries=geojson";

            $response = \Illuminate\Support\Facades\Http::timeout(3)->get($osrmUrl);

            if ($response->successful()) {
                $geometry = $response->json('routes.0.geometry');
                if ($geometry) {
                    return $geometry; // يحتوي على type: LineString و coordinates
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OSRM geometry call failed: {$e->getMessage()}");
        }

        // نظام احتياطي: إرجاع خط مستقيم بين النقطتين لتفادي توقف الخريطة في الفرونت إند
        return [
            'type' => 'LineString',
            'coordinates' => [
                [$lon1, $lat1],
                [$lon2, $lat2]
            ]
        ];
    }


private function calculateWorkingDays($startDate, $endDate): int
{
    if (empty($startDate) || empty($endDate)) return 1;
    try {
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->startOfDay();
        if ($end->lessThan($start)) return 1;

        $days = 0;
        $current = $start->copy();
        while ($current->lessThanOrEqualTo($end)) {
            if (!$current->isFriday() && !$current->isSaturday()) $days++;
            $current->addDay();
        }
        return max(1, $days);
    } catch (\Exception $e) {
        return 1;
    }
}


    private function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return 0.0;
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * حساب المسافة الفعلية بالسيارة (بالكيلومتر) عبر OSRM
     * مع وجود نظام حماية احتياطي (Fallback) بمعادلة Haversine في حال تعثر السيرفر.
     */
    private function getRouteDistanceInKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): float
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return 0.0;
        }

        try {
            // ملاحظة مهمة: OSRM يقبل الإحداثيات بصيغة (Longitude, Latitude)
            $osrmUrl = "http://localhost:5000/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}?overview=false";

            $response = \Illuminate\Support\Facades\Http::timeout(2)->get($osrmUrl);

            if ($response->successful()) {
                $distanceInMeters = $response->json('routes.0.distance');
                if ($distanceInMeters !== null) {
                    return round($distanceInMeters / 1000, 2);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("OSRM distance call failed: {$e->getMessage()}. Using Haversine fallback.");
        }

        // النظام الاحتياطي: حساب الهيفرسين مضروب في معامل انحناء الشوارع 1.3
        return round($this->calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) * 1.3, 2);
    }
}