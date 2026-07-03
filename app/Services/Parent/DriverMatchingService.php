<?php

namespace App\Services\Parent;

use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\Zone;
use App\Models\Shared\SubMunicipality;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * DriverMatchingService
 * ─────────────────────────────────────────────
 * خدمة الفلترة والمطابقة الذكية للسائقين
 * تعمل بثلاثة أوضاع:
 *   1) بحث نصي (اسم / هاتف)
 *   2) فلترة ذكية من بيانات الأطفال + تسعير تلقائي
 *   3) مزيج من الاثنين
 */
class DriverMatchingService
{
    // ──────────────────── أسعار الكيلومتر ────────────────────
    private const PRICE_PER_KM_AC     = 2.00;   // سيارة مكيفة
    private const PRICE_PER_KM_NO_AC  = 1.50;   // سيارة غير مكيفة

    // ─────────────────────────────────────────────────────────
    // نقطة الدخول الرئيسية
    // ─────────────────────────────────────────────────────────

    /**
     * matchDrivers
     * ─────────────────────────────────────────
     * @param  array  $filters  الفلاتر المُرسلة من الـ Request
     * @param  int    $parentId معرّف ولي الأمر الحالي
     * @return LengthAwarePaginator
     */
    public function matchDrivers(array $filters, int $parentId): LengthAwarePaginator
    {
        // ① جلب الأطفال المعنيين (المحددون أو كل أطفال ولي الأمر)
        $children = $this->resolveChildren($filters['child_ids'] ?? [], $parentId);

        // ② بناء الاستعلام الأساسي للسائقين المعتمدين
        $query = Driver::query()
            ->whereIn('drivers.status', ['Approved', 'Active'])
            ->with(['user', 'vehicles', 'zones']);

        // ─── تطبيق الفلاتر ───

        // أ) البحث النصي بالاسم أو الهاتف (لو موجود يُطبَّق مع باقي الفلاتر)
        if (!empty($filters['search_query'])) {
            $this->applyTextSearch($query, $filters['search_query']);
        }

        // ب) فلترة جنس السائق
        if (!empty($filters['driver_gender'])) {
            $query->where('drivers.gender', $filters['driver_gender']);
        }

        // ج) فلترة وجود مكيف في المركبة
        if (isset($filters['has_ac']) && $filters['has_ac'] !== null && $filters['has_ac'] !== '') {
            $hasAc = filter_var($filters['has_ac'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($hasAc !== null) {
                $query->whereHas('vehicles', fn($q) => $q->where('has_ac', $hasAc));
            }
        }

        // د) الفلترة الذكية المشتقة من بيانات الأطفال
        if ($children->isNotEmpty()) {
            $this->applyChildrenSmartFilters($query, $children);
        }

        // ─── ترتيب وتصفح النتائج ───
        $drivers = $query
            ->orderByDesc('rating_avg')
            ->orderByDesc('completed_trips_count')
            ->paginate(15);

        // ─── حساب التسعير التقديري لكل سائق ───
        if ($children->isNotEmpty()) {
            $drivers->getCollection()->transform(function (Driver $driver) use ($children) {
                $priceDetails = $this->calculatePricingForDriver($driver, $children);
                $driver->estimated_total_price    = $priceDetails['total'];
                $driver->pricing_breakdown        = $priceDetails['breakdown'];
                $driver->children_context         = $children;
                return $driver;
            });
        }

        return $drivers;
    }

    // ─────────────────────────────────────────────────────────
    // تحديد الأطفال المعنيين
    // ─────────────────────────────────────────────────────────

    /**
     * إذا حدّد ولي الأمر child_ids → جلب هؤلاء فقط
     * إذا لم يحدد → جلب كل أطفاله
     */
    private function resolveChildren(array $childIds, int $parentId): Collection
    {
        $query = Child::with(['school.zone.subMunicipality', 'address', 'logistics'])
            ->where('parent_id', $parentId);

        if (!empty($childIds)) {
            $query->whereIn('id', $childIds);
        }

        return $query->get();
    }

    // ─────────────────────────────────────────────────────────
    // البحث النصي
    // ─────────────────────────────────────────────────────────

    /**
     * فلترة السائقين بالاسم أو رقم الهاتف
     * تدعم: البحث الجزئي + تطبيع الألف (أ / إ / آ → ا)
     */
    private function applyTextSearch($query, string $keyword): void
    {
        $keyword           = trim($keyword);
        $normalizedKeyword = str_replace(['أ', 'إ', 'آ'], 'ا', $keyword);

        $query->whereHas('user', function ($q) use ($keyword, $normalizedKeyword) {
            $q->where(function ($sub) use ($keyword, $normalizedKeyword) {
                // البحث بالاسم الكامل (مع تطبيع الألف)
                $sub->where('users.full_name', 'like', "%{$keyword}%")
                    ->orWhere('users.full_name', 'like', "%{$normalizedKeyword}%")
                    ->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(users.full_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') LIKE ?",
                        ["%{$normalizedKeyword}%"]
                    )
                    // البحث برقم الهاتف الأساسي
                    ->orWhere('users.phone_number', 'like', "%{$keyword}%")
                    // البحث برقم الهاتف الاحتياطي
                    ->orWhere('users.alternative_phone', 'like', "%{$keyword}%");
            });
        });
    }

    // ─────────────────────────────────────────────────────────
    // الفلترة الذكية بناءً على بيانات الأطفال
    // ─────────────────────────────────────────────────────────

    /**
     * تطبيق الفلاتر المشتقة من الأطفال:
     *   - المنطقة الجغرافية (مع fallback للبلدية)
     *   - نوع الاشتراك (daily / monthly)
     *   - جنس الأطفال (accepted_gender للسائق)
     */
    private function applyChildrenSmartFilters($query, Collection $children): void
    {
        // ── فلترة المنطقة بذكاء ──
        $this->applyZoneFilter($query, $children);

        // ── فلترة جنس القبول للسائق ──
        $genders = $children->pluck('gender')->unique()->values()->toArray();
        if (count($genders) > 1) {
            // أطفال من الجنسين → السائق يجب أن يقبل 'both'
            $query->where('drivers.accepted_gender', 'both');
        } else {
            // جنس واحد → السائق يقبل هذا الجنس أو 'both'
            $query->whereIn('drivers.accepted_gender', [$genders[0], 'both']);
        }

        // ── فلترة نوع الاشتراك ──
        $subscriptionTypes = $children
            ->map(fn($c) => optional($c->logistics)->subscription_type)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($subscriptionTypes)) {
            $query->where(function ($q) use ($subscriptionTypes) {
                // السائق يقبل 'both' أو أي نوع من أنواع الأطفال
                $q->where('drivers.subscription_type', 'both');
                foreach ($subscriptionTypes as $type) {
                    $q->orWhere('drivers.subscription_type', $type);
                }
            });
        }
    }

    /**
     * فلترة المنطقة الجغرافية بمرحلتين:
     *
     * المرحلة 1: جلب السائقين الذين حددوا نفس منطقة مدرسة الطفل (zone_id)
     * المرحلة 2 (fallback): إذا لم يوجد → جلب السائقين في نفس البلدية (sub_municipality_id)
     */
    private function applyZoneFilter($query, Collection $children): void
    {
        // جمع كل zone_ids من مدارس الأطفال
        $zoneIds = $children
            ->map(fn($c) => optional($c->school)->zone_id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($zoneIds)) {
            return; // لا توجد مناطق محددة → لا تُفلتر
        }

        // ── المرحلة 1: فلترة بالـ zone_id مباشرة ──
        $driversInZone = (clone $query)->whereHas('zones', function ($q) use ($zoneIds) {
            $q->whereIn('zones.id', $zoneIds);
        })->count();

        if ($driversInZone > 0) {
            // وُجد سائقون في نفس المنطقة ✅
            $query->whereHas('zones', function ($q) use ($zoneIds) {
                $q->whereIn('zones.id', $zoneIds);
            });
            return;
        }

        // ── المرحلة 2 (fallback): فلترة بالبلدية ──
        // جلب sub_municipality_ids من الـ zones
        $subMunicipalityIds = Zone::whereIn('id', $zoneIds)
            ->pluck('sub_municipality_id')
            ->filter()
            ->unique()
            ->toArray();

        if (!empty($subMunicipalityIds)) {
            // جلب كل zone_ids في نفس البلدية
            $siblingZoneIds = Zone::whereIn('sub_municipality_id', $subMunicipalityIds)
                ->pluck('id')
                ->toArray();

            if (!empty($siblingZoneIds)) {
                $query->whereHas('zones', function ($q) use ($siblingZoneIds) {
                    $q->whereIn('zones.id', $siblingZoneIds);
                });
            }
        }
        // إذا لم يوجد حتى في البلدية → لا قيد جغرافي (يُعيد كل السائقين المعتمدين)
    }

    // ─────────────────────────────────────────────────────────
    // نظام التسعير الذكي
    // ─────────────────────────────────────────────────────────

    /**
     * حساب السعر الإجمالي التقديري لسائق معين لجميع الأطفال
     *
     * المعادلة لكل طفل:
     *   المسافة × سعر_الكيلومتر × عدد_أيام_العمل
     *
     * سعر_الكيلومتر:
     *   has_ac = true  → 2.00 د.ل
     *   has_ac = false → 1.50 د.ل
     *
     * عدد_أيام_العمل:
     *   subscription_type = daily   → 1 يوم
     *   subscription_type = monthly → الأيام بين start_date و end_date (استثناء الجمعة والسبت)
     */
    private function calculatePricingForDriver(Driver $driver, Collection $children): array
    {
        // تحديد سعر الكيلومتر حسب نوع السيارة
        $activeVehicle = $driver->vehicles->where('status', 'Active')->first()
                      ?? $driver->vehicles->first();

        $hasAc      = $activeVehicle ? (bool) $activeVehicle->has_ac : false;
        $pricePerKm = $hasAc ? self::PRICE_PER_KM_AC : self::PRICE_PER_KM_NO_AC;

        $totalPrice = 0.0;
        $breakdown  = [];

        foreach ($children as $child) {
            $childEntry = [
                'child_id'   => $child->id,
                'child_name' => $child->full_name,
            ];

            // تحقق من وجود عنوان المنزل والمدرسة
            if (!$child->address || !$child->school) {
                $childEntry['error']       = 'بيانات الموقع ناقصة (العنوان أو المدرسة)';
                $childEntry['child_price'] = 0;
                $breakdown[]               = $childEntry;
                continue;
            }

            // حساب المسافة بين المنزل والمدرسة
            $distanceKm = $this->calculateHaversineDistance(
                $child->address->lat, $child->address->lng,
                $child->school->lat,  $child->school->lng
            );

            // تحديد عدد أيام العمل
            $logistics        = $child->logistics;
            $subscriptionType = $logistics ? $logistics->subscription_type : 'monthly';

            if ($subscriptionType === 'daily') {
                $workingDays = 1;
            } else {
                // monthly: حساب أيام العمل الفعلية (استثناء الجمعة والسبت)
                $workingDays = $this->calculateWorkingDays(
                    $logistics?->start_date,
                    $logistics?->end_date
                );
            }

            // تحديد معامل المسافة بناءً على عمود trip_direction من ملف image_94b148.png
$tripDirection = $logistics?->trip_direction ?? 'go'; 
$tripMultiplier = ($tripDirection === 'both') ? 2 : 1;

// حساب السعر النهائي مع مضاعفة المسافة إذا كان نوع الرحلة 'both'
$childPrice  = round($distanceKm * $pricePerKm * $workingDays * $tripMultiplier, 2);
            $totalPrice += $childPrice;

            $childEntry['school_name']        = $child->school->name ?? '';
            $childEntry['home_lat']           = $child->address->lat;
            $childEntry['home_lng']           = $child->address->lng;
            $childEntry['school_lat']         = $child->school->lat;
            $childEntry['school_lng']         = $child->school->lng;
            $childEntry['distance_km']        = round($distanceKm, 2);
            $childEntry['price_per_km']       = $pricePerKm;
            $childEntry['subscription_type']  = $subscriptionType;
            $childEntry['working_days']       = $workingDays;
            $childEntry['child_price']        = $childPrice;
            $breakdown[] = $childEntry;
        }

        return [
            'total'      => round($totalPrice, 2),
            'has_ac'     => $hasAc,
            'price_per_km' => $pricePerKm,
            'breakdown'  => $breakdown,
        ];
    }

    /**
     * حساب أيام العمل الفعلية (استثناء الجمعة والسبت) بين تاريخين
     */
    private function calculateWorkingDays(?string $startDate, ?string $endDate): int
    {
        if (!$startDate || !$endDate) {
            return 22; // افتراضي: شهر عمل متوسط
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end   = Carbon::parse($endDate)->startOfDay();

            if ($end->lessThanOrEqualTo($start)) {
                return 1;
            }

            $days = 0;
            $current = $start->copy();
            while ($current->lessThanOrEqualTo($end)) {
                // استثناء الجمعة (5) والسبت (6)
                if (!$current->isFriday() && !$current->isSaturday()) {
                    $days++;
                }
                $current->addDay();
            }

            return max(1, $days);
        } catch (\Exception $e) {
            return 22;
        }
    }

    // ─────────────────────────────────────────────────────────
    // الحسابات الرياضية
    // ─────────────────────────────────────────────────────────

    /**
     * معادلة Haversine لحساب المسافة بالكيلومتر بين نقطتين جغرافيتين
     */
    private function calculateHaversineDistance(
        ?float $lat1, ?float $lon1,
        ?float $lat2, ?float $lon2
    ): float {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return 0.0;
        }

        $earthRadius = 6371; // نصف قطر الأرض بالكيلومتر
        $latDelta    = deg2rad($lat2 - $lat1);
        $lonDelta    = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}