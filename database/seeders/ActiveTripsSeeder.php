<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Shared\ActiveSubscription;
use App\Models\Parent\Child;
use App\Models\Shared\Trip;
use App\Models\Shared\Route;
use App\Models\Driver\Driver;
use Carbon\Carbon;

class ActiveTripsSeeder extends Seeder
{
    public function run(): void
    {
        echo "🚀 بدء إنشاء ورحل رحلات نشطة للأطفال الذين لديهم اشتراكات فعالة بدون حذف أي بيانات...\n";

        // 1. جلب كافة الاشتراكات النشطة المترابطة مع الأطفال والسائقين
        $activeSubs = ActiveSubscription::where('status', 'active')
            ->whereIn('child_id', Child::pluck('id')) // فلترة الأطفال الموجودين فعلياً في جدول children
            ->with(['child', 'driver'])
            ->get();

        if ($activeSubs->isEmpty()) {
            echo "ℹ️ لا توجد اشتراكات نشطة في جدول active_subscriptions لإنشاء رحلات لها.\n";
            return;
        }

        // تجميع الاشتراكات حسب السائق
        $subsByDriver = $activeSubs->groupBy('driver_id');
        $today = Carbon::today()->toDateString();
        $now   = Carbon::now();

        foreach ($subsByDriver as $driverId => $subs) {
            $driver = Driver::find($driverId);
            if (!$driver) continue;

            // أ) الحصول على مركبة السائق أو إنشاء واحدة نشطة له إن لم توجد
            $vehicleId = DB::table('vehicles')->where('driver_id', $driverId)->value('id');
            if (!$vehicleId) {
                $vehicleId = DB::table('vehicles')->insertGetId([
                    'driver_id'       => $driverId,
                    'plate_number'    => '5-' . rand(10000, 99999),
                    'brand'           => 'تويوتا',
                    'model'           => 'هايس',
                    'year'            => 2022,
                    'color'           => 'أبيض',
                    'type'            => 'Van',
                    'capacity_manual' => 14,
                    'is_verified'     => 1,
                    'status'          => 'Active',
                    'created_at'      => $now,
                    'updated_at'      => $now
                ]);
            }

            // ب) الحصول على مسار السائق أو إنشاء مسار جديد له
            $route = Route::where('driver_id', $driverId)->first();
            if (!$route) {
                $route = Route::create([
                    'driver_id'          => $driverId,
                    'vehicle_id'         => $vehicleId,
                    'route_name'         => 'مسار رحلة الذهاب الصباحية',
                    'route_type'         => 'Morning',
                    'start_time'         => '07:00:00',
                    'estimated_duration' => 45,
                    'status'             => 'Active'
                ]);
            }

            // ج) إنشاء أو تحديث الرحلة اليومية النشطة بالسائق (Status = started)
            $trip = Trip::where('driver_id', $driverId)
                ->where('trip_date', $today)
                ->where('status', 'started')
                ->first();

            if (!$trip) {
                $trip = Trip::create([
                    'driver_id'            => $driverId,
                    'route_id'             => $route->id,
                    'trip_type'            => 'Morning',
                    'status'               => 'started',
                    'trip_date'            => $today,
                    'scheduled_at'         => Carbon::today()->setTime(7, 0, 0),
                    'started_at'           => Carbon::now()->subMinutes(20),
                    'scheduled_start_time' => Carbon::today()->setTime(7, 0, 0),
                    'actual_start_time'    => Carbon::now()->subMinutes(20),
                    'driver_attendance'    => 1,
                    'created_at'           => $now,
                ]);
            }

            // د) ربط حضور الأطفال وأحداث الصعود (Events) والـ GPS لكل اشتراك نشط
            foreach ($subs as $sub) {
                $childId = $sub->child_id;
                if (!$childId || !Child::where('id', $childId)->exists()) continue;

                // 1. تسجيل حضور الطفل في الرحلة الحالية
                DB::table('trip_student_attendance')->updateOrInsert(
                    ['trip_id' => $trip->id, 'child_id' => $childId],
                    ['attendance_status' => 'present', 'updated_at' => $now, 'created_at' => $now]
                );

                // 2. إيقاع حدث صعود الطفل للحافلة (trip_events)
                $eventExists = DB::table('trip_events')
                    ->where('trip_id', $trip->id)
                    ->where('child_id', $childId)
                    ->exists();

                if (!$eventExists) {
                    DB::table('trip_events')->insert([
                        'trip_id'         => $trip->id,
                        'child_id'        => $childId,
                        'subscription_id' => $sub->id,
                        'action_type'     => 'picked_up',
                        'trip_type'       => 'ذهاب',
                        'location_lat'    => $sub->pickup_lat ?? 32.89200000,
                        'location_lng'    => $sub->pickup_lng ?? 13.17500000,
                        'scanned_at'      => Carbon::now()->subMinutes(rand(3, 15)),
                        'trip_cost'       => 15.00
                    ]);
                }
            }

            // هـ) إدخال إحداثيات التتبع المباشر بالحافلة (trip_tracking)
            $lat = 32.89350000 + (rand(-50, 50) / 10000);
            $lng = 13.17650000 + (rand(-50, 50) / 10000);

            DB::table('trip_tracking')->insert([
                'trip_id'     => $trip->id,
                'latitude'    => $lat,
                'longitude'   => $lng,
                'speed'       => 40.5,
                'accuracy'    => 4.5,
                'recorded_at' => $now
            ]);

            echo "✅ تم إنشاء رحلة نشطة (Trip ID: {$trip->id}) للسائق ID: {$driverId} وربط الأطفال المشتركين بها بنجاح!\n";
        }

        echo "🎉 اكتمل إنشاء الرحلات النشطة لجميع الأطفال المشتركين بنجاح تام!\n";
    }
}
