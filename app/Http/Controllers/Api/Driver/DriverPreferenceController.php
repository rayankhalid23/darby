<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\UpdateDriverPreferencesRequest;
use App\Services\Driver\DriverPreferenceService;
use App\Http\Resources\Api\Driver\DriverPreferenceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Exception;

class DriverPreferenceController extends Controller
{
    protected DriverPreferenceService $preferenceService;

    public function __construct(DriverPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    /**
     * 🔍 1. دالة العرض: استعراض تفضيلات ومناطق السائق الحالية
     */
    public function show(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return response()->json(['status' => false, 'message' => 'ملف التعريف غير موجود.'], 404);
        }

        // شحن العلاقات مسبقاً لمنع مشكلة الـ N+1 Query وتسريع الأداء
        $driver->load('zones.subMunicipality.municipality');
        $preferences = $this->preferenceService->getPreferences($driver);

        return response()->json([
            'status' => true,
            'data'   => new DriverPreferenceResource($preferences)
        ], Response::HTTP_OK);
    }

    /**
     * 🔄 2. دالة التحديث الشامل (الفترة + نوع الاشتراك + مصفوفة المناطق معاً)
     * تلتقط خطأ اختلاف البلدية الفرعية وتمنع التخزين العشوائي
     */
    public function update(UpdateDriverPreferencesRequest $request): JsonResponse
    {
        try {
            $driver = $request->user()->driver;
            if (!$driver) {
                return response()->json(['status' => false, 'message' => 'ملف التعريف غير موجود.'], 404);
            }

            $updatedDriver = $this->preferenceService->updatePreferences($driver, $request->validated());

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث التفضيلات بنجاح.',
                'data'    => new DriverPreferenceResource($updatedDriver)
            ]);

        } catch (Exception $e) {
            \Log::error('Driver Preference Update Error: ' . $e->getMessage(), [
                'driver_id' => $request->user()->driver?->id,
                'trace'     => $e->getTraceAsString()
            ]);
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * ➕ 3. دالة إضافة منطقة منفردة لتفضيلات السائق الحالية
     * تتحقق من أن المنطقة الجديدة تتبع نفس البلدية الصغيرة للمناطق السابقة
     */
    public function addZone(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => ['required', 'integer', 'exists:zones,id']
        ], ['zone_id.exists' => 'المنطقة المحددة غير مسجلة بالنظام.']);

        try {
            $driver = $request->user()->driver;
            if (!$driver) {
                return response()->json(['status' => false, 'message' => 'ملف التعريف غير موجود.'], 404);
            }

            $updatedDriver = $this->preferenceService->addZoneToDriver($driver, $request->zone_id);

            return response()->json([
                'status'  => true,
                'message' => 'تم إضافة المنطقة الجديدة لتفضيلات التغطية الخاصة بك بنجاح.',
                'data'    => new DriverPreferenceResource($updatedDriver)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // 💡 هذا السطر يقوم بتسجيل الخطأ مع تفاصيل المسار والملف والسطر (مهم جداً للمطورين)
            \Log::error('Driver Preference Error: ' . $e->getMessage(), [
                'driver_id' => $request->user()->driver->id,
                'trace'     => $e->getTraceAsString()
            ]);
        
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * ➖ 4. دالة إزالة منطقة منفردة من تفضيلات السائق
     */
    public function removeZone(Request $request): JsonResponse
    {
        $request->validate([
            'zone_id' => ['required', 'integer']
        ]);

        try {
            $driver = $request->user()->driver;
            if (!$driver) {
                return response()->json(['status' => false, 'message' => 'ملف التعريف غير موجود.'], 404);
            }

            $updatedDriver = $this->preferenceService->removeZoneFromDriver($driver, $request->zone_id);

            return response()->json([
                'status'  => true,
                'message' => 'تم إزالة المنطقة من تفضيلات التغطية الخاصة بك بنجاح.',
                'data'    => new DriverPreferenceResource($updatedDriver)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            // 💡 هذا السطر يقوم بتسجيل الخطأ مع تفاصيل المسار والملف والسطر (مهم جداً للمطورين)
            \Log::error('Driver Preference Error: ' . $e->getMessage(), [
                'driver_id' => $request->user()->driver->id,
                'trace'     => $e->getTraceAsString()
            ]);
        
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * ⚙️ 5. دالة الخيارات الافتراضية للنظام (البلديات والمحلات والزونات لبناء القوائم المنسدلة في الفرونت)
     */
    public function defaults(): JsonResponse
    {
        $defaults = $this->preferenceService->getSystemDefaults();

        return response()->json([
            'status' => true,
            'data'   => $defaults
        ], Response::HTTP_OK);
    }
}