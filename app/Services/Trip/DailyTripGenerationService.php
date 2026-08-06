<?php

namespace App\Services\Trip;

use App\Models\Driver\DriverSeatSlot;
use App\Models\Parent\Child;
use App\Models\Shared\AbsenceLog;
use App\Models\Shared\Route;
use App\Models\Shared\RouteStop;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\User;
use App\Notifications\CustomDatabaseNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * يولّد الرحلة اليومية التشغيلية (Daily Trip Instance) من المسار الهيكلي المرجعي (Master Route)
 * قبل موعد الوردية بـ 30 دقيقة، مع استبعاد الأطفال المسجل غيابهم مسبقاً (absent_pre).
 */
class DailyTripGenerationService
{
    /**
     * ينشئ (أو يعيد) رحلة اليوم لمسار معين — عملية idempotent، آمنة للاستدعاء المتكرر
     * (سواء من الـ cron أو من السحب اللحظي في todayTrips()).
     */
    public function generateForRoute(Route $route, ?Carbon $date = null): ?Trip
    {
        $date = $date ?? Carbon::today();
        $dateString = $date->toDateString();

        $trip = Trip::where('driver_id', $route->driver_id)
            ->where('route_id', $route->id)
            ->where('trip_date', $dateString)
            ->where('trip_type', $route->route_type)
            ->first();

        $isNew = false;

        if (!$trip) {
            $trip = Trip::create([
                'driver_id'            => $route->driver_id,
                'route_id'             => $route->id,
                'trip_type'            => $route->route_type,
                'shift_slot'           => $route->shift_slot,
                'status'               => 'pending',
                'scheduled_at'         => now(),
                'scheduled_start_time' => $route->start_time,
                'trip_date'            => $dateString,
            ]);
            $isNew = true;
        }

        if (TripStop::where('trip_id', $trip->id)->doesntExist()) {
            $this->buildTripStops($trip, $route, $dateString);
        }

        if ($isNew) {
            $this->notifyDriverAndParents($trip, $route);
        }

        return $trip;
    }

    /**
     * يفحص كل المسارات النشطة ويولّد رحلة اليوم لأي مسار دخل نافذة T-30 ولم تُولَّد رحلته بعد.
     */
    public function generateDueTrips(?Carbon $now = null): array
    {
        $now = $now ?? Carbon::now();
        $today = $now->copy()->startOfDay();

        $routes = Route::where('status', 'Active')
            ->whereNotNull('shift_slot')
            ->whereNotNull('start_time')
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($routes as $route) {
            $alreadyExists = Trip::where('driver_id', $route->driver_id)
                ->where('route_id', $route->id)
                ->where('trip_date', $today->toDateString())
                ->exists();

            if ($alreadyExists) {
                $skipped++;
                continue;
            }

            $startDateTime = Carbon::parse($today->toDateString() . ' ' . $route->start_time);
            $windowStart = $startDateTime->copy()->subMinutes(30);

            if ($now->greaterThanOrEqualTo($windowStart) && $now->lessThanOrEqualTo($startDateTime)) {
                $this->generateForRoute($route, $today);
                $generated++;
            } else {
                $skipped++;
            }
        }

        return [
            'checked'   => $routes->count(),
            'generated' => $generated,
            'skipped'   => $skipped,
        ];
    }

    private function buildTripStops(Trip $trip, Route $route, string $dateString): void
    {
        $routeStops = RouteStop::where('route_id', $route->id)->orderBy('sequence_order')->get();
        if ($routeStops->isEmpty()) {
            return;
        }

        $isGo = DriverSeatSlot::isGoSlot($route->shift_slot ?? '');
        $absenceTypesToExclude = $isGo
            ? [AbsenceLog::TYPE_PICKUP, AbsenceLog::TYPE_BOTH]
            : [AbsenceLog::TYPE_DROPOFF, AbsenceLog::TYPE_BOTH];

        $absentChildIds = AbsenceLog::whereDate('absence_date', $dateString)
            ->whereIn('absence_type', $absenceTypesToExclude)
            ->pluck('child_id')
            ->all();

        // المرحلة 1: تحديد الأطفال المتبقين (غير الغائبين) اليوم
        $keptChildIds = $routeStops->where('stop_type', RouteStop::TYPE_HOME)
            ->pluck('child_id')
            ->reject(fn($id) => in_array($id, $absentChildIds))
            ->values()
            ->all();

        $keptSchoolIds = !empty($keptChildIds)
            ? Child::whereIn('id', $keptChildIds)->pluck('school_id')->unique()->all()
            : [];

        // المرحلة 2: بناء المحطات بنفس الترتيب الأصلي، مع إعادة ترقيم المحطات المتبقية فقط
        $sequence = 1;

        foreach ($routeStops as $stop) {
            $isHome = $stop->stop_type === RouteStop::TYPE_HOME;
            $isKept = $isHome
                ? in_array($stop->child_id, $keptChildIds)
                : in_array($stop->school_id, $keptSchoolIds);

            TripStop::create([
                'trip_id'        => $trip->id,
                'route_stop_id'  => $stop->id,
                'stop_type'      => $stop->stop_type,
                'child_id'       => $stop->child_id,
                'school_id'      => $stop->school_id,
                'lat'            => $stop->lat,
                'lng'            => $stop->lng,
                'label'          => $stop->label,
                'sequence_order' => $isKept ? $sequence : 0,
                'status'         => $isKept ? TripStop::STATUS_PENDING : TripStop::STATUS_ABSENT_PRE,
            ]);

            if ($isKept) {
                $sequence++;
            }
        }
    }

    private function notifyDriverAndParents(Trip $trip, Route $route): void
    {
        try {
            $driverUser = $route->driver?->user;
            if ($driverUser) {
                $driverUser->notify(new CustomDatabaseNotification([
                    'title'    => 'رحلتك القادمة تبدأ بعد 30 دقيقة',
                    'message'  => 'تم تجهيز رحلتك القادمة. انقر لمعاينة الطلاب.',
                    'type'     => 'trip_ready',
                    'metadata' => ['trip_id' => $trip->id],
                ]));
            }

            $pendingChildIds = TripStop::where('trip_id', $trip->id)
                ->where('stop_type', TripStop::TYPE_HOME)
                ->where('status', TripStop::STATUS_PENDING)
                ->pluck('child_id');

            if ($pendingChildIds->isEmpty()) {
                return;
            }

            $parentUserIds = Child::whereIn('children.id', $pendingChildIds)
                ->join('parents', 'children.parent_id', '=', 'parents.id')
                ->pluck('parents.user_id')
                ->unique();

            $users = User::whereIn('id', $parentUserIds)->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new CustomDatabaseNotification([
                    'title'    => 'تذكير: انطلاق رحلة طفلكم بعد 30 دقيقة',
                    'message'  => 'رحلة طفلكم ستنطلق قريباً، يرجى الاستعداد.',
                    'type'     => 'trip_upcoming',
                    'metadata' => ['trip_id' => $trip->id],
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning("فشل إرسال إشعارات توليد الرحلة اليومية للرحلة ID: {$trip->id} - " . $e->getMessage());
        }
    }
}
