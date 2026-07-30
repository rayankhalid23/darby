<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Models\Shared\Route;
use App\Models\User;
use Carbon\Carbon;

class Children123TripsSeeder extends Seeder
{
    public function run(): void
    {
        echo "🚀 بدء إضافة الاشتراكات والرحلات النشطة لجميع أيام الأسبوع للأطفال (IDs: 1, 2, 3)...\n";

        // 1. التأكد من وجود الأطفال 1, 2, 3
        $children = Child::whereIn('id', [1, 2, 3])->get();
        if ($children->isEmpty()) {
            echo "❌ الأطفال غير موجودين في الداتابيز.\n";
            return;
        }

        // 2. جلب السائق (عبد السلام المصراتي ID: 36 أو أول سائق)
        $driver = Driver::find(36) ?? Driver::first();
        if (!$driver) {
            echo "❌ لا يوجد سائق لربط الأطفال به.\n";
            return;
        }

        // 3. الحصول على عقد لولي الأمر طه القمودي (User ID: 93)
        $parentId = 93;
        $contract = Contract::where('parent_id', $parentId)->first();
        if (!$contract) {
            $contract = Contract::create([
                'subscription_request_id' => 1,
                'parent_id'               => $parentId,
                'driver_id'               => $driver->user_id ?? $driver->id,
                'contract_number'         => 'CNT-2026-CHILD123',
                'subscription_type'       => 'monthly',
                'direction'               => 'two_way',
                'timing'                  => 'morning',
                'pickup_time'             => '07:00:00',
                'dropoff_time'            => '14:00:00',
                'max_waiting_time'        => 15,
                'start_date'              => Carbon::today()->startOfWeek()->toDateString(),
                'end_date'                => Carbon::today()->endOfWeek()->toDateString(),
                'days_count'              => 22,
                'total_price'             => 900.00,
                'status'                  => 'active',
                'signed_at'               => now()
            ]);
        }

        // 4. إنشاء الاشتراكات النشطة في active_subscriptions لكل طفل (1, 2, 3)
        foreach ($children as $child) {
            ActiveSubscription::updateOrCreate(
                ['child_id' => $child->id, 'driver_id' => $driver->id],
                [
                    'contract_id'   => $contract->id,
                    'parent_id'     => $parentId,
                    'pickup_lat'    => 32.89200000,
                    'pickup_lng'    => 13.17500000,
                    'pickup_label'  => 'منزل طه القمودي - حي الأندلس',
                    'dropoff_lat'   => 32.89000000,
                    'dropoff_lng'   => 13.17000000,
                    'dropoff_label' => 'مدرسة الجيل الجديد الدولية',
                    'pickup_time'   => '07:00:00',
                    'dropoff_time'  => '14:00:00',
                    'status'        => 'active'
                ]
            );
        }

        // 5. التأكد من وجود مركبة ومسار للسائق
        $vehicleId = DB::table('vehicles')->where('driver_id', $driver->id)->value('id');
        if (!$vehicleId) {
            $vehicleId = DB::table('vehicles')->insertGetId([
                'driver_id'       => $driver->id,
                'plate_number'    => '5-99887',
                'brand'           => 'تويوتا',
                'model'           => 'كوستر',
                'year'            => 2023,
                'color'           => 'أبيض',
                'type'            => 'Bus',
                'capacity_manual' => 20,
                'is_verified'     => 1,
                'status'          => 'Active',
                'created_at'      => now(),
                'updated_at'      => now()
            ]);
        }

        $route = Route::where('driver_id', $driver->id)->first();
        if (!$route) {
            $route = Route::create([
                'driver_id'          => $driver->id,
                'vehicle_id'         => $vehicleId,
                'route_name'         => 'مسار أطفال طه القمودي - حي الأندلس',
                'route_type'         => 'Morning',
                'start_time'         => '07:00:00',
                'estimated_duration' => 40,
                'status'             => 'Active'
            ]);
        }

        // 6. تحديد أيام الأسبوع الحالي (الأحد إلى الخميس)
        $startOfWeek = Carbon::today()->startOfWeek(Carbon::SUNDAY);
        $weekDays = [];
        for ($i = 0; $i < 5; $i++) {
            $weekDays[] = $startOfWeek->copy()->addDays($i);
        }

        $todayStr = Carbon::today()->toDateString();

        foreach ($weekDays as $dayCarbon) {
            $dateStr = $dayCarbon->toDateString();

            if ($dateStr === $todayStr) {
                $tripStatus  = 'started';
                $startedAt   = $dayCarbon->copy()->setTime(7, 10, 0);
                $completedAt = null;
            } elseif ($dateStr < $todayStr) {
                $tripStatus  = 'completed';
                $startedAt   = $dayCarbon->copy()->setTime(7, 5, 0);
                $completedAt = $dayCarbon->copy()->setTime(7, 45, 0);
            } else {
                $tripStatus  = 'planned';
                $startedAt   = null;
                $completedAt = null;
            }

            // إنشاء أو تحديث سجل الرحلة
            $trip = Trip::updateOrCreate(
                [
                    'driver_id' => $driver->id,
                    'trip_date' => $dateStr,
                    'trip_type' => 'Morning',
                ],
                [
                    'route_id'             => $route->id,
                    'scheduled_at'         => $dayCarbon->copy()->setTime(7, 0, 0),
                    'started_at'           => $startedAt,
                    'completed_at'         => $completedAt,
                    'status'               => $tripStatus,
                    'scheduled_start_time' => $dayCarbon->copy()->setTime(7, 0, 0),
                    'actual_start_time'    => $startedAt,
                    'driver_attendance'    => 1,
                    'created_at'           => now(),
                ]
            );

            // ربط حضور الأبناء والأحداث والتتبع لكل طفل (1, 2, 3)
            foreach ($children as $child) {
                $subRec = ActiveSubscription::where('child_id', $child->id)->where('driver_id', $driver->id)->first();

                // 1. تسجيل الحضور
                DB::table('trip_student_attendance')->updateOrInsert(
                    ['trip_id' => $trip->id, 'child_id' => $child->id],
                    ['attendance_status' => 'present', 'updated_at' => now(), 'created_at' => now()]
                );

                // 2. إيقاع حدث صعود الطفل للحافلة للرحلات القائمة والمكتملة
                if ($tripStatus !== 'planned' && $subRec) {
                    DB::table('trip_events')->updateOrInsert(
                        ['trip_id' => $trip->id, 'child_id' => $child->id],
                        [
                            'subscription_id' => $subRec->id,
                            'action_type'     => 'picked_up',
                            'trip_type'       => 'ذهاب',
                            'location_lat'    => 32.89200000,
                            'location_lng'    => 13.17500000,
                            'scanned_at'      => $startedAt ? $startedAt->copy()->addMinutes(5) : now(),
                            'trip_cost'       => 15.00
                        ]
                    );
                }
            }

            // 3. إدخال إحداثيات التتبع الجغرافي الحي للرحلة النشطة والمكتملة
            if ($tripStatus !== 'planned') {
                DB::table('trip_tracking')->insert([
                    'trip_id'     => $trip->id,
                    'latitude'    => 32.89250000,
                    'longitude'   => 13.17450000,
                    'speed'       => 38.0,
                    'accuracy'    => 4.0,
                    'recorded_at' => now()
                ]);
            }

            echo "✅ يوم {$dateStr}: تم إنشاء الرحلة (Status: {$tripStatus} - ID: {$trip->id}) للأطفال 1, 2, 3 بنجاح!\n";
        }

        echo "🎉 اكتمل زرع الرحلات النشطة لجميع أطفال طه القمودي (IDs: 1, 2, 3) طوال الأسبوع بنجاح تام!\n";
    }
}
