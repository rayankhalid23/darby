<?php

namespace App\Services\Shared;

use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\PricingSetting;
use Carbon\Carbon;

/**
 * 🔑 المصدر الوحيد لحساب سعر اشتراك الطفل.
 *
 * ⚠️ كان السعر يُستقبل من العميل مباشرة عبر `price_per_child` أو `trip_price`
 * ويُحجز من المحفظة كما هو. والمفتاحان يحملان معنيين مختلفين (إجمالي الاشتراك
 * مقابل سعر الرحلة الواحدة)، فكان إرسال `trip_price` وحده يجعل إجمالي اشتراك
 * الشهر مساوياً لسعر رحلة واحدة. الآن يُحسب السعر على الخادم دائماً من:
 *
 *      المسافة × سعر الكيلومتر (حسب تكييف المركبة) × اتجاهات اليوم × أيام العمل
 *
 * ونفس هذه المعادلة تُستخدم في شاشة البحث عن سائق، فيرى ولي الأمر عند الطلب
 * نفس الرقم الذي رآه عند الاختيار.
 */
class PricingCalculator
{
    /**
     * أقل مسافة محتسبة (كم) — رحلة قصيرة جداً لا تنزل تحت هذا الحد.
     */
    public const MIN_BILLABLE_DISTANCE_KM = 4.0;

    /**
     * نسبة الخصم الجماعي حسب عدد الأطفال في نفس الطلب.
     */
    public function discountPercentForChildrenCount(int $childrenCount, ?PricingSetting $settings = null): float
    {
        $settings ??= PricingSetting::first();

        return match (true) {
            $childrenCount === 1 => (float) ($settings->discount_one_child ?? 0.00),
            $childrenCount === 2 => (float) ($settings->discount_two_children ?? 10.00),
            $childrenCount >= 3  => (float) ($settings->discount_three_plus_children ?? 15.00),
            default              => 0.0,
        };
    }

    /**
     * سعر الكيلومتر المعتمد لمركبة السائق النشطة (مكيفة أو غير مكيفة).
     */
    public function pricePerKmForDriver(?Driver $driver, ?PricingSetting $settings = null): float
    {
        $settings ??= PricingSetting::first();

        $priceKmAc    = (float) ($settings->price_per_km_ac ?? 2.50);
        $priceKmNonAc = (float) ($settings->price_per_km_non_ac ?? 2.00);

        if (!$driver) {
            return $priceKmNonAc;
        }

        $driver->loadMissing('vehicles');
        $vehicle = $driver->vehicles->where('status', 'Active')->first() ?? $driver->vehicles->first();

        return ($vehicle && $vehicle->has_ac) ? $priceKmAc : $priceKmNonAc;
    }

    /**
     * عدد اتجاهات الرحلة في اليوم الواحد: ذهاب وإياب = 2، وإلا 1.
     */
    public function tripsPerDay(?string $direction): int
    {
        $dir = strtolower(trim((string) $direction));

        return in_array($dir, ['both', 'two_way', ''], true) ? 2 : 1;
    }

    /**
     * أيام العمل بين تاريخين مع استثناء الجمعة والسبت.
     */
    public function workingDays(?string $startDate, ?string $endDate): int
    {
        if (!$startDate) {
            return 1;
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end   = Carbon::parse($endDate ?? $startDate)->startOfDay();
        } catch (\Throwable) {
            return 1;
        }

        if ($end->lt($start)) {
            return 1;
        }

        $days = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (!in_array($cursor->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $days++;
            }
            $cursor->addDay();
        }

        return max($days, 1);
    }

