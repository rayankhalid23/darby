<?php

namespace App\Http\Controllers\Api\Trip;

use App\Http\Controllers\Controller;
// استدعاء الـ Requests من مجلدها المنظم الجديد
use App\Http\Requests\Api\Trip\StartTripRequest;
use App\Http\Requests\Api\Trip\UpdateLocationRequest;
use App\Http\Requests\Api\Trip\VerifyQrRequest;
use App\Http\Requests\Api\Trip\DriverAbsenceRequest;
// استدعاء الـ Resource من مجلده المنظم الجديد
use App\Http\Resources\Api\Trip\TripResource;
use App\Services\Trip\TripLifecycleService;
use App\Services\Trip\TripTrackingService;
use App\Services\Trip\TripStopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // 👈 استدعاء واجهة الـ Log لتسجيل الأحداث
use Exception;

class DriverTripController extends Controller
{
    protected TripLifecycleService $lifecycleService;
    protected TripTrackingService $trackingService;
    protected TripStopService $stopService;

    public function __construct(
        TripLifecycleService $lifecycleService,
        TripTrackingService $trackingService,
        TripStopService $stopService
    ) {
        $this->lifecycleService = $lifecycleService;
        $this->trackingService = $trackingService;
        $this->stopService = $stopService;
    }

    public function start(StartTripRequest $request): JsonResponse
    {
        $user = Auth::user();
        $driverId = $user->driver->id;

        try {
            $trip = $this->lifecycleService->startTrip($driverId, $request->trip_type);
            
            // تسجيل نجاح بدء الرحلة
            Log::info("Trip Started Successfully", [
                'driver_id'   => $driverId,
                'driver_name' => $user->name,
                'trip_id'     => $trip->id,
                'trip_type'   => $request->trip_type
            ]);

            return response()->json([
                'status'  => 'success', 
                'message' => 'تم بدء الرحلة بنجاح', 
                'data'    => new TripResource($trip)
            ]);
        } catch (Exception $e) {
            // تسجيل فشل عملية بدء الرحلة والسبب
            Log::error("Failed to start trip", [
                'driver_id' => $driverId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function updateLocation(UpdateLocationRequest $request, $tripId): JsonResponse
    {
        $driverId = Auth::user()->driver->id;

        // تسجيل استلام إحداثيات جديدة (مفيد جداً في بيئة التطوير لتتبع البث المستمر للـ GPS)
        Log::debug("GPS Location Received", [
            'trip_id'   => $tripId,
            'driver_id' => $driverId,
            'lat'       => $request->latitude,
            'lng'       => $request->longitude,
            'speed'     => $request->speed ?? 0
        ]);

        $result = $this->trackingService->updateDriverLocation(
            $tripId, $request->latitude, $request->longitude, $request->speed ?? 0
        );
        
        return response()->json($result);
    }

    public function skip($tripId, $childId): JsonResponse
    {
        $driverId = Auth::user()->driver->id;

        try {
            $this->stopService->skipChild($tripId, $childId);
            
            // تسجيل تجاوز المحطة
            Log::notice("Station Skipped by Driver", [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
                'child_id'  => $childId
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم تخطي المحطة بنجاح وإعادة حساب المسار']);
        } catch (Exception $e) {
            // تسجيل الفشل في التخطي
            Log::error("Failed to skip station", [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
                'child_id'  => $childId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function verifyQr(VerifyQrRequest $request, $tripId, $childId): JsonResponse
    {
        $driverId = Auth::user()->driver->id;

        try {
            $this->stopService->verifyPickupQR(
                $tripId, $childId, $request->qr_code, $request->latitude, $request->longitude
            );

            // تسجيل إثبات صعود الطفل بنجاح بالـ QR والموقع الممسوح فيه
            Log::info("QR Code Verified - Child Picked Up", [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
                'child_id'  => $childId,
                'location'  => ['lat' => $request->latitude, 'lng' => $request->longitude]
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم التحقق وصعود الطفل بسلام']);
        } catch (Exception $e) {
            // تسجيل محاولات المسح الخاطئة كتحذير أمني
            Log::warning("QR Verification Failed", [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
                'child_id'  => $childId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function registerAbsence(DriverAbsenceRequest $request): JsonResponse
    {
        $user = Auth::user();
        $driverId = $user->driver->id;

        try {
            $this->lifecycleService->setDriverAbsence($driverId, $request->dates);
            
            // تسجيل غياب السائق والتواريخ المجدولة له
            Log::info("Driver Absence Registered", [
                'driver_id'   => $driverId,
                'driver_name' => $user->name,
                'dates'       => $request->dates
            ]);

            return response()->json(['status' => 'success', 'message' => 'تم تسجيل أيام غيابك وإشعار أولياء الأمور']);
        } catch (Exception $e) {
            Log::error("Failed to register driver absence", [
                'driver_id' => $driverId,
                'error'     => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    public function complete($tripId): JsonResponse
    {
        $driverId = Auth::user()->driver->id;

        try {
            $result = $this->lifecycleService->completeTrip($tripId);
            
            // تسجيل إنهاء الرحلة مع حالتها النهائية (مكتملة أو بها تحذير)
            Log::info("Trip Completion Triggered", [
                'trip_id'     => $tripId,
                'driver_id'   => $driverId,
                'status_type' => $result['status']
            ]);

            if ($result['status'] === 'warning') {
                return response()->json($result, 202);
            }
            return response()->json($result);

        } catch (Exception $e) {
            Log::error("Error during trip completion Process", [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
                'error'     => $e->getMessage()
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}