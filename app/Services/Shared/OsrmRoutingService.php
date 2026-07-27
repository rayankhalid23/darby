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
        // استخدام الرابط المحلي مع إمكانية قراءته من .env مستقبلاً
        $this->baseUrl = config('services.osrm.url', 'http://localhost:5001/route/v1/driving/');
    }

    /**
     * حساب المسار والتواقيت التقديرية بين مجموعة نقاط متتالية
     *
     * @param array $coordinates مصفوفة تحتوي على خطوط الطول والعرض بترتيب السير [['lat' => 32.8, 'lng' => 13.1], ...]
     * @return array|null
     */
    public function calculateRoute(array $coordinates): ?array
    {
        if (count($coordinates) < 2) {
            return null;
        }

        // 1. حماية: التحقق من صحة الإحداثيات وتخطي الاستعلام فوراً إذا كانت الإحداثيات صفرية (0,0) أو مفقودة
        foreach ($coordinates as $point) {
            $lat = (float)($point['lat'] ?? 0);
            $lng = (float)($point['lng'] ?? 0);

            if ($lat == 0.0 || $lng == 0.0) {
                Log::warning('OSRM Route Skipped: تم تخطي طلب المسار لوجود إحداثيات صفرية أو غير مكتملة.');
                return null;
            }
        }

        try {
            // تحويل الإحداثيات إلى صيغة (lng,lat)
            $formattedCoords = collect($coordinates)->map(function ($point) {
                return $point['lng'] . ',' . $point['lat'];
            })->implode(';');

            $baseUrl = rtrim($this->baseUrl, '/') . '/';
            $url = $baseUrl . $formattedCoords . '?overview=full&geometries=geojson';

            // تقليل الـ timeout إلى 3 ثوانٍ حتى لا ينتظر النظام كثيراً في حال توقف السيرفر المحلي
            $response = Http::timeout(3)->get($url);

            if ($response->successful() && $response->json('code') === 'Ok') {
                return $response->json();
            }

            Log::warning('OSRM Route Warning: لم يتم إرجاع مسار صالح من المحرك المحلي.', [
                'response' => $response->json()
            ]);
            return null;

        } catch (Exception $e) {
            // تسجيل warning لتفادي ملء ملف الـ Logs بالأخطاء عند توقف سيرفر OSRM
            Log::warning('OSRM Connection Warning: تعذر الاتصال بخدمة الخرائط المحلية (' . $e->getMessage() . ')');
            return null;
        }
    }

    /**
     * دالة مخصصة لحساب الوقت التقديري بالثواني والمسافة بالأمتار بين نقطتين
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