<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_one_child',
        'discount_two_children',
        'discount_three_plus_children',
        'platform_commission_rate',
        'location_change_fee',
        'location_change_fee_under_2km',
        'location_change_fee_2_to_6km',
        'location_change_fee_6_to_10km',
        'price_per_km_ac',
        'price_per_km_non_ac',
    ];

    public const DEFAULT_LOCATION_CHANGE_FEE = 5.00;

    /**
     * شرائح رسوم تغيير العنوان حسب المسافة بين الموقع الجديد وموقع الطفل الحالي (المنزل).
     *   under_2km : أقل من 2 كم
     *   2_to_6km  : من 2 كم حتى 6 كم (شاملة الطرفين)
     *   6_to_10km : أكثر من 6 كم حتى 10 كم
     * ما زاد عن 10 كم لا يملك شريحة سعرية ويُرفض الطلب.
     */
    public const TIER_UNDER_2KM = 'under_2km';
    public const TIER_2_TO_6KM  = '2_to_6km';
    public const TIER_6_TO_10KM = '6_to_10km';

    public const MAX_LOCATION_CHANGE_DISTANCE_KM = 10.0;

    public const DEFAULT_TIER_FEES = [
        self::TIER_UNDER_2KM => 5.00,
        self::TIER_2_TO_6KM  => 10.00,
        self::TIER_6_TO_10KM => 15.00,
    ];

    public const TIER_COLUMNS = [
        self::TIER_UNDER_2KM => 'location_change_fee_under_2km',
        self::TIER_2_TO_6KM  => 'location_change_fee_2_to_6km',
        self::TIER_6_TO_10KM => 'location_change_fee_6_to_10km',
    ];

    public const TIER_LABELS = [
        self::TIER_UNDER_2KM => 'أقل من 2 كم',
        self::TIER_2_TO_6KM  => 'من 2 كم إلى 6 كم',
        self::TIER_6_TO_10KM => 'أكثر من 6 كم إلى 10 كم',
    ];

    /**
     * النسبة الافتراضية للعمولة (٪) إذا لم تُضبط الإعدادات بعد.
     */
    public const DEFAULT_COMMISSION_RATE = 8.00;

    /**
     * 🔑 المصدر الوحيد لنسبة عمولة المنصة كنسبة مئوية (مثال: 8.00).
     *
     * كانت النسبة مكررة يدوياً في أكثر من خدمة (12٪ في السجل المالي و8٪ في بقية
     * النظام) فتختلف الأرقام بين مسار وآخر ولا تتطابق التقارير أبداً.
     * أي حساب للعمولة يجب أن يمر من هنا.
     */
    public static function commissionRatePercent(): float
    {
        return (float) (static::query()->value('platform_commission_rate') ?? self::DEFAULT_COMMISSION_RATE);
    }

    /**
     * نفس النسبة ككسر عشري جاهز للضرب (مثال: 0.08).
     */
    public static function commissionRateFraction(): float
    {
        return static::commissionRatePercent() / 100;
    }

    /**
     * الرسم الأساسي (الاحتياطي) لتغيير الموقع عندما تتعذّر معرفة المسافة.
     */
    public static function locationChangeFee(): float
    {
        return (float) (static::query()->value('location_change_fee') ?? self::DEFAULT_LOCATION_CHANGE_FEE);
    }

    /**
     * يحدد شريحة المسافة التي تقع فيها مسافة معينة، أو null إذا تجاوزت الحد الأقصى المسموح.
     */
    public static function resolveFeeTier(float $distanceKm): ?string
    {
        if ($distanceKm < 2.0) {
            return self::TIER_UNDER_2KM;
        }

        if ($distanceKm <= 6.0) {
            return self::TIER_2_TO_6KM;
        }

        if ($distanceKm <= self::MAX_LOCATION_CHANGE_DISTANCE_KM) {
            return self::TIER_6_TO_10KM;
        }

        return null;
    }

    /**
     * رسم الشريحة كما ضبطه الأدمن، مع السقوط للقيمة الافتراضية إذا لم تُضبط بعد.
     */
    public static function feeForTier(string $tier): float
    {
        $column = self::TIER_COLUMNS[$tier] ?? null;
        if (!$column) {
            return self::locationChangeFee();
        }

        $value = static::query()->value($column);

        return $value === null
            ? (float) (self::DEFAULT_TIER_FEES[$tier] ?? self::DEFAULT_LOCATION_CHANGE_FEE)
            : (float) $value;
    }

    /**
     * كل الشرائح المضبوطة حالياً — تُستخدم في شاشات الأدمن وفي توضيح التسعير لولي الأمر.
     */
    public static function locationChangeFeeTiers(): array
    {
        $settings = static::query()->first();

        $tiers = [];
        foreach (self::TIER_COLUMNS as $tier => $column) {
            $value = $settings?->{$column};
            $tiers[] = [
                'tier'  => $tier,
                'label' => self::TIER_LABELS[$tier],
                'fee'   => $value === null ? (float) self::DEFAULT_TIER_FEES[$tier] : (float) $value,
            ];
        }

        return $tiers;
    }
}