    /**
     * التسعير الكامل لطفل واحد داخل طلب اشتراك.
     *
     * @param  float|null  $distanceKm  المسافة المقاسة بين المنزل والمدرسة؛ عند غيابها
     *                                  تُشتق من إحداثيات الطفل ومدرسته المحفوظة.
     * @return array{
     *     distance_km: float, effective_distance_km: float, price_per_km: float,
     *     working_days: int, trips_per_day: int, trip_price: float,
     *     raw_total: float, discount_percent: float, discount_amount: float,
     *     total_after_discount: float, trip_price_after_discount: float,
     *     platform_commission: float, driver_net: float, expected_trips: int
     * }
     */
    public function calculateForChild(
        ?Child $child,
        ?Driver $driver,
        string $subscriptionType,
        ?string $direction,
        ?string $startDate,
        ?string $endDate,
        ?float $distanceKm = null,
        float $discountPercent = 0.0,
        ?PricingSetting $settings = null
    ): array {
        $settings ??= PricingSetting::first();

        $pricePerKm     = $this->pricePerKmForDriver($driver, $settings);
        $commissionRate = PricingSetting::commissionRatePercent();

        $distanceKm ??= $this->resolveChildDistanceKm($child);
        $distanceKm  = max(0.0, (float) $distanceKm);

        $effectiveDistance = max($distanceKm, self::MIN_BILLABLE_DISTANCE_KM);
        $tripsPerDay       = $this->tripsPerDay($direction);

        $workingDays = strtolower(trim($subscriptionType)) === 'single_day'
            ? 1
            : $this->workingDays($startDate, $endDate);

        // ⚠️ «الرحلة» في هذا النظام = اتجاه واحد (ذهاب أو إياب) لا يوم كامل:
        // expected_trips = أيام العمل × اتجاهات اليوم، ومحرّك التسوية يصرف حصة
        // لكل اتجاه على حدة. لذلك trip_price هو سعر الاتجاه الواحد، وسعر اليوم
        // هو daily_price. الخلط بينهما يضاعف كل سعر معروض في الاشتراكات ذات
        // الاتجاهين.
        $tripPrice  = round($effectiveDistance * $pricePerKm, 2); // سعر الاتجاه الواحد
        $dailyPrice = round($tripPrice * $tripsPerDay, 2);        // سعر اليوم الكامل
        $rawTotal   = round($dailyPrice * $workingDays, 2);

        $discountAmount     = round(($rawTotal * $discountPercent) / 100, 2);
        $totalAfterDiscount = max(0.0, round($rawTotal - $discountAmount, 2));

        $commission = round(($totalAfterDiscount * $commissionRate) / 100, 2);
        $driverNet  = max(0.0, round($totalAfterDiscount - $commission, 2));

        return [
            'distance_km'               => round($distanceKm, 2),
            'effective_distance_km'     => round($effectiveDistance, 2),
            'price_per_km'              => $pricePerKm,
            'working_days'              => $workingDays,
            'trips_per_day'             => $tripsPerDay,
            'trip_price'                => $tripPrice,
            'daily_price'               => $dailyPrice,
            'raw_total'                 => $rawTotal,
            'discount_percent'          => $discountPercent,
            'discount_amount'           => $discountAmount,
            'total_after_discount'      => $totalAfterDiscount,
            'trip_price_after_discount' => max(0.0, round($tripPrice * (1 - ($discountPercent / 100)), 2)),
            'platform_commission'       => $commission,
            'driver_net'                => $driverNet,
            'expected_trips'            => max(1, $workingDays * $tripsPerDay),
        ];
    }

    /**
     * المسافة بين منزل الطفل ومدرسته بخط مستقيم (Haversine) كقيمة احتياطية
     * عندما لا يرسل العميل مسافة مقاسة على الطريق.
     */
    public function resolveChildDistanceKm(?Child $child): float
    {
        if (!$child) {
            return 0.0;
        }

        $child->loadMissing(['address', 'school']);

        $lat1 = $child->address?->lat;
        $lng1 = $child->address?->lng;
        $lat2 = $child->school?->lat;
        $lng2 = $child->school?->lng;

        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) {
            return 0.0;
        }

        return $this->haversineKm((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2);
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }
}
