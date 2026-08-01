<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

class DriverTrackingController extends Controller
{
    public function updateLocation(Request $request)
    {
        // التحقق من البيانات الواردة من السائق
        $request->validate([
            'trip_id' => 'required',
            'driver_lat' => 'required|numeric',
            'driver_lng' => 'required|numeric',
            'heading' => 'nullable|numeric',
            'is_online' => 'required|boolean',
        ]);

        try {
            // تهيئة اتصال الفايربيز باستخدام الملف الآمن الذي حددناه مسبقاً
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials.file'));
            $firestore = $factory->createFirestore();
            $database = $firestore->database();

            $tripId = $request->trip_id;

            // البيانات التي سيتم تحديثها لحظياً
            $trackingData = [
                'trip_id' => (int) $tripId,
                'driver_lat' => (float) $request->driver_lat,
                'driver_lng' => (float) $request->driver_lng,
                'heading' => (float) ($request->heading ?? 0.0),
                'is_online' => (bool) $request->is_online,
                'last_updated' => now()->toIso8601String(),
            ];

            // كتابة أو تحديث البيانات داخل Collection اسمها trips_tracking والـ Document هو رقم الرحلة
            $database->collection('trips_tracking')->document((string) $tripId)->set($trackingData);

            return response()->json([
                'status' => true,
                'message' => 'Location updated successfully in Firebase',
                'data' => $trackingData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}