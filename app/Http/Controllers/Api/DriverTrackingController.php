<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Trip\TripTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DriverTrackingController extends Controller
{
    protected TripTrackingService $trackingService;

    public function __construct(TripTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * نقطة نهاية قديمة (Legacy) لتحديث الموقع — أُبقيت للتوافق مع نسخ سابقة من التطبيق،
     * لكنها الآن تفوّض المنطق بالكامل لنفس TripTrackingService المستخدم في المسار الرئيسي
     * (POST /driver/trips/{tripId}/location) بدلاً من الكتابة المباشرة لـ Firestore فقط،
     * حتى يتطابق السلوك بين المسارين: نفس تسجيل نقاط trip_tracking، نفس منطق الـ dedup،
     * ونفس الاستشعار التلقائي لبدء الرحلة (Auto-Start).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id'     => 'required|integer|exists:trips,id',
            'driver_lat'  => 'required|numeric|between:-90,90',
            'driver_lng'  => 'required|numeric|between:-180,180',
            'heading'     => 'nullable|numeric|between:0,360',
            'is_online'   => 'nullable|boolean',
        ]);

        $this->trackingService->updateDriverLocation(
            (int) $request->trip_id,
            (float) $request->driver_lat,
            (float) $request->driver_lng,
            0.0,
            $request->heading !== null ? (float) $request->heading : null
        );

        return response()->json([
            'status'  => true,
            'message' => 'Location updated successfully',
        ], 200);
    }
}
