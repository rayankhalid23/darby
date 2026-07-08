<?php

namespace App\Services\Trip;

use App\Models\Shared\Trip;
use App\Models\Shared\TripEvent;
use App\Models\Shared\AbsenceLog;
use App\Models\Driver\DriverAbsence;
use App\Models\Shared\ActiveSubscription;
use App\Services\Shared\OsrmRoutingService;
use App\Models\Driver\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Cache; // ✅ استيراد الفيساد الصحيح للكاش
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CustomDatabaseNotification;
use Carbon\Carbon;
use Exception;

class TripLifecycleService
{
    protected OsrmRoutingService $osrmService;

    /**
     * حقن خدمة الـ OSRM التي قمنا بإعدادها مسبقاً
     */
    public function __construct(OsrmRoutingService $osrmService)
    {
        $this->osrmService = $osrmService;
    }

    /**
     * الدالة 1: startTrip (بدء الرحلة اليومية للسائق مع الاستثناءات المحددة)
     */
   /**
     * بدء رحلة جديدة للسائق مع التحقق من حالات التضارب
     */
    public function startTrip(int $driverId, string $tripType)
    {
        \Illuminate\Support\Facades\Log::info("Attempting to start trip for driver: $driverId");
        $today = Carbon::today()->toDateString();

        // [استثناء 1]: التحقق مما إذا كان السائق مسجل كغائب اليوم
        $isDriverAbsent = \App\Models\Driver\DriverAbsence::where('driver_id', $driverId)
            ->whereDate('absence_date', $today)
            ->exists();

        if ($isDriverAbsent) {
            throw new \Exception('لا يمكن بدء الرحلة؛ السائق مسجل كغائب لهذا اليوم.');
        }

        // [استثناء 2]: منع التضارب إذا كانت هناك رحلة بدأت بالفعل ولم تُغلق
        $activeTrip = Trip::where('driver_id', $driverId)
            ->where('status', 'started')
            ->first();
            
        if ($activeTrip) {
            return $activeTrip; // إرجاع الرحلة المفتوحة حالياً لتجنب الازدواجية
        }

        return DB::transaction(function () use ($driverId, $tripType) {
    
    // 🛡️ حماية وتوحيد النص القادم ليتوافق تماماً مع الـ Enum في قاعدة البيانات ('Morning', 'Afternoon')
    $incomingType = strtolower($tripType);
    $dbTripType = (in_array($incomingType, ['morning', 'صباحية', 'صباح', 'morning']) || str_contains($incomingType, 'صباح')) 
        ? 'Morning' 
        : 'Afternoon';

    // البحث عن المسار باستخدام القيمة الموحدة
    $route = \App\Models\Shared\Route::where('driver_id', $driverId)
        ->where('route_type', $dbTripType) 
        ->where('status', 'Active')
        ->first();

    $trip = Trip::create([
        'driver_id'           => $driverId,
        'trip_type'           => $dbTripType, // هنا نضمن تخزين القيمة الصحيحة تماماً الإنجليزية وبحرف كبير
        'status'              => 'started',
        'route_id'            => $route?->id ?? 0,
        'scheduled_start_time'=> Carbon::now(),
        'actual_start_time'   => Carbon::now(),
        'scheduled_at'        => Carbon::now(),
        'trip_date'           => Carbon::today()->toDateString(),
    ]);

    // حساب المسار والتواقيت المبدئية عند الانطلاق فوراً
    $this->calculateInitialRoute($trip->id);

    return $trip->fresh(); 
});

    }

