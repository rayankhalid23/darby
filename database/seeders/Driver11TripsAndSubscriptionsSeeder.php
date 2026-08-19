<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver\Driver;
use App\Models\Driver\DriverSeatSlot;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\School;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\Route as RouteModel;
use App\Models\Shared\RouteStop;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStop;
use App\Models\Shared\TripEvent;
use App\Models\Shared\TripTracking;
use App\Models\Shared\AbsenceLog;
use App\Models\Driver\DriverAbsence;
use App\Enums\driver\DriverShift;

class Driver11TripsAndSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        echo "ًںڑ€ ط¨ط¯ط، ط¥ظ†ط´ط§ط، ظˆط²ط±ط¹ ط¨ظٹط§ظ†ط§طھ ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط§ظ„ط±ط­ظ„ط§طھ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظ„ظ„ط³ط§ط¦ظ‚ user_id = 11...\n";

        // =========================================================================
        // 0. ط§ظ„طھط£ظƒط¯ ظ…ظ† ظˆط¬ظˆط¯ ط§ظ„ط£ط¯ظˆط§ط± ط§ظ„ط£ط³ط§ط³ظٹط© ظپظٹ ط§ظ„ظ†ط¸ط§ظ… (Roles)
        // =========================================================================
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'super_admin', 'display_name' => 'ط³ظˆط¨ط± ط£ط¯ظ…ظ†'],
            ['id' => 2, 'name' => 'admin',       'display_name' => 'ظ…ط´ط±ظپ ط§ظ„ظ†ط¸ط§ظ…'],
            ['id' => 3, 'name' => 'parent',      'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
            ['id' => 4, 'name' => 'driver',      'display_name' => 'ط³ط§ط¦ظ‚'],
        ]);

        // =========================================================================
        // 1. طھط¬ظ‡ظٹط² ط£ظˆ ط¥ظ†ط´ط§ط، ط­ط³ط§ط¨ ط§ظ„ظ…ط³طھط®ط¯ظ… ظ„ظ„ط³ط§ط¦ظ‚ user_id = 11 (User & Driver)
        // =========================================================================
        // طھط­ط±ظٹط± ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ ظˆط§ظ„ط¨ط±ظٹط¯ ظ…ظ† ط£ظٹ ط­ط³ط§ط¨ط§طھ ط£ط®ط±ظ‰ ظ„طھظپط§ط¯ظٹ Duplicate Entry
        DB::table('users')->where('phone_number', '0911111111')->where('id', '!=', 11)->update(['phone_number' => '0910000011']);
        DB::table('users')->where('email', 'driver11@darby.ly')->where('id', '!=', 11)->update(['email' => 'driver11_old@darby.ly']);

        $driverUser = User::find(11);

        if (!$driverUser) {
            DB::table('users')->insert([
                'id'                => 11,
                'full_name'         => 'ط§ظ„ظƒط§ط¨طھظ† ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…ظ‡ط¯ظˆظٹ',
                'email'             => 'driver11@darby.ly',
                'phone_number'      => '0911111111',
                'password_hash'     => Hash::make('password123'),
                'role_id'           => 4,
                'is_active'         => 1,
                'phone_verified'    => 1,
                'phone_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $driverUser = User::find(11);
            echo "âœ… طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط³طھط®ط¯ظ… ط§ظ„ظ…ط·ظ„ظˆط¨ user_id = 11 (ط§ظ„ظƒط§ط¨طھظ† ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…ظ‡ط¯ظˆظٹ)\n";
        } else {
            $driverUser->update([
                'full_name'    => 'ط§ظ„ظƒط§ط¨طھظ† ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…ظ‡ط¯ظˆظٹ',
                'email'        => 'driver11@darby.ly',
                'phone_number' => '0911111111',
                'is_active'    => 1,
            ]);
            echo "â„¹ï¸ڈ طھظ… ط§ظ„طھط­ط¯ظٹط« ط¹ظ„ظ‰ ط­ط³ط§ط¨ ط§ظ„ظ…ط³طھط®ط¯ظ… ط§ظ„ظ…ظˆط¬ظˆط¯ user_id = 11\n";
        }

        // ط¥ظ†ط´ط§ط، ط£ظˆ طھط­ط¯ظٹط« ط³ط¬ظ„ ط§ظ„ط³ط§ط¦ظ‚ (Driver Profile)
        $driver = Driver::where('user_id', 11)->first();

        if (!$driver) {
            $driver = Driver::create([
                'user_id'           => 11,
                'national_id'       => '119900887766',
                'license_number'    => 'LIC-11009988',
                'license_expiry'    => Carbon::now()->addYears(3)->toDateString(),
                'status'            => 'Approved',
                'subscription_type' => 'both',
                'accepted_gender'   => 'both',
                'gender'            => 'male',
                'shift'             => DriverShift::BOTH,
                'morning_go'        => true,
                'morning_return'    => true,
                'afternoon_go'      => true,
                'afternoon_return'  => true,
                'current_lat'       => 32.88720000,
                'current_lng'       => 13.17130000,
                'last_ping_at'      => $now,
            ]);
        } else {
            $driver->update([
                'status'            => 'Approved',
                'morning_go'        => true,
                'morning_return'    => true,
                'afternoon_go'      => true,
                'afternoon_return'  => true,
                'current_lat'       => 32.88720000,
                'current_lng'       => 13.17130000,
                'last_ping_at'      => $now,
            ]);
        }

        $driverId = $driver->id;
        echo "âœ… ط§ظ„ط³ط§ط¦ظ‚ ط¬ط§ظ‡ط² (Driver ID: {$driverId}, User ID: 11)\n";

        // =========================================================================
        // 2. ط¥ظ†ط´ط§ط، ظ…ط±ظƒط¨ط© ط§ظ„ط³ط§ط¦ظ‚ ظˆط­ط¬ظˆط²ط§طھ ط§ظ„ظ…ظ‚ط§ط¹ط¯ (Vehicle & Seat Slots)
        // =========================================================================
        $vehicleId = DB::table('vehicles')->where('driver_id', $driverId)->value('id');
        if (!$vehicleId) {
            $vehicleId = DB::table('vehicles')->insertGetId([
                'driver_id'       => $driverId,
                'plate_number'    => '5-88111',
                'brand'           => 'طھظˆظٹظˆطھط§',
                'model'           => 'ظ‡ط§ظٹط³ طھظˆظٹظ† ظƒط§ط¨ظٹظ†ط©',
                'year'            => '2023',
                'color'           => 'ط£ط¨ظٹط¶ ظ…ظ„ظƒظٹ',
                'type'            => 'Van',
                'capacity_manual' => 14,
                'is_verified'     => 1,
                'has_ac'          => 1,
                'status'          => 'Active',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // ط¶ط¨ط· ط­ط¬ظˆط²ط§طھ ط§ظ„ظ…ظ‚ط§ط¹ط¯ ظ„ظƒظ„ ط§ظ„ظپطھط±ط©
        $seatSlots = [
            DriverSeatSlot::MORNING_GO       => ['total' => 14, 'reserved' => 5],
            DriverSeatSlot::MORNING_RETURN   => ['total' => 14, 'reserved' => 5],
            DriverSeatSlot::AFTERNOON_GO     => ['total' => 14, 'reserved' => 3],
            DriverSeatSlot::AFTERNOON_RETURN => ['total' => 14, 'reserved' => 3],
        ];

        foreach ($seatSlots as $slotKey => $counts) {
            DriverSeatSlot::updateOrCreate(
                ['driver_id' => $driverId, 'slot' => $slotKey],
                ['total_seats' => $counts['total'], 'reserved_seats' => $counts['reserved']]
            );
        }

        // =========================================================================
        // 3. ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط¯ط§ط±ط³ ط§ظ„ظˆط§ظ‚ط¹ظٹط© ظپظٹ ط·ط±ط§ط¨ظ„ط³ (Schools)
        // =========================================================================
        $schoolsSpecs = [
            'school_1' => [
                'name'    => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯ ط§ظ„ط¯ظˆظ„ظٹط© (ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³)',
                'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط¬ط§ظ…ط¹ ط§ظ„ط£ظ†ط¯ظ„ط³',
                'lat'     => 32.89000000,
                'lng'     => 13.17000000,
            ],
            'school_2' => [
                'name'    => 'ظ…ط¯ط±ط³ط© ط§ظ„ظ‚ط¯ط³ ط§ظ„ظ†ظ…ظˆط°ط¬ظٹط© (ط´ط§ط±ط¹ ط¯ظ…ط´ظ‚)',
                'address' => 'ط´ط§ط±ط¹ ط¯ظ…ط´ظ‚ - ط·ط±ط§ط¨ظ„ط³',
                'lat'     => 32.86500000,
                'lng'     => 13.19000000,
            ],
            'school_3' => [
                'name'    => 'ظ…ط¯ط±ط³ط© ط§ظ„ظ†ظˆط§ط© ط§ظ„ط£ظˆظ„ظ‰ (ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط©)',
                'address' => 'ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط© - ظ‚ط±ط¨ ظ…ط±ظƒط² ط§ظ„ط¨ط±ظٹط¯',
                'lat'     => 32.89500000,
                'lng'     => 13.22000000,
            ],
            'school_4' => [
                'name'    => 'ظ…ط¯ط±ط³ط© ظ„ظٹط¨ظٹط§ ط§ظ„ط­ط¯ظٹط«ط© (ط¹ظٹظ† ط²ط§ط±ط©)',
                'address' => 'ط¹ظٹظ† ط²ط§ط±ط© - ظ‚ط±ط¨ ط§ظ„ط·ط±ظ‚ ط§ظ„ط¯ط§ط¦ط±ظٹ',
                'lat'     => 32.83500000,
                'lng'     => 13.25000000,
            ],
        ];

        $schools = [];
        foreach ($schoolsSpecs as $key => $spec) {
            $sc = School::where('name', $spec['name'])->first();
            if (!$sc) {
                $sc = School::create([
                    'name'    => $spec['name'],
                    'address' => $spec['address'],
                    'lat'     => $spec['lat'],
                    'lng'     => $spec['lng'],
                    'status'  => 'approved',
                ]);
            }
            $schools[$key] = $sc;
        }

        // =========================================================================
        // 4. ط¥ظ†ط´ط§ط، ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ظˆط§ظ„ط£ط·ظپط§ظ„ (Parents & Children)
        // =========================================================================
        $parentsData = [
            [
                'email'     => 'parent11_1@darby.ly',
                'name'      => 'ط¹ظ„ظٹ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„ط²ظˆظٹ',
                'phone'     => '0912220011',
                'children'  => [
                    [
                        'name'          => 'ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ',
                        'gender'        => 'male',
                        'grade'         => 4,
                        'school_id'     => $schools['school_1']->id,
                        'qr_token'      => 'QR_CHILD_1101_TAREQ',
                        'home_lat'      => 32.88750000,
                        'home_lng'      => 13.17200000,
                        'sub_scenario'  => 'active_full',
                    ],
                    [
                        'name'          => 'ط³ط§ط±ط© ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ',
                        'gender'        => 'female',
                        'grade'         => 2,
                        'school_id'     => $schools['school_1']->id,
                        'qr_token'      => 'QR_CHILD_1102_SARA',
                        'home_lat'      => 32.88750000,
                        'home_lng'      => 13.17200000,
                        'sub_scenario'  => 'active_full',
                    ],
                ]
            ],
            [
                'email'     => 'parent11_2@darby.ly',
                'name'      => 'ظ…ط­ظ…ط¯ ط§ظ„ط¨ط´ظٹط± ط§ظ„ظˆط±ظپظ„ظٹ',
                'phone'     => '0912220022',
                'children'  => [
                    [
                        'name'          => 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ',
                        'gender'        => 'male',
                        'grade'         => 5,
                        'school_id'     => $schools['school_1']->id,
                        'qr_token'      => 'QR_CHILD_1103_OMAR',
                        'home_lat'      => 32.87900000,
                        'home_lng'      => 13.15800000,
                        'sub_scenario'  => 'active_full',
                    ],
                ]
            ],
            [
                'email'     => 'parent11_3@darby.ly',
                'name'      => 'ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط¥ط¨ط±ط§ظ‡ظٹظ… ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                'phone'     => '0912220033',
                'children'  => [
                    [
                        'name'          => 'ط®ط¯ظٹط¬ط© ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                        'gender'        => 'female',
                        'grade'         => 3,
                        'school_id'     => $schools['school_2']->id,
                        'qr_token'      => 'QR_CHILD_1104_KHADIJA',
                        'home_lat'      => 32.89500000,
                        'home_lng'      => 13.22000000,
                        'sub_scenario'  => 'active_morning_only',
                    ],
                    [
                        'name'          => 'ط£ظ†ط³ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                        'gender'        => 'male',
                        'grade'         => 1,
                        'school_id'     => $schools['school_2']->id,
                        'qr_token'      => 'QR_CHILD_1105_ANAS',
                        'home_lat'      => 32.89500000,
                        'home_lng'      => 13.22000000,
                        'sub_scenario'  => 'active_morning_only',
                    ],
                ]
            ],
            [
                'email'     => 'parent11_4@darby.ly',
                'name'      => 'ط·ط§ط±ظ‚ ظ…طµط·ظپظ‰ ط§ظ„ظ‚ط±ط§ظ…ط§ظ†ظٹ',
                'phone'     => '0912220044',
                'children'  => [
                    [
                        'name'          => 'ظٹط§ط³ظ…ظٹظ† ط·ط§ط±ظ‚ ط§ظ„ظ‚ط±ط§ظ…ط§ظ†ظٹ',
                        'gender'        => 'female',
                        'grade'         => 6,
                        'school_id'     => $schools['school_3']->id,
                        'qr_token'      => 'QR_CHILD_1106_YASMEEN',
                        'home_lat'      => 32.83500000,
                        'home_lng'      => 13.25000000,
                        'sub_scenario'  => 'paused',
                    ],
                ]
            ],
            [
                'email'     => 'parent11_5@darby.ly',
                'name'      => 'ط¹ظ…ط± ظپط±ط¬ ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
                'phone'     => '0912220055',
                'children'  => [
                    [
                        'name'          => 'ظ…ط§ظ„ظƒ ط¹ظ…ط± ط§ظ„ظƒظٹظ„ط§ظ†ظٹ',
                        'gender'        => 'male',
                        'grade'         => 4,
                        'school_id'     => $schools['school_4']->id,
                        'qr_token'      => 'QR_CHILD_1107_MALIK',
                        'home_lat'      => 32.81800000,
                        'home_lng'      => 13.08500000,
                        'sub_scenario'  => 'expired',
                    ],
                ]
            ],
            [
                'email'     => 'parent11_6@darby.ly',
                'name'      => 'ط®ط§ظ„ط¯ ط³ط¹ط¯ ط§ظ„ظپظٹطھظˆط±ظٹ',
                'phone'     => '0912220066',
                'children'  => [
                    [
                        'name'          => 'ظپط§ط·ظ…ط© ط®ط§ظ„ط¯ ط§ظ„ظپظٹطھظˆط±ظٹ',
                        'gender'        => 'female',
                        'grade'         => 3,
                        'school_id'     => $schools['school_1']->id,
                        'qr_token'      => 'QR_CHILD_1108_FATIMA',
                        'home_lat'      => 32.86500000,
                        'home_lng'      => 13.19000000,
                        'sub_scenario'  => 'pending_request',
                    ],
                ]
            ],
        ];

        $createdChildren = [];

        foreach ($parentsData as $pData) {
            // طھط­ط±ظٹط± ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ ط¥ظ† ظƒط§ظ† ظ…ط³طھط®ط¯ظ…ط§ظ‹ ظ…ظ† ظ‚ط¨ظ„ ط­ط³ظ€ط§ط¨ ط¢ط®ط±
            DB::table('users')->where('phone_number', $pData['phone'])->where('email', '!=', $pData['email'])->update(['phone_number' => '091000' . rand(1000, 9999)]);

            $pUser = User::where('email', $pData['email'])->first();
            if (!$pUser) {
                $pUser = User::create([
                    'full_name'     => $pData['name'],
                    'email'         => $pData['email'],
                    'phone_number'  => $pData['phone'],
                    'password_hash' => Hash::make('password123'),
                    'role_id'       => 3,
                    'is_active'     => 1,
                ]);
            }

            $parentModel = ParentModel::firstOrCreate(
                ['user_id' => $pUser->id],
                ['is_trusted' => 1]
            );

            foreach ($pData['children'] as $cData) {
                $child = Child::where('parent_id', $parentModel->id)
                    ->where('full_name', $cData['name'])
                    ->first();

                if (!$child) {
                    $child = Child::create([
                        'parent_id'     => $parentModel->id,
                        'school_id'     => $cData['school_id'],
                        'full_name'     => $cData['name'],
                        'gender'        => $cData['gender'],
                        'birth_date'    => Carbon::now()->subYears(7 + $cData['grade'])->toDateString(),
                        'grade'         => $cData['grade'],
                        'qr_code_token' => $cData['qr_token'],
                    ]);
                } else {
                    $child->update(['qr_code_token' => $cData['qr_token']]);
                }

                $child->sub_scenario = $cData['sub_scenario'];
                $child->home_lat     = $cData['home_lat'];
                $child->home_lng     = $cData['home_lng'];

                $createdChildren[$cData['name']] = $child;
            }
        }

        echo "âœ… طھظ… ط²ط±ط¹ ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± ظˆط§ظ„ط£ط·ظپط§ظ„ ظˆط§ظ„ط±ظ…ظˆط² ط§ظ„ط³ط±ظٹط© QR ط¨ظ†ط¬ط§ط­!\n";

        // =========================================================================
        // 5. ط¥ظ†ط´ط§ط، ظˆطھظ†ظˆط¹ ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط§ظ„ط¹ظ‚ظˆط¯ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ (Contracts & Subscriptions)
        // =========================================================================
        
        // ط£) ط§ظ„ط¹ظ‚ظˆط¯ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط© ط§ظ„ظƒط§ظ…ظ„ط© (Active Full Subscriptions)
        foreach (['ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ', 'ط³ط§ط±ط© ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ', 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ'] as $childName) {
            $ch = $createdChildren[$childName];
            $parentModel = $ch->parent;

            $subReq = SubscriptionRequest::firstOrCreate(
                ['parent_id' => $parentModel->id, 'driver_id' => $driverId, 'status' => 'accepted'],
                [
                    'school_id'         => $ch->school_id,
                    'subscription_type' => 'multi_day',
                    'direction'         => 'both',
                    'timing'            => 'BOTH',
                    'start_date'        => Carbon::today()->startOfMonth()->toDateString(),
                    'end_date'          => Carbon::today()->endOfMonth()->toDateString(),
                    'days_count'        => 22,
                    'total_price'       => 350.00,
                    'children_count'    => 1,
                ]
            );

            $contract = Contract::firstOrCreate(
                ['subscription_request_id' => $subReq->id],
                [
                    'parent_id'         => $parentModel->id,
                    'driver_id'         => $driverUser->id,
                    'contract_number'   => Contract::generateContractNumber(),
                    'subscription_type' => 'multi_day',
                    'direction'         => 'both',
                    'timing'            => 'BOTH',
                    'start_date'        => Carbon::today()->startOfMonth()->toDateString(),
                    'end_date'          => Carbon::today()->endOfMonth()->toDateString(),
                    'days_count'        => 22,
                    'total_price'       => 350.00,
                    'pickup_time'       => '07:00:00',
                    'dropoff_time'      => '13:30:00',
                    'status'            => 'active',
                    'max_waiting_time'  => 5,
                    'signed_at'         => $now,
                ]
            );

            ActiveSubscription::updateOrCreate(
                ['child_id' => $ch->id, 'driver_id' => $driverId],
                [
                    'contract_id'   => $contract->id,
                    'parent_id'     => $parentModel->user_id,
                    'pickup_lat'    => $ch->home_lat,
                    'pickup_lng'    => $ch->home_lng,
                    'pickup_label'  => 'ط§ظ„ظ…ظ†ط²ظ„ - ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ / ط§ظ„ط³ظٹط§ط­ظٹط©',
                    'dropoff_lat'   => $ch->school->lat,
                    'dropoff_lng'   => $ch->school->lng,
                    'dropoff_label' => $ch->school->name,
                    'pickup_time'   => '07:00:00',
                    'dropoff_time'  => '13:30:00',
                    'status'        => 'active',
                ]
            );
        }

        // ط¨) ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„طµط¨ط§ط­ظٹط© ظپظ‚ط· (Active Morning Only)
        foreach (['ط®ط¯ظٹط¬ط© ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ', 'ط£ظ†ط³ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ'] as $childName) {
            $ch = $createdChildren[$childName];
            $parentModel = $ch->parent;

            $subReq = SubscriptionRequest::firstOrCreate(
                ['parent_id' => $parentModel->id, 'driver_id' => $driverId, 'direction' => 'go', 'status' => 'accepted'],
                [
                    'school_id'         => $ch->school_id,
                    'subscription_type' => 'multi_day',
                    'direction'         => 'go',
                    'timing'            => 'MORNING',
                    'start_date'        => Carbon::today()->startOfMonth()->toDateString(),
                    'end_date'          => Carbon::today()->endOfMonth()->toDateString(),
                    'days_count'        => 22,
                    'total_price'       => 200.00,
                    'children_count'    => 1,
                ]
            );

            $contract = Contract::firstOrCreate(
                ['subscription_request_id' => $subReq->id],
                [
                    'parent_id'         => $parentModel->id,
                    'driver_id'         => $driverUser->id,
                    'contract_number'   => Contract::generateContractNumber(),
                    'subscription_type' => 'multi_day',
                    'direction'         => 'go',
                    'timing'            => 'MORNING',
                    'start_date'        => Carbon::today()->startOfMonth()->toDateString(),
                    'end_date'          => Carbon::today()->endOfMonth()->toDateString(),
                    'days_count'        => 22,
                    'total_price'       => 200.00,
                    'pickup_time'       => '07:15:00',
                    'dropoff_time'      => '07:45:00',
                    'status'            => 'active',
                    'max_waiting_time'  => 5,
                    'signed_at'         => $now,
                ]
            );

            ActiveSubscription::updateOrCreate(
                ['child_id' => $ch->id, 'driver_id' => $driverId],
                [
                    'contract_id'   => $contract->id,
                    'parent_id'     => $parentModel->user_id,
                    'pickup_lat'    => $ch->home_lat,
                    'pickup_lng'    => $ch->home_lng,
                    'pickup_label'  => 'ط§ظ„ظ…ظ†ط²ظ„ - ط³ظˆظ‚ ط§ظ„ط¬ظ…ط¹ط©',
                    'dropoff_lat'   => $ch->school->lat,
                    'dropoff_lng'   => $ch->school->lng,
                    'dropoff_label' => $ch->school->name,
                    'pickup_time'   => '07:15:00',
                    'dropoff_time'  => '07:45:00',
                    'status'        => 'active',
                ]
            );
        }

        // ط¬) ط§ط´طھط±ط§ظƒ ظ…ظ„ط؛ظ‰ / ظ…ظˆظ‚ظپ (Cancelled Subscription)
        $chPaused = $createdChildren['ظٹط§ط³ظ…ظٹظ† ط·ط§ط±ظ‚ ط§ظ„ظ‚ط±ط§ظ…ط§ظ†ظٹ'];
        $subReqPaused = SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chPaused->parent->id, 'driver_id' => $driverId, 'status' => 'cancelled'],
            [
                'school_id'         => $chPaused->school_id,
                'subscription_type' => 'multi_day',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::today()->subMonth()->toDateString(),
                'end_date'          => Carbon::today()->toDateString(),
                'days_count'        => 20,
                'total_price'       => 300.00,
                'children_count'    => 1,
            ]
        );
        $contractPaused = Contract::firstOrCreate(
            ['subscription_request_id' => $subReqPaused->id],
            [
                'parent_id'         => $chPaused->parent->id,
                'driver_id'         => $driverUser->id,
                'contract_number'   => Contract::generateContractNumber(),
                'subscription_type' => 'multi_day',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::today()->subMonth()->toDateString(),
                'end_date'          => Carbon::today()->toDateString(),
                'days_count'        => 20,
                'total_price'       => 300.00,
                'pickup_time'       => '07:00:00',
                'dropoff_time'      => '13:30:00',
                'status'            => 'terminated',
                'max_waiting_time'  => 5,
                'signed_at'         => $now,
            ]
        );
        ActiveSubscription::updateOrCreate(
            ['child_id' => $chPaused->id, 'driver_id' => $driverId],
            [
                'contract_id'   => $contractPaused->id,
                'parent_id'     => $chPaused->parent->user_id,
                'pickup_lat'    => $chPaused->home_lat,
                'pickup_lng'    => $chPaused->home_lng,
                'pickup_label'  => 'ط§ظ„ظ…ظ†ط²ظ„ - ط¹ظٹظ† ط²ط§ط±ط©',
                'dropoff_lat'   => $chPaused->school->lat,
                'dropoff_lng'   => $chPaused->school->lng,
                'dropoff_label' => $chPaused->school->name,
                'status'        => 'cancelled',
            ]
        );

        // ط¯) ط§ط´طھط±ط§ظƒ ظ…ظƒطھظ…ظ„ / ظ…ظ†طھظ‡ظٹ ط§ظ„طµظ„ط§ط­ظٹط© (Completed Subscription)
        $chExpired = $createdChildren['ظ…ط§ظ„ظƒ ط¹ظ…ط± ط§ظ„ظƒظٹظ„ط§ظ†ظٹ'];
        $subReqExpired = SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chExpired->parent->id, 'driver_id' => $driverId, 'status' => 'accepted'],
            [
                'school_id'         => $chExpired->school_id,
                'subscription_type' => 'multi_day',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::today()->subMonths(2)->toDateString(),
                'end_date'          => Carbon::today()->subMonth()->toDateString(),
                'days_count'        => 22,
                'total_price'       => 350.00,
                'children_count'    => 1,
            ]
        );
        $contractExpired = Contract::firstOrCreate(
            ['subscription_request_id' => $subReqExpired->id],
            [
                'parent_id'         => $chExpired->parent->id,
                'driver_id'         => $driverUser->id,
                'contract_number'   => Contract::generateContractNumber(),
                'subscription_type' => 'multi_day',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::today()->subMonths(2)->toDateString(),
                'end_date'          => Carbon::today()->subMonth()->toDateString(),
                'days_count'        => 22,
                'total_price'       => 350.00,
                'pickup_time'       => '07:00:00',
                'dropoff_time'      => '13:30:00',
                'status'            => 'terminated',
                'max_waiting_time'  => 5,
                'signed_at'         => $now,
            ]
        );
        ActiveSubscription::updateOrCreate(
            ['child_id' => $chExpired->id, 'driver_id' => $driverId],
            [
                'contract_id'   => $contractExpired->id,
                'parent_id'     => $chExpired->parent->user_id,
                'pickup_lat'    => $chExpired->home_lat,
                'pickup_lng'    => $chExpired->home_lng,
                'pickup_label'  => 'ط§ظ„ظ…ظ†ط²ظ„ - ط¬ظ†ط²ظˆط±',
                'dropoff_lat'   => $chExpired->school->lat,
                'dropoff_lng'   => $chExpired->school->lng,
                'dropoff_label' => $chExpired->school->name,
                'status'        => 'completed',
            ]
        );

        // ظ‡ظ€) ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط± (Pending Request)
        $chPending = $createdChildren['ظپط§ط·ظ…ط© ط®ط§ظ„ط¯ ط§ظ„ظپظٹطھظˆط±ظٹ'];
        SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chPending->parent->id, 'driver_id' => $driverId, 'status' => 'pending'],
            [
                'school_id'         => $chPending->school_id,
                'subscription_type' => 'multi_day',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::tomorrow()->toDateString(),
                'end_date'          => Carbon::tomorrow()->addMonth()->toDateString(),
                'days_count'        => 20,
                'total_price'       => 320.00,
                'children_count'    => 1,
                'notes'             => 'ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ط¬ط¯ظٹط¯ ظ„ظ†ظ‡ط§ظٹط© ط§ظ„ظپطµظ„ ط§ظ„ط¯ط±ط§ط³ظٹ',
            ]
        );

        echo "âœ… طھظ… ط¥ط¯ط®ط§ظ„ ط¬ظ…ظٹط¹ ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط§ظ„ط§ط´طھط±ط§ظƒط§طھ (ظ†ط´ط· ظƒط§ظ…ظ„طŒ ظ†ط´ط· طµط¨ط§ط­ظٹطŒ ظ…ظˆظ‚ظپطŒ ظ…ظ†طھظ‡ظٹطŒ ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط±)\n";

        // =========================================================================
        // 6. ط¥ظ†ط´ط§ط، ط§ظ„ظ…ط³ط§ط±ط§طھ ط§ظ„ظ‡ظٹظƒظ„ظٹط© ط§ظ„ظ…ط±ط¬ط¹ظٹط© ظ„ظ„ط³ط§ط¦ظ‚ (Master Routes & RouteStops)
        // =========================================================================
        
        // ط§ظ„ظ…ط³ط§ط± 1: ط§ظ„ط°ظ‡ط§ط¨ ط§ظ„طµط¨ط§ط­ظٹ (Morning Go)
        $routeMorning = RouteModel::updateOrCreate(
            ['driver_id' => $driverId, 'route_type' => 'Morning', 'shift_slot' => DriverSeatSlot::MORNING_GO],
            [
                'vehicle_id'         => $vehicleId,
                'route_name'         => 'ظ…ط³ط§ط± ط§ظ„ط°ظ‡ط§ط¨ ط§ظ„طµط¨ط§ط­ظٹ - ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ ظˆط§ظ„ط³ظٹط§ط­ظٹط© ط¥ظ„ظ‰ ط§ظ„ظ…ط¯ط§ط±ط³',
                'start_time'         => '07:00:00',
                'estimated_duration' => 45,
                'status'             => 'Active',
            ]
        );

        // ظ…ط³ط­ ظ…ط­ط·ط§طھ ط§ظ„ظ…ط³ط§ط± ط§ظ„ظ‚ط¯ظٹظ…ط© ظ„ط¥ط¹ط§ط¯ط© ط²ط±ط¹ظ‡ط§ ط¨ط§ظ†طھط¸ط§ظ…
        RouteStop::where('route_id', $routeMorning->id)->delete();

        $morningStops = [
            ['type' => 'home',   'child' => 'ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ',          'school' => null,                   'lat' => 32.8875, 'lng' => 13.1720, 'label' => 'ظ…ظ†ط²ظ„ ط¹ط§ط¦ظ„ط© ط§ظ„ط²ظˆظٹ', 'seq' => 1],
            ['type' => 'home',   'child' => 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ',        'school' => null,                   'lat' => 32.8790, 'lng' => 13.1580, 'label' => 'ظ…ظ†ط²ظ„ ط¹ط§ط¦ظ„ط© ط§ظ„ظˆط±ظپظ„ظٹ', 'seq' => 2],
            ['type' => 'home',   'child' => 'ط®ط¯ظٹط¬ط© ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',  'school' => null,                   'lat' => 32.8950, 'lng' => 13.2200, 'label' => 'ظ…ظ†ط²ظ„ ط¹ط§ط¦ظ„ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ', 'seq' => 3],
            ['type' => 'home',   'child' => 'ط£ظ†ط³ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',   'school' => null,                   'lat' => 32.8950, 'lng' => 13.2200, 'label' => 'ظ…ظ†ط²ظ„ ط¹ط§ط¦ظ„ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ', 'seq' => 4],
            ['type' => 'school', 'child' => null,                      'school' => $schools['school_1']->id, 'lat' => 32.8900, 'lng' => 13.1700, 'label' => $schools['school_1']->name, 'seq' => 5],
            ['type' => 'school', 'child' => null,                      'school' => $schools['school_2']->id, 'lat' => 32.8650, 'lng' => 13.1900, 'label' => $schools['school_2']->name, 'seq' => 6],
        ];

        $routeStopMap = [];
        foreach ($morningStops as $st) {
            $childObj = $st['child'] ? $createdChildren[$st['child']] : null;
            $rs = RouteStop::create([
                'route_id'       => $routeMorning->id,
                'stop_type'      => $st['type'],
                'child_id'       => $childObj?->id,
                'school_id'      => $st['school'],
                'lat'            => $st['lat'],
                'lng'            => $st['lng'],
                'label'          => $st['label'],
                'sequence_order' => $st['seq'],
            ]);
            if ($st['child']) {
                $routeStopMap[$st['child']] = $rs;
            }
        }

        // ط±ط¨ط· ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط© ط¨ظ‡ط°ط§ ط§ظ„ظ…ط³ط§ط±
        ActiveSubscription::where('driver_id', $driverId)->update(['route_id' => $routeMorning->id]);

        // ط§ظ„ظ…ط³ط§ط± 2: ط§ظ„ط¹ظˆط¯ط© ط§ظ„طµط¨ط§ط­ظٹط© (Morning Return)
        $routeReturn = RouteModel::updateOrCreate(
            ['driver_id' => $driverId, 'route_type' => 'Morning', 'shift_slot' => DriverSeatSlot::MORNING_RETURN],
            [
                'vehicle_id'         => $vehicleId,
                'route_name'         => 'ظ…ط³ط§ط± ط§ظ„ط¹ظˆط¯ط© ط§ظ„طµط¨ط§ط­ظٹ - ظ…ظ† ط§ظ„ظ…ط¯ط§ط±ط³ ط¥ظ„ظ‰ ط§ظ„ظ…ظ†ط§ط²ظ„',
                'start_time'         => '13:30:00',
                'estimated_duration' => 40,
                'status'             => 'Active',
            ]
        );

        echo "âœ… طھظ… ط¥ظ†ط´ط§ط، ظˆطھط­ط¯ظٹط« ط§ظ„ظ…ط³ط§ط±ط§طھ ط§ظ„ظ‡ظٹظƒظ„ظٹط© ظˆظ…ط­ط·ط§طھظ‡ط§ ط¨ظ†ط¬ط§ط­!\n";

        // =========================================================================
        // 7. ط²ط±ط¹ ط؛ظٹط§ط¨ ظ…ط¬ط¯ظˆظ„ ظ„ط·ظپظ„ ظˆظ„ظ„ط³ط§ط¦ظ‚ (Absence Scenarios)
        // =========================================================================
        
        // ط؛ظٹط§ط¨ ط§ظ„ط·ظپظ„ "ط£ظ†ط³ ط§ظ„طھط±ظ‡ظˆظ†ظٹ" ط§ظ„ظٹظˆظ… ظˆظ…ط³طھظ‚ط¨ظ„ط§ظ‹ ظ…ظ† ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
        $childAnas = $createdChildren['ط£ظ†ط³ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ'];
        AbsenceLog::updateOrCreate(
            ['child_id' => $childAnas->id, 'absence_date' => $today],
            [
                'absence_type' => AbsenceLog::TYPE_BOTH,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]
        );

        // ط؛ظٹط§ط¨ ط§ظ„ط³ط§ط¦ظ‚ ط¨ط¹ط¯ 3 ط£ظٹط§ظ… (Driver Absence)
        DriverAbsence::updateOrCreate(
            ['driver_id' => $driverId, 'absence_date' => Carbon::tomorrow()->addDays(3)->toDateString()]
        );

        // =========================================================================
        // 8. ط²ط±ط¹ ظƒط§ظپط© ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط§ظ„ط±ط­ظ„ط§طھ ط§ظ„طھط´ط؛ظٹظ„ظٹط© (Trip Scenarios)
        // =========================================================================

        // -------------------------------------------------------------------------
        // ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ ط§ظ„ط£ظˆظ„: ط±ط­ظ„ط© طµط¨ط§ط­ظٹط© ط¬ط§ط±ظٹط© ط­ط§ظ„ظٹط§ظ‹ ط§ظ„ظٹظˆظ… (Ongoing Active Trip: status = started)
        // -------------------------------------------------------------------------
        $tripOngoing = Trip::updateOrCreate(
            [
                'driver_id'  => $driverId,
                'route_id'   => $routeMorning->id,
                'trip_date'  => $today,
                'trip_type'  => 'Morning',
                'shift_slot' => DriverSeatSlot::MORNING_GO,
            ],
            [
                'status'               => 'in_progress',
                'scheduled_at'         => Carbon::today()->setTime(7, 0, 0),
                'started_at'           => Carbon::now()->subMinutes(25),
                'scheduled_start_time' => Carbon::today()->setTime(7, 0, 0),
                'actual_start_time'    => Carbon::now()->subMinutes(25),
                'start_lat'            => 32.88720000,
                'start_lng'            => 13.17130000,
                'created_at'           => $now,
            ]
        );

        // طھظ†ط¸ظٹظپ ط§ظ„ظ…ط­ط·ط§طھ ظˆط§ظ„ط£ط­ط¯ط§ط« ط§ظ„ظ‚ط¯ظٹظ…ط© ظ„ظ‡ط°ظ‡ ط§ظ„ط±ط­ظ„ط©
        TripStop::where('trip_id', $tripOngoing->id)->delete();
        TripEvent::where('trip_id', $tripOngoing->id)->delete();
        TripTracking::where('trip_id', $tripOngoing->id)->delete();

        // ظ…ط­ط·ط§طھ ط§ظ„ط±ط­ظ„ط© ط§ظ„ط¬ط§ط±ظٹط© ظ…ط¹ طھظ†ظˆط¹ ظƒط§ظ…ظ„ ظپظٹ ط§ظ„ط­ط§ظ„ط§طھ ط§ظ„ط¯ظ‚ظٹظ‚ط© (boarded, pending, skipped, absent_pre)
        $ongoingStopsData = [
            // ط·ط§ط±ظ‚ ط§ظ„ط²ظˆظٹ: طµط¹ط¯ ظ„ظ„ط­ط§ظپظ„ط© ظˆطھظ… ظ…ط³ط­ ط§ظ„ظ€ QR
            [
                'child'     => 'ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_BOARDED,
                'lat'       => 32.8875,
                'lng'       => 13.1720,
                'label'     => 'ظ…ظ†ط²ظ„ ط·ط§ط±ظ‚ ط§ظ„ط²ظˆظٹ (ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³)',
                'seq'       => 1,
                'eta'       => '07:08',
            ],
            // ط³ط§ط±ط© ط§ظ„ط²ظˆظٹ: طµط¹ط¯طھ ظ„ظ„ط­ط§ظپظ„ط© ط£ظٹط¶ط§ظ‹
            [
                'child'     => 'ط³ط§ط±ط© ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_BOARDED,
                'lat'       => 32.8875,
                'lng'       => 13.1720,
                'label'     => 'ظ…ظ†ط²ظ„ ط³ط§ط±ط© ط§ظ„ط²ظˆظٹ (ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³)',
                'seq'       => 2,
                'eta'       => '07:09',
            ],
            // ط¹ظ…ط± ط§ظ„ظˆط±ظپظ„ظٹ: ط§ظ„ظ…ط­ط·ط© ط§ظ„ظ‚ط§ط¯ظ…ط© ظ„ظ„ط³ط§ط¦ظ‚ (ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط±)
            [
                'child'     => 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_PENDING,
                'lat'       => 32.8790,
                'lng'       => 13.1580,
                'label'     => 'ظ…ظ†ط²ظ„ ط¹ظ…ط± ط§ظ„ظˆط±ظپظ„ظٹ (ط§ظ„ط³ظٹط§ط­ظٹط©) - ط§ظ„ظ…ط­ط·ط© ط§ظ„ظ‚ط§ط¯ظ…ط©',
                'seq'       => 3,
                'eta'       => '07:22',
            ],
            // ط®ط¯ظٹط¬ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ: طھظ… طھط®ط·ظٹ ظ…ط­ط·طھظ‡ط§ ظ„ط¹ط¯ظ… ط§ظ„ط§ط³طھط¬ط§ط¨ط© ط¹ظ†ط¯ ط§ظ„ط¨ط§ط¨
            [
                'child'     => 'ط®ط¯ظٹط¬ط© ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_SKIPPED_UNRESPONSIVE,
                'lat'       => 32.8950,
                'lng'       => 13.2200,
                'label'     => 'ظ…ظ†ط²ظ„ ط®ط¯ظٹط¬ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ (طھظ… ط§ظ„طھط®ط·ظٹ)',
                'seq'       => 4,
                'eta'       => '07:30',
            ],
            // ط£ظ†ط³ ط§ظ„طھط±ظ‡ظˆظ†ظٹ: ط؛ط§ط¦ط¨ ط¨ط¹ظ„ظ… ظ…ط³ط¨ظ‚ ظ…ظ† ظˆظ„ظٹ ط§ظ„ط£ظ…ط±
            [
                'child'     => 'ط£ظ†ط³ ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_ABSENT_PRE,
                'lat'       => 32.8950,
                'lng'       => 13.2200,
                'label'     => 'ظ…ظ†ط²ظ„ ط£ظ†ط³ ط§ظ„طھط±ظ‡ظˆظ†ظٹ (ط؛ط§ط¦ط¨ ظ…ط³ط¨ظ‚ط§ظ‹)',
                'seq'       => 0,
                'eta'       => null,
            ],
            // ظ…ط­ط·ط© ط§ظ„ظˆطµظˆظ„ ظ„ظ„ظ…ط¯ط±ط³ط© ط§ظ„ط£ظˆظ„ظ‰
            [
                'child'     => null,
                'school'    => $schools['school_1']->id,
                'type'      => TripStop::TYPE_SCHOOL,
                'status'    => TripStop::STATUS_PENDING,
                'lat'       => 32.8900,
                'lng'       => 13.1700,
                'label'     => $schools['school_1']->name,
                'seq'       => 5,
                'eta'       => '07:40',
            ],
            // ظ…ط­ط·ط© ط§ظ„ظˆطµظˆظ„ ظ„ظ„ظ…ط¯ط±ط³ط© ط§ظ„ط«ط§ظ†ظٹط©
            [
                'child'     => null,
                'school'    => $schools['school_2']->id,
                'type'      => TripStop::TYPE_SCHOOL,
                'status'    => TripStop::STATUS_PENDING,
                'lat'       => 32.8650,
                'lng'       => 13.1900,
                'label'     => $schools['school_2']->name,
                'seq'       => 6,
                'eta'       => '07:50',
            ],
        ];

        foreach ($ongoingStopsData as $sData) {
            $chObj = $sData['child'] ? $createdChildren[$sData['child']] : null;
            $rStop = $sData['child'] ? ($routeStopMap[$sData['child']] ?? null) : null;

            TripStop::create([
                'trip_id'        => $tripOngoing->id,
                'route_stop_id'  => $rStop?->id,
                'stop_type'      => $sData['type'],
                'child_id'       => $chObj?->id,
                'school_id'      => $sData['school'],
                'lat'            => $sData['lat'],
                'lng'            => $sData['lng'],
                'label'          => $sData['label'],
                'sequence_order' => $sData['seq'],
                'status'         => $sData['status'],
                'eta'            => $sData['eta'],
                'eta_minutes'    => $sData['seq'] > 0 ? ($sData['seq'] * 5) : null,
            ]);
        }

        // ط£ط­ط¯ط§ط« ط§ظ„ط±ط­ظ„ط© ط§ظ„ط¬ط§ط±ظٹط© (Trip Events: pickups, skip)
        $chTareq = $createdChildren['ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ'];
        $chSara  = $createdChildren['ط³ط§ط±ط© ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ'];
        $chKhad  = $createdChildren['ط®ط¯ظٹط¬ط© ط¹ط¨ط¯ ط§ظ„ظ„ظ‡ ط§ظ„طھط±ظ‡ظˆظ†ظٹ'];

        $subTareqId = ActiveSubscription::where('child_id', $chTareq->id)->value('id') ?? 1;
        $subSaraId  = ActiveSubscription::where('child_id', $chSara->id)->value('id') ?? 1;
        $subKhadId  = ActiveSubscription::where('child_id', $chKhad->id)->value('id') ?? 1;

        TripEvent::create([
            'trip_id'         => $tripOngoing->id,
            'child_id'        => $chTareq->id,
            'subscription_id' => $subTareqId,
            'action_type'     => 'picked_up',
            'trip_type'       => 'ط°ظ‡ط§ط¨',
            'location_lat'    => 32.88750000,
            'location_lng'    => 13.17200000,
            'scanned_at'      => Carbon::now()->subMinutes(15),
            'trip_cost'       => 15.00,
        ]);

        TripEvent::create([
            'trip_id'         => $tripOngoing->id,
            'child_id'        => $chSara->id,
            'subscription_id' => $subSaraId,
            'action_type'     => 'picked_up',
            'trip_type'       => 'ط°ظ‡ط§ط¨',
            'location_lat'    => 32.88750000,
            'location_lng'    => 13.17200000,
            'scanned_at'      => Carbon::now()->subMinutes(14),
            'trip_cost'       => 15.00,
        ]);

        TripEvent::create([
            'trip_id'         => $tripOngoing->id,
            'child_id'        => $chKhad->id,
            'subscription_id' => $subKhadId,
            'action_type'     => 'skipped',
            'trip_type'       => 'ط°ظ‡ط§ط¨',
            'location_lat'    => 32.89500000,
            'location_lng'    => 13.22000000,
            'scanned_at'      => Carbon::now()->subMinutes(5),
            'trip_cost'       => 0.00,
        ]);

        // ط³ط¬ظ„ط§طھ ط§ظ„طھطھط¨ط¹ ط§ظ„ط­ظٹ ط§ظ„ظ…ط¨ط§ط´ط± ط¨ط§ظ„ط­ط§ظپظ„ط© (Trip Live Tracking GPS)
        $trackingPoints = [
            ['lat' => 32.8872, 'lng' => 13.1713, 'speed' => 0.0,  'mins_ago' => 25],
            ['lat' => 32.8875, 'lng' => 13.1720, 'speed' => 12.5, 'mins_ago' => 15],
            ['lat' => 32.8820, 'lng' => 13.1650, 'speed' => 45.0, 'mins_ago' => 8],
            ['lat' => 32.8805, 'lng' => 13.1600, 'speed' => 38.0, 'mins_ago' => 2],
        ];

        foreach ($trackingPoints as $tp) {
            TripTracking::create([
                'trip_id'     => $tripOngoing->id,
                'latitude'    => $tp['lat'],
                'longitude'   => $tp['lng'],
                'speed'       => $tp['speed'],
                'accuracy'    => 5.0,
                'recorded_at' => Carbon::now()->subMinutes($tp['mins_ago']),
            ]);
        }

        echo "âœ… ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ 1: طھظ… ط¥ظٹظ‚ط§ط¹ ط±ط­ظ„ط© ط¬ط§ط±ظٹط© ط§ظ„ط¢ظ† (Trip ID: {$tripOngoing->id}) ظ…ط¹ طھطھط¨ط¹ ط­ظٹ ظˆظ…ط²ظٹط¬ ط­ظ‚ظٹظ‚ظٹ ظ…ظ† ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط§ط¨!\n";

        // -------------------------------------------------------------------------
        // ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ ط§ظ„ط«ط§ظ†ظٹ: ط±ط­ظ„ط© ظ…ظƒطھظ…ظ„ط© ط¨ظ†ط¬ط§ط­ ط§ظ„ظٹظˆظ… (Completed Trip Today: status = completed)
        // -------------------------------------------------------------------------
        $tripCompletedToday = Trip::create([
            'driver_id'            => $driverId,
            'route_id'             => $routeReturn->id,
            'trip_type'            => 'Morning',
            'shift_slot'           => DriverSeatSlot::MORNING_RETURN,
            'status'               => 'completed',
            'scheduled_at'         => Carbon::today()->setTime(12, 30, 0),
            'started_at'           => Carbon::now()->subHours(4),
            'completed_at'         => Carbon::now()->subHours(3)->addMinutes(15),
            'scheduled_start_time' => Carbon::today()->setTime(12, 30, 0),
            'actual_start_time'    => Carbon::now()->subHours(4),
            'trip_date'            => $today,
            'created_at'           => $now,
        ]);

        foreach (['ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ', 'ط³ط§ط±ط© ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ', 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ'] as $idx => $cName) {
            $chObj = $createdChildren[$cName];
            
            TripStop::create([
                'trip_id'        => $tripCompletedToday->id,
                'stop_type'      => TripStop::TYPE_HOME,
                'child_id'       => $chObj->id,
                'lat'            => $chObj->home_lat,
                'lng'            => $chObj->home_lng,
                'label'          => "ظ…ظ†ط²ظ„ {$chObj->full_name}",
                'sequence_order' => $idx + 1,
                'status'         => TripStop::STATUS_DELIVERED_HOME,
            ]);

            $subId = ActiveSubscription::where('child_id', $chObj->id)->value('id') ?? 1;

            TripEvent::create([
                'trip_id'         => $tripCompletedToday->id,
                'child_id'        => $chObj->id,
                'subscription_id' => $subId,
                'action_type'     => 'dropped_off',
                'trip_type'       => 'ط¹ظˆط¯ط©',
                'location_lat'    => $chObj->home_lat,
                'location_lng'    => $chObj->home_lng,
                'scanned_at'      => Carbon::now()->subHours(3)->addMinutes($idx * 10),
                'trip_cost'       => 15.00,
            ]);
        }

        echo "âœ… ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ 2: طھظ… ط¥ظٹظ‚ط§ط¹ ط±ط­ظ„ط© ظ…ظƒطھظ…ظ„ط© ط§ظ„ظٹظˆظ… ط¨ظ†ط¬ط§ط­ (Trip ID: {$tripCompletedToday->id})\n";

        // -------------------------------------------------------------------------
        // ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ ط§ظ„ط«ط§ظ„ط«: ط±ط­ظ„ط© ظ‚ط§ط¯ظ…ط©/ظ…ط¬ط¯ظˆظ„ط© ظ„ظ… طھط¨ط¯ط£ ط¨ط¹ط¯ (Pending Upcoming Trip: status = pending)
        // -------------------------------------------------------------------------
        $tripPendingUpcoming = Trip::create([
            'driver_id'            => $driverId,
            'route_id'             => $routeMorning->id,
            'trip_type'            => 'Afternoon',
            'shift_slot'           => DriverSeatSlot::AFTERNOON_GO,
            'status'               => 'pending',
            'scheduled_at'         => Carbon::today()->setTime(14, 0, 0),
            'scheduled_start_time' => Carbon::today()->setTime(14, 0, 0),
            'trip_date'            => $today,
            'created_at'           => $now,
        ]);

        foreach (['ط·ط§ط±ظ‚ ط¹ظ„ظٹ ط§ظ„ط²ظˆظٹ', 'ط¹ظ…ط± ظ…ط­ظ…ط¯ ط§ظ„ظˆط±ظپظ„ظٹ'] as $idx => $cName) {
            $chObj = $createdChildren[$cName];
            TripStop::create([
                'trip_id'        => $tripPendingUpcoming->id,
                'stop_type'      => TripStop::TYPE_HOME,
                'child_id'       => $chObj->id,
                'lat'            => $chObj->home_lat,
                'lng'            => $chObj->home_lng,
                'label'          => "ظ…ظ†ط²ظ„ {$chObj->full_name}",
                'sequence_order' => $idx + 1,
                'status'         => TripStop::STATUS_PENDING,
                'eta_minutes'    => ($idx + 1) * 10,
            ]);
        }

        echo "âœ… ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ 3: طھظ… ط¥ظٹظ‚ط§ط¹ ط±ط­ظ„ط© ظ‚ط§ط¯ظ…ط© ظ…ط¬ط¯ظˆظ„ط© ظ„ط§ط®طھط¨ط§ط± ط¨ط¯ط، ط§ظ„ط±ط­ظ„ط© (Trip ID: {$tripPendingUpcoming->id})\n";

        // -------------------------------------------------------------------------
        // ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ ط§ظ„ط±ط§ط¨ط¹: ط±ط­ظ„ط© طھط§ط±ظٹط®ظٹط© ظ…ظƒطھظ…ظ„ط© ط§ظ„ط¨ط§ط±ط­ط© (Historical Completed Trip Yesterday)
        // -------------------------------------------------------------------------
        $tripHistorical = Trip::create([
            'driver_id'            => $driverId,
            'route_id'             => $routeMorning->id,
            'trip_type'            => 'Morning',
            'shift_slot'           => DriverSeatSlot::MORNING_GO,
            'status'               => 'completed',
            'scheduled_at'         => Carbon::yesterday()->setTime(7, 0, 0),
            'started_at'           => Carbon::yesterday()->setTime(7, 5, 0),
            'completed_at'         => Carbon::yesterday()->setTime(7, 45, 0),
            'scheduled_start_time' => Carbon::yesterday()->setTime(7, 0, 0),
            'actual_start_time'    => Carbon::yesterday()->setTime(7, 5, 0),
            'trip_date'            => $yesterday,
            'created_at'           => Carbon::yesterday(),
        ]);

        echo "âœ… ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ 4: طھظ… ط¥ظٹظ‚ط§ط¹ ط±ط­ظ„ط© ط³ط§ط¨ظ‚ط© ظ…ظƒطھظ…ظ„ط© ظ…ظ† ظٹظˆظ… ط£ظ…ط³ (Trip ID: {$tripHistorical->id})\n";

        // -------------------------------------------------------------------------
        // ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ ط§ظ„ط®ط§ظ…ط³: ط±ط­ظ„ط© ظ…ظ„ط؛ط§ط© ظ„ط³ط¨ط¨ ط·ط§ط±ط¦ (Cancelled Trip: status = cancelled)
        // -------------------------------------------------------------------------
        $tripCancelled = Trip::create([
            'driver_id'         => $driverId,
            'route_id'          => $routeReturn->id,
            'trip_type'         => 'Morning',
            'shift_slot'        => DriverSeatSlot::MORNING_RETURN,
            'status'            => 'suspended_breakdown',
            'suspension_reason' => 'ط¹ط·ظ„ ط·ط§ط±ط¦ ظپظٹ ط§ظ„ط­ط§ظپظ„ط© ظˆطھظ… طھظˆظپظٹط± ط­ط§ظپظ„ط© ط¨ط¯ظٹظ„ط© ظ„ظ†ظ‚ظ„ ط§ظ„ط·ظ„ط§ط¨',
            'scheduled_at'      => Carbon::yesterday()->setTime(13, 30, 0),
            'trip_date'         => $yesterday,
            'created_at'        => Carbon::yesterday(),
        ]);

        echo "âœ… ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆ 5: طھظ… ط¥ظٹظ‚ط§ط¹ ط±ط­ظ„ط© ظ…ظ„ط؛ط§ط© ظ„ط§ط®طھط¨ط§ط± ط­ط§ظ„ط© ط§ظ„ط¥ظ„ط؛ط§ط، (Trip ID: {$tripCancelled->id})\n";

        // =========================================================================
        // 9. ط·ط¨ط§ط¹ط© ط§ظ„طھظ‚ط±ظٹط± ط§ظ„ظ†ظ‡ط§ط¦ظٹ ظˆط³ط¬ظ„ ط§ظ„ظ…ظ„ط®طµ ظ„ظ„ط§ط®طھط¨ط§ط±
        // =========================================================================
        echo "\n" . str_repeat("=", 75) . "\n";
        echo "ًںژ‰ ط§ظƒطھظ…ظ„ ط²ط±ط¹ ط¨ظٹط§ظ†ط§طھ ط§ظ„ط³ط§ط¦ظ‚ user_id = 11 ط¨ط¬ظ…ظٹط¹ ط§ظ„ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظˆط§ظ„ط±ط­ظ„ط§طھ!\n";
        echo str_repeat("=", 75) . "\n";
        echo "ًں‘¤ ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„ط³ط§ط¦ظ‚:\n";
        echo "   - User ID: 11\n";
        echo "   - Driver ID: {$driverId}\n";
        echo "   - ط§ظ„ط§ط³ظ…: ط§ظ„ظƒط§ط¨طھظ† ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…ظ‡ط¯ظˆظٹ\n";
        echo "   - ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ: driver11@darby.ly\n";
        echo "   - ط±ظ‚ظ… ط§ظ„ظ‡ط§طھظپ: 0911111111\n";
        echo "   - ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±: password123\n";
        echo "-------------------------------------------------------------------------\n";
        echo "ًںڑŒ ط¨ظٹط§ظ†ط§طھ ط§ظ„ط±ط­ظ„ط§طھ ط§ظ„ط¬ط§ظ‡ط²ط© ظ„ظ„ط§ط®طھط¨ط§ط± ط§ظ„ظ…ط¨ط§ط´ط±:\n";
        echo "   1ï¸ڈâƒ£ ط±ط­ظ„ط© ط¬ط§ط±ظٹط© ط§ظ„ط¢ظ† (Started / Live Tracking): ID {$tripOngoing->id}\n";
        echo "      -> ط·ظ„ط§ط¨ طµط¹ط¯ظˆط§ (boarded): ط·ط§ط±ظ‚ ط§ظ„ط²ظˆظٹطŒ ط³ط§ط±ط© ط§ظ„ط²ظˆظٹ\n";
        echo "      -> ط·ظپظ„ ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط± (pending): ط¹ظ…ط± ط§ظ„ظˆط±ظپظ„ظٹ\n";
        echo "      -> ط·ظپظ„ طھظ… طھط®ط·ظٹظ‡ (skipped): ط®ط¯ظٹط¬ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ\n";
        echo "      -> ط·ظپظ„ ط؛ط§ط¦ط¨ ظ…ط³ط¨ظ‚ط§ظ‹ (absent_pre): ط£ظ†ط³ ط§ظ„طھط±ظ‡ظˆظ†ظٹ\n";
        echo "   2ï¸ڈâƒ£ ط±ط­ظ„ط© ظ‚ط§ط¯ظ…ط© ظ…ط¬ط¯ظˆظ„ط© (Pending - ط¬ط§ظ‡ط²ط© ظ„ط¨ط¯ط، ط§ظ„ط±ط­ظ„ط©): ID {$tripPendingUpcoming->id}\n";
        echo "   3ï¸ڈâƒ£ ط±ط­ظ„ط© ظ…ظƒطھظ…ظ„ط© ط§ظ„ظٹظˆظ… (Completed Today): ID {$tripCompletedToday->id}\n";
        echo "   4ï¸ڈâƒ£ ط±ط­ظ„ط© طھط§ط±ظٹط®ظٹط© ط§ظ„ط¨ط§ط±ط­ط© (Historical Completed): ID {$tripHistorical->id}\n";
        echo "   5ï¸ڈâƒ£ ط±ط­ظ„ط© ظ…ظ„ط؛ط§ط© (Cancelled Trip): ID {$tripCancelled->id}\n";
        echo "-------------------------------------------------------------------------\n";
        echo "ًں”‘ ط§ظ„ط±ظ…ظˆط² ط§ظ„ط³ط±ظٹط© ظ„ظ„ط§ط®طھط¨ط§ط± (QR Code Tokens):\n";
        echo "   - ط·ط§ط±ظ‚ ط§ظ„ط²ظˆظٹ:  QR_CHILD_1101_TAREQ\n";
        echo "   - ط³ط§ط±ط© ط§ظ„ط²ظˆظٹ:   QR_CHILD_1102_SARA\n";
        echo "   - ط¹ظ…ط± ط§ظ„ظˆط±ظپظ„ظٹ:  QR_CHILD_1103_OMAR\n";
        echo "   - ط®ط¯ظٹط¬ط© ط§ظ„طھط±ظ‡ظˆظ†ظٹ: QR_CHILD_1104_KHADIJA\n";
        echo str_repeat("=", 75) . "\n\n";
    }
}
