<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OsrmRoutingService
{
    protected string $baseUrl;

    public function __construct()
    {
        // البورت المتاح والمشغل محلياً عندك بنجاح
        $this->baseUrl = 'http://localhost:5001/route/v1/driving/';
    }

    /**
     * حساب المسار والتواقيت التقديرية بين مجموعة نقاط متتالية مع جلب الرسم الهندسي (Geometry)
     *
     * @param array $coordinates مصفوفة تحتوي على خطوط الطول والعرض بترتيب السير [['lat' => 32.8, 'lng' => 13.1], ...]
     * @return array|null
     */
    public function calculateRoute(array $coordinates): ?array
    {
        if (count($coordinates) < 2) {
            return null;
        }

        try {
            // تحويل الإحداثيات إلى الصيغة التي يفهمها OSRM وهي (lng,lat) مفصولة بفاصلة منقوطة
            // تنبيه: OSRM يطلب الـ Longitude أولاً ثم Latitude
            $formattedCoords = collect($coordinates)->map(function ($point) {
                return $point['lng'] . ',' . $point['lat'];
            })->implode(';');

            // التعديل الجوهري: طلب التفاصيل الهندسية (geometries=geojson) لرسم المسار في الواجهة
            // و overview=full لضمان جودة ودقة المنعطفات في خط السير
            $url = $this->baseUrl . $formattedCoords . '?overview=full&geometries=geojson';

            // تم رفع الـ timeout إلى 10 ثوانٍ لتجنب انقطاع الاتصال عند جلب بيانات المسارات الطويلة
            $response = Http::timeout(10)->get($url);

            if ($response->successful() && $response->json('code') === 'Ok') {
                return $response->json();
            }

            Log::error('OSRM Route Error: Invalid response code from local engine.', [
                'response' => $response->json()
            ]);
            return null;

        } catch (Exception $e) {
            Log::error('OSRM Connection Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * دالة مخصصة لحساب الوقت التقديري بالثواني والمسافة بالأمتار بين نقطتين فقط بسرعة
     */
    public function getDistanceMatrix(array $source, array $destination): array
    {
        $coords = [$source, $destination];
        $result = $this->calculateRoute($coords);

        if ($result && isset($result['routes'][0])) {
            return [
                'distance' => $result['routes'][0]['distance'], // بالأمتار
                'duration' => $result['routes'][0]['duration'], // بالثواني
            ];
        }

        return ['distance' => 0, 'duration' => 0];
    }
}