    /**
     * الدالة 2: calculateInitialRoute (حساب المسار وتوليده من محرك OSRM باستثناء الأطفال الغائبين)
     */
    public function calculateInitialRoute(int $tripId): ?array
    {
        $trip = Trip::findOrFail($tripId);
        $today = Carbon::today()->toDateString();

        // جلب إحداثيات موقع السائق الحالي لتبدأ خريطة السير من عنده
        $driver = Driver::findOrFail($trip->driver_id);
        $coordinates = [
            ['lat' => $driver->current_lat, 'lng' => $driver->current_lng]
        ];

        // جلب الاشتراكات الفعالة المقترنة بالسائق مرتبة هندسياً حسب حقل الترتيب
        $subscriptions = ActiveSubscription::where('driver_id', $trip->driver_id)
            ->orderBy('sort_order', 'asc')
            ->get();

        $validSubCount = 0;
        $schoolCoords = null;

        foreach ($subscriptions as $sub) {
            // [اللقطة الذكية]: استثناء الطفل إذا حددت الأم غيابه اليوم بالتواريخ
            $isChildAbsent = AbsenceLog::where('child_id', $sub->child_id)
                ->whereDate('absence_date', $today)
                ->exists();

            if ($isChildAbsent) {
                continue; // تخطي المسار وتجاوزه فوراً
            }

            // تحديد نقطة التوقف بناءً على نوع الرحلة (صباحية للحوش / مسائية للمدرسة)
            if ($trip->trip_type === 'morning') {
                $coordinates[] = ['lat' => $sub->pickup_lat, 'lng' => $sub->pickup_lng];
            } else {
                $coordinates[] = ['lat' => $sub->dropoff_lat, 'lng' => $sub->dropoff_lng];
            }

            // تأمين إحداثيات المدرسة باعتبارها المحطة الأخيرة للكل
            if (!$schoolCoords && $sub->school) {
                $schoolCoords = ['lat' => $sub->school->latitude, 'lng' => $sub->school->longitude];
            }
            
            $validSubCount++;
        }

        // حماية: إذا كان كل الأطفال غائبين اليوم، لا نرسل طلباً فارغاً لـ OSRM
        if ($validSubCount === 0) {
            return null;
        }

        // إغلاق مصفوفة المسار بالمدرسة
        if ($schoolCoords) {
            $coordinates[] = $schoolCoords;
        }

        // إرسال البيانات لمحرك OSRM المحلي وتوليد خط السير والأوقات التقديرية
        $routeData = $this->osrmService->calculateRoute($coordinates);

        return $routeData;
    }

    /**
     * الدالة 3: reorderRouteSequence (تمكين السائق من الترتيب اليدوي للمحطات)
     */
    public function reorderRouteSequence(int $driverId, array $orderedSubscriptionIds): void
    {
        DB::transaction(function () use ($orderedSubscriptionIds) {
            foreach ($orderedSubscriptionIds as $index => $subId) {
                ActiveSubscription::where('id', $subId)->update([
                    'sort_order' => $index + 1
                ]);
            }
        });
    }

    /**
     * الدالة 11 (محدثة): setChildAbsence (تحديد الأم لتواريخ غياب طفلها مع الحماية التامة)
     */
    public function setChildAbsence(int $childId, array $dates): void
    {
        $today = Carbon::today()->toDateString();
        
        DB::transaction(function () use ($childId, $dates) {
            foreach ($dates as $date) {
                $formattedDate = Carbon::parse($date)->toDateString();

                // [استثناء حماية]: منع التلاعب بأيام قد مضت وانتهت
                if ($formattedDate < Carbon::today()->toDateString()) {
                    continue;
                }

                // حفظ الغياب في جدول المجلد المشترك
                AbsenceLog::firstOrCreate([
                    'child_id' => $childId,
                    'absence_date' => $formattedDate
                ]);
            }
        });

        // [تحديث لحظي فورى]: لو عدلت الأم غياب اليوم وكانت رحلة الحافلة بدأت فعلاً، نحدث المسار فوراَ
        if (in_array($today, $dates)) {
            $this->recalculateActiveTripsForChild($childId);
        }
    }

    /**
     * دالة إضافية: removeChildAbsence (تراجع الأم عن طلب الغياب وإعادة الطفل للمسار)
     */
    public function removeChildAbsence(int $childId, array $dates): void
    {
        AbsenceLog::where('child_id', $childId)
            ->whereIn('absence_date', collect($dates)->map(fn($d) => Carbon::parse($d)->toDateString()))
            ->delete();

        $today = Carbon::today()->toDateString();
        if (in_array($today, $dates)) {
            $this->recalculateActiveTripsForChild($childId);
        }
    }

    /**
     * دالة إضافية: setDriverAbsence (تحديد السائق لأيام غيابه بالتواريخ وإشعار أولياء الأمور تلقائياً)
     */
    public function setDriverAbsence(int $driverId, array $dates): void
    {
        DB::transaction(function () use ($driverId, $dates) {
            foreach ($dates as $date) {
                $formattedDate = Carbon::parse($date)->toDateString();

                if ($formattedDate < Carbon::today()->toDateString()) {
                    continue;
                }

                DriverAbsence::firstOrCreate([
                    'driver_id' => $driverId,
                    'absence_date' => $formattedDate
                ]);
            }
        });

        // جلب جميع أولياء الأمور (Users) المرتبطين باشتراكات هذا السائق لإشعارهم
        $parentUserIds = ActiveSubscription::where('driver_id', $driverId)
            ->join('children', 'active_subscriptions.child_id', '=', 'children.id')
            ->join('parents', 'children.parent_id', '=', 'parents.id')
            ->pluck('parents.user_id')
            ->unique();

        $usersToNotify = User::whereIn('id', $parentUserIds)->get();

        // إطلاق وإيداع الإشعارات في جدول notifications
        $datesString = implode(', ', $dates);
        Notification::send($usersToNotify, new CustomDatabaseNotification([
            'title' => 'تنبيه: غياب السائق اليومي',
            'message' => "نفيدكم علماً بأن السائق حدد أيام غياب له في التواريخ التالية: ({$datesString})، ولن يتم تفعيل مسار الرحلة في هذه الأيام.",
            'type' => 'driver_absence'
        ]));
    }

