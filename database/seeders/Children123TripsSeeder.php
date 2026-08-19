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
        echo "ًںڑ€ ط¨ط¯ط، ط¥ط¶ط§ظپط© ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظˆط§ظ„ط±ط­ظ„ط§طھ ط§ظ„ظ†ط´ط·ط© ظ„ط¬ظ…ظٹط¹ ط£ظٹط§ظ… ط§ظ„ط£ط³ط¨ظˆط¹ ظ„ظ„ط£ط·ظپط§ظ„ (IDs: 1, 2, 3)...\n";

        // 1. ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ط§ظ„ط£ط·ظپط§ظ„ 1, 2, 3
        $children = Child::whereIn('id', [1, 2, 3])->get();
        if ($children->isEmpty()) {
            echo "â‌Œ ط§ظ„ط£ط·ظپط§ظ„ ط؛ظٹط± ظ…ظˆط¬ظˆط¯ظٹظ† ظپظٹ ط§ظ„ط¯ط§طھط§ط¨ظٹط².\n";
            return;
        }

        // 2. ط¬ظ„ط¨ ط§ظ„ط³ط§ط¦ظ‚ (ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…طµط±ط§طھظٹ ID: 36 ط£ظˆ ط£ظˆظ„ ط³ط§ط¦ظ‚)
        $driver = Driver::find(36) ?? Driver::first();
        if (!$driver) {
            echo "â‌Œ ظ„ط§ ظٹظˆط¬ط¯ ط³ط§ط¦ظ‚ ظ„ط±ط¨ط· ط§ظ„ط£ط·ظپط§ظ„ ط¨ظ‡.\n";
            return;
        }

        // 3. ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط¹ظ‚ط¯ ظ„ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ (User ID: 93)
        $parentId = 93;
        $contract = Contract::where('parent_id', $parentId)->first();
        if (!$contract) {
            $contract = Contract::create([
                'subscription_request_id' => 1,
                'parent_id'               => $parentId,
                'driver_id'               => $driver->user_id ?? $driver->id,
                'contract_number'         => 'CNT-2026-CHILD123',
                'subscription_type' => 'multi_day',
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

        // 4. ط¥ظ†ط´ط§ط، ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط© ظپظٹ active_subscriptions ظ„ظƒظ„ ط·ظپظ„ (1, 2, 3)
        foreach ($children as $child) {
            ActiveSubscription::updateOrCreate(
                ['child_id' => $child->id, 'driver_id' => $driver->id],
                [
                    'contract_id'   => $contract->id,
                    'parent_id'     => $parentId,
                    'pickup_lat'    => 32.89200000,
                    'pickup_lng'    => 13.17500000,
                    'pickup_label'  => 'ظ…ظ†ط²ظ„ ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ - ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³',
                    'dropoff_lat'   => 32.89000000,
                    'dropoff_lng'   => 13.17000000,
                    'dropoff_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯ ط§ظ„ط¯ظˆظ„ظٹط©',
                    'pickup_time'   => '07:00:00',
                    'dropoff_time'  => '14:00:00',
                    'status'        => 'active'
                ]
            );
        }

        // 5. ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ظ…ط±ظƒط¨ط© ظˆظ…ط³ط§ط± ظ„ظ„ط³ط§ط¦ظ‚
        $vehicleId = DB::table('vehicles')->where('driver_id', $driver->id)->value('id');
        if (!$vehicleId) {
            $vehicleId = DB::table('vehicles')->insertGetId([
                'driver_id'       => $driver->id,
                'plate_number'    => '5-99887',
                'brand'           => 'طھظˆظٹظˆطھط§',
                'model'           => 'ظƒظˆط³طھط±',
                'year'            => 2023,
                'color'           => 'ط£ط¨ظٹط¶',
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
                'route_name'         => 'ظ…ط³ط§ط± ط£ط·ظپط§ظ„ ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ - ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³',
                'route_type'         => 'Morning',
                'start_time'         => '07:00:00',
                'estimated_duration' => 40,
                'status'             => 'Active'
            ]);
        }

        // 6. طھط­ط¯ظٹط¯ ط£ظٹط§ظ… ط§ظ„ط£ط³ط¨ظˆط¹ ط§ظ„ط­ط§ظ„ظٹ (ط§ظ„ط£ط­ط¯ ط¥ظ„ظ‰ ط§ظ„ط®ظ…ظٹط³)
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

            // ط¥ظ†ط´ط§ط، ط£ظˆ طھط­ط¯ظٹط« ط³ط¬ظ„ ط§ظ„ط±ط­ظ„ط©
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

            // ط±ط¨ط· ط­ط¶ظˆط± ط§ظ„ط£ط¨ظ†ط§ط، ظˆط§ظ„ط£ط­ط¯ط§ط« ظˆط§ظ„طھطھط¨ط¹ ظ„ظƒظ„ ط·ظپظ„ (1, 2, 3)
            foreach ($children as $child) {
                $subRec = ActiveSubscription::where('child_id', $child->id)->where('driver_id', $driver->id)->first();

                // 1. طھط³ط¬ظٹظ„ ط§ظ„ط­ط¶ظˆط±
                DB::table('trip_student_attendance')->updateOrInsert(
                    ['trip_id' => $trip->id, 'child_id' => $child->id],
                    ['attendance_status' => 'present', 'updated_at' => now(), 'created_at' => now()]
                );

                // 2. ط¥ظٹظ‚ط§ط¹ ط­ط¯ط« طµط¹ظˆط¯ ط§ظ„ط·ظپظ„ ظ„ظ„ط­ط§ظپظ„ط© ظ„ظ„ط±ط­ظ„ط§طھ ط§ظ„ظ‚ط§ط¦ظ…ط© ظˆط§ظ„ظ…ظƒطھظ…ظ„ط©
                if ($tripStatus !== 'planned' && $subRec) {
                    DB::table('trip_events')->updateOrInsert(
                        ['trip_id' => $trip->id, 'child_id' => $child->id],
                        [
                            'subscription_id' => $subRec->id,
                            'action_type'     => 'picked_up',
                            'trip_type'       => 'ط°ظ‡ط§ط¨',
                            'location_lat'    => 32.89200000,
                            'location_lng'    => 13.17500000,
                            'scanned_at'      => $startedAt ? $startedAt->copy()->addMinutes(5) : now(),
                            'trip_cost'       => 15.00
                        ]
                    );
                }
            }

            // 3. ط¥ط¯ط®ط§ظ„ ط¥ط­ط¯ط§ط«ظٹط§طھ ط§ظ„طھطھط¨ط¹ ط§ظ„ط¬ط؛ط±ط§ظپظٹ ط§ظ„ط­ظٹ ظ„ظ„ط±ط­ظ„ط© ط§ظ„ظ†ط´ط·ط© ظˆط§ظ„ظ…ظƒطھظ…ظ„ط©
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

            echo "âœ… ظٹظˆظ… {$dateStr}: طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ط±ط­ظ„ط© (Status: {$tripStatus} - ID: {$trip->id}) ظ„ظ„ط£ط·ظپط§ظ„ 1, 2, 3 ط¨ظ†ط¬ط§ط­!\n";
        }

        echo "ًںژ‰ ط§ظƒطھظ…ظ„ ط²ط±ط¹ ط§ظ„ط±ط­ظ„ط§طھ ط§ظ„ظ†ط´ط·ط© ظ„ط¬ظ…ظٹط¹ ط£ط·ظپط§ظ„ ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ (IDs: 1, 2, 3) ط·ظˆط§ظ„ ط§ظ„ط£ط³ط¨ظˆط¹ ط¨ظ†ط¬ط§ط­ طھط§ظ…!\n";
    }
}
