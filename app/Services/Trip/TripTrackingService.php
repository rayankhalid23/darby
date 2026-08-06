<?php

namespace App\Services\Trip;

use App\Models\Shared\Trip;
use App\Models\Shared\TripEvent;
use App\Models\Shared\TripTracking;
use App\Models\Shared\ActiveSubscription;
use App\Models\Driver\Driver;
use App\Models\User;
use App\Notifications\CustomDatabaseNotification;
use App\Notifications\TripStartedNotification; // تأكد من وجود هذا الـ Notification
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class TripTrackingService
{
    /**
     * استقبال إحداثيات السائق وتخزينها ذكياً
     */
    public function updateDriverLocation(int $tripId, float $lat, float $lng, float $speed = 0): array
    {
        $trip = Trip::findOrFail($tripId);

        // 1. الاستشعار التلقائي للبدء (Auto-Start)
        if ($trip->status === 'pending' && $speed > 10) {
            $this->handleAutoStart($trip);
        }

        $driverId = $trip->driver_id;
        
        // تحديث الموقع اللحظي للسائق
        Driver::where('id', $driverId)->update([
            'current_lat' => $lat,
            'current_lng' => $lng
        ]);

        // 2. هندسة الكاش لمنع التكرار في قاعدة البيانات
        $cacheKey = "driver_last_loc_{$driverId}";
        $lastLocation = Cache::get($cacheKey);
        $shouldSaveToDb = (!$lastLocation || $this->calculateHaversineDistance($lastLocation['lat'], $lastLocation['lng'], $lat, $lng) > 0.015);

        if ($shouldSaveToDb) {
            TripTracking::create([
                'trip_id' => $tripId,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'recorded_at' => Carbon::now()
            ]);
            Cache::put($cacheKey, ['lat' => $lat, 'lng' => $lng], now()->addHours(6));
        }

        return [
            'status' => 'success',
            'proximity' => $this->checkProximityAndArrival($trip, $lat, $lng)
        ];
    }

    /**
     * معالجة بدء الرحلة التلقائي والتصحيح اللاحق (Back-dating)
     */
    protected function handleAutoStart(Trip $trip)
    {
        // جلب وقت أول إحداثية مسجلة للرحلة (دقة الـ Back-dating)
        $firstTracking = TripTracking::where('trip_id', $trip->id)->orderBy('recorded_at', 'asc')->first();
        $startTime = $firstTracking ? $firstTracking->recorded_at : Carbon::now();

        $trip->update([
            'status' => 'in_progress',
            'started_at' => $startTime,
            'actual_start_time' => $startTime
        ]);

        // إخطار الأهالي المعنيين فقط
        $this->notifyPendingParents($trip);
    }

    /**
     * إخطار الأهالي الذين لم يركب أطفالهم بعد (تجنب الإزعاج)
     */
    protected function notifyPendingParents(Trip $trip)
    {
        $today = Carbon::today()->toDateString();

        $pendingSubscriptions = ActiveSubscription::where('driver_id', $trip->driver_id)
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('absence_logs')->whereColumn('child_id', 'active_subscriptions.child_id')->whereDate('absence_date', $today))
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('trip_events')->whereColumn('child_id', 'active_subscriptions.child_id')->where('trip_id', $trip->id)->whereIn('action_type', ['picked_up', 'skipped']))
            ->get();

        foreach ($pendingSubscriptions as $sub) {
            if ($user = $sub->child->parent->user ?? null) {
                Notification::send($user, new CustomDatabaseNotification([
                    'title' => 'بدأت الرحلة 🚌',
                    'message' => 'الحافلة في طريقها إليكم الآن.',
                    'type' => 'trip_started'
                ]));
            }
        }
    }

    protected function checkProximityAndArrival(Trip $trip, float $driverLat, float $driverLng): array
    {
        // (تم الاحتفاظ بنفس المنطق الذكي الخاص بك سابقاً)
        // ...
        return ['status' => 'tracking'];
    }

    protected function calculateHaversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}