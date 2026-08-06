<?php

namespace App\Services\Trip;

use App\Models\Shared\TripStop;
use App\Support\GeoEstimator;
use Exception;

class GeofenceViolationException extends Exception
{
    protected string $errorCode;

    public function __construct(string $message, string $errorCode = 'OUT_OF_RANGE', int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}

/**
 * يفرض قيود الـ GPS على التأكيد اليدوي (بدون QR) فقط:
 * ≤100م للمنازل، ≤200م للمدارس — لمنع التأكيد الوهمي عن بُعد.
 * التأكيد عبر QR يتجاوز هذا الفحص بالكامل (سرعة مقابل ثقة الكود).
 */
class GeofenceService
{
    public const HOME_RADIUS_METERS   = 100;
    public const SCHOOL_RADIUS_METERS = 200;

    public function isWithinRadius(float $lat1, float $lng1, float $lat2, float $lng2, float $maxMeters): bool
    {
        $distanceMeters = GeoEstimator::haversineKm($lat1, $lng1, $lat2, $lng2) * 1000;
        return $distanceMeters <= $maxMeters;
    }

    /**
     * @throws GeofenceViolationException إذا كان موقع السائق خارج النطاق المسموح لهذه المحطة
     */
    public function assertWithinRadius(float $driverLat, float $driverLng, TripStop $stop): void
    {
        $maxMeters = $stop->stop_type === TripStop::TYPE_SCHOOL
            ? self::SCHOOL_RADIUS_METERS
            : self::HOME_RADIUS_METERS;

        $distanceMeters = GeoEstimator::haversineKm($driverLat, $driverLng, (float) $stop->lat, (float) $stop->lng) * 1000;

        if ($distanceMeters > $maxMeters) {
            throw new GeofenceViolationException(
                sprintf(
                    'أنت بعيد عن موقع المحطة (%.0f م)، الحد المسموح %d م. يرجى الاقتراب أو استخدام مسح QR.',
                    $distanceMeters,
                    $maxMeters
                ),
                'OUT_OF_RANGE',
                422
            );
        }
    }
}
