<?php

namespace App\Services\Trip;

use App\Models\Shared\Trip;
use App\Models\Shared\ActiveSubscription;
use App\Models\Parent\ParentModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ParentTripService
{
    private function resolveParentIds(int $userId): array
    {
        $parent = ParentModel::where('user_id', $userId)->first();
        return array_values(array_unique(array_filter([$userId, $parent ? $parent->id : null])));
    }

    /**
     * 1. جلب الرحلات المفعلة حالياً لأطفال ولي الأمر
     */
    public function getActiveTripsForParent(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        // جلب معرفات أطفال ولي الأمر
        $childIds = DB::table('children')
            ->whereIn('parent_id', $parentIds)
            ->pluck('id')
            ->toArray();

        if (empty($childIds)) {
            return [];
        }

        // جلب الاشتراكات النشطة لمعرفة السائقين المرتبطين بالأطفال
        $subscriptions = ActiveSubscription::whereIn('child_id', $childIds)
            ->where('status', 'active')
            ->with(['child', 'driver.user', 'school'])
            ->get();

        $driverIds = $subscriptions->pluck('driver_id')->unique()->toArray();

        // جلب الرحلات القائمة حالياً لهؤلاء السائقين
        $activeTrips = Trip::whereIn('driver_id', $driverIds)
            ->where('status', 'started')
            ->whereDate('trip_date', Carbon::today()->toDateString())
            ->get();

        $result = [];

        foreach ($activeTrips as $trip) {
            // جلب الأطفال التابعين لهذا الأب والموجودين في هذه الرحلة
            $subChildren = $subscriptions->where('driver_id', $trip->driver_id);

            foreach ($subChildren as $sub) {
                // جلب حالة الطفل في الرحلة الحالية من جدول trip_events
                $event = DB::table('trip_events')
                    ->where('trip_id', $trip->id)
                    ->where('child_id', $sub->child_id)
                    ->latest('scanned_at')
                    ->first();

                // التحقق مما إذا كان الطفل غائباً اليوم
                $isAbsent = DB::table('absence_logs')
                    ->where('child_id', $sub->child_id)
                    ->whereDate('absence_date', Carbon::today()->toDateString())
                    ->exists();

                $childStatus = 'waiting'; // الافتراضي: ينتظر وصول الحافلة
                if ($isAbsent) {
                    $childStatus = 'absent';
                } elseif ($event) {
                    $childStatus = $event->action_type; // picked_up or dropped_off or absent
                }

                // جلب بيانات عداد الانتظار من الكاش إن كان السائق واقفاً عند بيت الطفل
                $waitingCache = Cache::get("trip_waiting_{$trip->id}_{$sub->child_id}");

                $result[] = [
                    'trip_id'        => $trip->id,
                    'trip_type'      => $trip->trip_type,
                    'status'         => $trip->status,
                    'driver_name'    => $sub->driver->user->full_name ?? $sub->driver->user->name ?? 'سائق الحافلة',
                    'driver_phone'   => $sub->driver->user->phone_number ?? $sub->driver->user->phone ?? null,
                    'vehicle_info'   => $sub->driver->vehicle_info ?? 'حافلة مدرسية',
                    'child_id'       => $sub->child_id,
                    'child_name'     => $sub->child->full_name,
                    'child_status'   => $childStatus,
                    'waiting_timer'  => $waitingCache,
                    'started_at'     => $trip->actual_start_time,
                ];
            }
        }

        return $result;
    }

    /**
     * 2. جلب بيانت التتبع اللحظي لرحلة معينة (Live Tracking Data)
     */
    public function getLiveTracking(int $userId, int $tripId): array
    {
        $trip = Trip::with('driver')->findOrFail($tripId);

        // أخذ موقع السائق اللحظي من الكاش للحفاظ على السرعة، أو قاعدة البيانات كخيار بديل
        $cacheKey = "driver_last_loc_{$trip->driver_id}";
        $cachedLoc = Cache::get($cacheKey);

        $driverLat = $cachedLoc['lat'] ?? $trip->driver->current_lat;
        $driverLng = $cachedLoc['lng'] ?? $trip->driver->current_lng;

        return [
            'trip_id'      => $trip->id,
            'status'       => $trip->status,
            'driver_lat'   => (float)$driverLat,
            'driver_lng'   => (float)$driverLng,
            'last_updated' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * 3. جلب الرحلات القادمة (Upcoming Trips)
     */
    public function getUpcomingTrips(int $userId): array
    {
        $parentIds = $this->resolveParentIds($userId);

        $subscriptions = ActiveSubscription::whereHas('child', function ($q) use ($parentIds) {
            $q->whereIn('parent_id', $parentIds);
        })
        ->where('status', 'active')
        ->with(['child', 'driver.user', 'school'])
        ->get();

        $upcoming = [];
        $today = Carbon::today()->toDateString();

        foreach ($subscriptions as $sub) {
            // التحقق هل تم بدء رحلة اليوم وإنهائها بالفعل
            $completedToday = Trip::where('driver_id', $sub->driver_id)
                ->whereDate('trip_date', $today)
                ->where('status', 'completed')
                ->pluck('trip_type')
                ->toArray();

            // رحلة الصباح القادمة
            if (!in_array('Morning', $completedToday)) {
                $upcoming[] = [
                    'child_name'   => $sub->child->full_name,
                    'trip_type'    => 'Morning',
                    'title'        => 'رحلة الذهاب للمدرسة',
                    'scheduled_for'=> 'اليوم صباحاً',
                    'driver_name'  => $sub->driver->user->full_name ?? $sub->driver->user->name ?? 'السائق',
                    'school_name'  => $sub->school->name ?? '',
                ];
            }

            // رحلة المساء القادمة
            if (!in_array('Afternoon', $completedToday)) {
                $upcoming[] = [
                    'child_name'   => $sub->child->full_name,
                    'trip_type'    => 'Afternoon',
                    'title'        => 'رحلة العودة للمنزل',
                    'scheduled_for'=> 'اليوم ظهراً',
                    'driver_name'  => $sub->driver->user->full_name ?? $sub->driver->user->name ?? 'السائق',
                    'school_name'  => $sub->school->name ?? '',
                ];
            }
        }

        return $upcoming;
    }

    /**
     * 4. أرشيف الرحلات بالكامل للأطفال المشتركين
     */
    public function getTripHistory(int $userId, int $perPage = 15)
    {
        $parentIds = $this->resolveParentIds($userId);

        return DB::table('trip_events')
            ->join('trips', 'trip_events.trip_id', '=', 'trips.id')
            ->join('children', 'trip_events.child_id', '=', 'children.id')
            ->join('drivers', 'trips.driver_id', '=', 'drivers.id')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->whereIn('children.parent_id', $parentIds)
            ->select(
                'trips.id as trip_id',
                'trips.trip_type',
                'trips.trip_date',
                'children.full_name as child_name',
                'users.full_name as driver_name',
                'trip_events.action_type',
                'trip_events.scanned_at',
                'trip_events.trip_cost'
            )
            ->orderBy('trip_events.scanned_at', 'desc')
            ->paginate($perPage);
    }
}