<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestingDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ط¥ظ†ط´ط§ط، ط³ط§ط¦ظ‚ظٹظ† ظ…ط¹ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ط±ظƒط¨ط§طھ ط§ظ„ظ…ط·ظ„ظˆط¨ط©
        $drivers = [
            [
                'full_name' => 'ط³ط§ط¦ظ‚ ظ…ظƒظٹظپ (ظ…ط«ط§ظ„ظٹ)', 
                'phone_number' => '0910000001', 
                'gender' => 'male', 
                'accepted_gender' => 'both', 
                'subscription_type' => 'both', 
                'has_ac' => 1, // tinyint
                'zone_id' => 1 // طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ zone ط¨ظ‡ط°ط§ ط§ظ„ظ€ ID
            ]
        ];

        foreach ($drivers as $d) {
            $userId = DB::table('users')->insertGetId([
                'full_name'    => $d['full_name'],
                'email'        => 'driver' . rand(100, 999) . '@test.com',
                'phone_number' => $d['phone_number'],
                'password_hash'=> Hash::make('12345678'),
                'role_id'      => 2,
                'is_active'    => 1,
                'created_at'   => now()
            ]);

            $driverId = DB::table('drivers')->insertGetId([
                'user_id'           => $userId, 
                'status'            => 'Approved',
                'subscription_type' => $d['subscription_type'], 
                'accepted_gender'   => $d['accepted_gender'],
                'gender'            => $d['gender'],
                'created_at'        => now()
            ]);

            // ط¥ط¯ط±ط§ط¬ ط§ظ„ط³ظٹط§ط±ط© ظ…ط¹ ط§ظ„ط­ظ‚ظˆظ„ ط§ظ„ط¥ظ„ط²ط§ظ…ظٹط© (plate_number, brand, model, etc)
            DB::table('vehicles')->insert([
                'driver_id'    => $driverId, 
                'plate_number' => 'LY-' . rand(1000, 9999),
                'brand'        => 'Toyota',
                'model'        => 'Hiace',
                'year'         => '2025',
                'color'        => 'White',
                'type'         => 'Bus',
                'capacity_manual' => 12,
                'is_verified'  => 1,
                'has_ac'       => $d['has_ac'],
                'status'       => 'Active',
                'created_at'   => now()
            ]);

            DB::table('driver_zone')->insert([
                'driver_id' => $driverId, 
                'zone_id'   => $d['zone_id'],
                'created_at' => now()
            ]);
        }

        // 2. ط¥ظ†ط´ط§ط، ط·ظپظ„ ظ„ظ„ط§ط®طھط¨ط§ط±
        $childId = DB::table('children')->insertGetId([
            'parent_id' => 1, 
            'full_name' => 'ظ…ط­ظ…ط¯ ط£ط­ظ…ط¯', 
            'birth_date' => '2015-05-05',
            'gender'    => 'male',
            'grade'     => 3,
            'notification_radius' => 500,
            'qr_code_token' => 'test_token_123',
            'created_at' => now()
        ]);

        DB::table('child_logistics')->insert([
            'child_id' => $childId, 
            'preferred_time_slot' => 'morning',
            'trip_direction' => 'both',
            'is_active' => 1,
            'subscription_type' => 'multi_day',
            'created_at' => now()
        ]);
    }
}