    /**
     * دالة حماية داخلية (Helper) لإعادة الحساب اللحظي الفوري للمسار إذا تغيرت حالة الطفل والرحلة قائمة
     */
    protected function recalculateActiveTripsForChild(int $childId): void
    {
        $activeTrip = Trip::where('status', 'started')
            ->whereHas('driver.activeSubscriptions', function ($query) use ($childId) {
                $query->where('child_id', $childId);
            })->first();

        if ($activeTrip) {
            $this->calculateInitialRoute($activeTrip->id);
        }
    }
    /**
     * الدالة 12: completeTrip (إنهاء الرحلة وتصفير الكاش لحفظ ذاكرة السيرفر)
     */
    public function completeTrip(int $tripId): array
    {
        $trip = Trip::findOrFail($tripId);

        if ($trip->status === 'completed') {
            return ['status' => 'already_completed', 'message' => 'الرحلة مغلقة بالفعل.'];
        }

        // [استثناء أمان]: التحقق من وجود أطفال معلقين في الرحلة لم يتم معالجة حالتهم
        $today = Carbon::today()->toDateString();
        $pendingChildren = ActiveSubscription::where('driver_id', $trip->driver_id)
            ->whereNotExists(function ($query) use ($today) {
                $query->select(DB::raw(1))
                    ->from('absence_logs')
                    ->whereColumn('absence_logs.child_id', 'active_subscriptions.child_id')
                    ->whereDate('absence_logs.absence_date', $today);
            })
            ->whereNotExists(function ($query) use ($trip) {
                $query->select(DB::raw(1))
                    ->from('trip_events')
                    ->whereColumn('trip_events.child_id', 'active_subscriptions.child_id')
                    ->where('trip_events.trip_id', $trip->id)
                    ->whereIn('trip_events.action_type', ['picked_up', 'skipped']);
            })
            ->count();

        // إذا كان هناك أطفال معلقين، نرجع تحذيراً للسائق (يمكنك جعلها Exception حسب رغبتك في UX)
        if ($pendingChildren > 0) {
            return [
                'status' => 'warning',
                'message' => "يوجد عدد ({$pendingChildren}) من الأطفال لم يتم تأكيد ركوبهم أو تخطيهم بعد. هل أنت متأكد من الإنهاء؟"
            ];
        }

        return DB::transaction(function () use ($trip) {
            // 1. تحديث حالة الرحلة في قاعدة البيانات
            $trip->update([
                'status' => 'completed',
                'actual_end_time' => Carbon::now()
            ]);

            // 2. تصفير وتنظيف الـ Cache الخاص بهذه الرحلة تماماً للحفاظ على موارد الخادم
            $driverId = $trip->driver_id;
            Cache::forget("driver_last_loc_{$driverId}");
            
            // جلب الأطفال لتنظيف كاش العدادات الخاص بهم
            $childIds = ActiveSubscription::where('driver_id', $driverId)->pluck('child_id');
            foreach ($childIds as $childId) {
                Cache::forget("trip_waiting_{$trip->id}_{$childId}");
                Cache::forget("proximity_alert_sent_{$trip->id}_{$childId}");
                Cache::forget("automatic_arrival_logged_{$trip->id}_{$childId}");
            }

            // 3. إشعار أولياء الأمور المشتركين في هذه الرحلة بنهاية الرحلة والوصول الآمن للوجهة
            $parentUserIds = ActiveSubscription::where('driver_id', $driverId)
                ->join('children', 'active_subscriptions.child_id', '=', 'children.id')
                ->join('parents', 'children.parent_id', '=', 'parents.id')
                ->pluck('parents.user_id')
                ->unique();

            $usersToNotify = User::whereIn('id', $parentUserIds)->get();
            
            $destination = $trip->trip_type === 'morning' ? 'المدرسة' : 'المنزل';
            Notification::send($usersToNotify, new CustomDatabaseNotification([
                'title' => 'وصلت الحافلة بسلام 🏁',
                'message' => "أنهى السائق الرحلة بنجاح، ووصل جميع الأطفال إلى {$destination} بسلامة الله.",
                'type' => 'trip_completed'
            ]));

            return [
                'status' => 'success',
                'message' => 'تم إنهاء الرحلة وتصفير سجلات الكاش المؤقتة بنجاح.'
            ];
        });
    }
}