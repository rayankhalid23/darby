<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Address;
use App\Models\Parent\School;
use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\DriverReview;
use App\Models\Shared\Complaint;
use App\Models\Shared\AbsenceLog;
use App\Models\Shared\Trip;
use App\Models\Shared\TripEvent;
use App\Models\Shared\TripTracking;
use App\Models\Shared\Route;
use App\Models\Shared\Invoice;
use App\Models\Shared\RechargeRequest;
use App\Models\Shared\WithdrawalRequest;
use Carbon\Carbon;

class FullSystemSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        

        echo "ًںڑ€ ط¨ط¯ط§ظٹط© ط²ط±ط¹ ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ط´ط§ظ…ظ„ط© ظ„ظ„ظ†ط¸ط§ظ…...\n";

        // طھظ†ط¸ظٹظپ ط§ظ„ط¨ظٹط§ظ†ط§طھ ظˆط§ظ„طھظ†ط¸ظٹظپ ط§ظ„ظˆظ‚ط§ط¦ظٹ ظ„طھظƒط±ط§ط± ط§ظ„ط§ط³طھط¯ط¹ط§ط،
        $emails = ['admin@darby.com', 'parent1@darby.com', 'parent2@darby.com', 'driver1@darby.com', 'driver2@darby.com'];
        $oldUserIds = User::whereIn('email', $emails)->pluck('id');
        if ($oldUserIds->count() > 0) {
            $oldDriverIds = DB::table('drivers')->whereIn('user_id', $oldUserIds)->pluck('id');
            DB::table('vehicles')->whereIn('driver_id', $oldDriverIds)->orWhereIn('plate_number', ['5-12345', '5-67890'])->delete();
            DB::table('parents')->whereIn('user_id', $oldUserIds)->delete();
            DB::table('drivers')->whereIn('user_id', $oldUserIds)->delete();
            DB::table('admins')->whereIn('user_id', $oldUserIds)->delete();
            User::whereIn('id', $oldUserIds)->forceDelete();
        }

        DB::table('vehicles')->whereIn('plate_number', ['5-12345', '5-67890'])->delete();
        DB::table('invoices')->whereIn('invoice_number', ['INV-2026-001'])->delete();
        DB::table('contracts')->whereIn('contract_number', ['CNT-2026-001'])->delete();

        // =========================================================================
        // 1. ط²ط±ط¹ ط§ظ„ط£ط¯ظˆط§ط± (Roles)
        // =========================================================================
        if (DB::table('roles')->count() === 0) {
            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'super_admin', 'display_name' => 'ط³ظˆط¨ط± ط£ط¯ظ…ظ†'],
                ['id' => 2, 'name' => 'admin', 'display_name' => 'ظ…ط´ط±ظپ ط§ظ„ظ†ط¸ط§ظ…'],
                ['id' => 3, 'name' => 'parent', 'display_name' => 'ظˆظ„ظٹ ط£ظ…ط±'],
                ['id' => 4, 'name' => 'driver', 'display_name' => 'ط³ط§ط¦ظ‚'],
            ]);
        }

        // =========================================================================
        // 2. ط²ط±ط¹ ط§ظ„ط¬ط؛ط±ط§ظپظٹط§ ظˆط§ظ„ظ…ط¯ط§ط±ط³ (Geography & Schools)
        // =========================================================================
        $municipalityId = DB::table('municipalities')->insertGetId([
            'name' => 'ط·ط±ط§ط¨ظ„ط³ ط§ظ„ظ…ط±ظƒط²',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $subMunicipalityId = DB::table('sub_municipalities')->insertGetId([
            'municipality_id' => $municipalityId,
            'name' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $zoneId = DB::table('zones')->insertGetId([
            'sub_municipality_id' => $subMunicipalityId,
            'name' => 'ظ…ظ†ط·ظ‚ط© ط§ظ„ط³ظٹط§ط­ظٹط©',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $school1 = School::create([
            'name' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯ ط§ظ„ط¯ظˆظ„ظٹط©',
            'lat' => 32.89000000,
            'lng' => 13.17000000,
            'address' => 'ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ - ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط¬ط§ظ…ط¹ ط§ظ„ط£ظ†ط¯ظ„ط³',
            'zone_id' => $zoneId,
            'status' => 'approved'
        ]);

        $school2 = School::create([
            'name' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط´ط±ظˆظ‚ ط§ظ„ط£ظ‡ظ„ظٹط©',
            'lat' => 32.90500000,
            'lng' => 13.22000000,
            'address' => 'ط¨ظ† ط¹ط§ط´ظˆط± - ط§ظ„ط´ط§ط±ط¹ ط§ظ„ط؛ط±ط¨ظٹ',
            'zone_id' => $zoneId,
            'status' => 'approved'
        ]);

        // =========================================================================
        // 3. ط­ط³ط§ط¨ ظ…ط¯ظٹط± ط§ظ„ظ†ط¸ط§ظ… (Admin Account)
        // =========================================================================
        $adminUser = User::create([
            'full_name' => 'ط£ط­ظ…ط¯ ط§ظ„ظ…ظ†طµظˆط±ظٹ (ط§ظ„ط£ط¯ظ…ظ†)',
            'email' => 'admin@darby.com',
            'phone_number' => '0910000000',
            'password_hash' => Hash::make('12345678'),
            'role_id' => 2,
            'is_active' => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);

        $admin = DB::table('admins')->insertGetId([
            'user_id' => $adminUser->id,
            'created_by' => $adminUser->id
        ]);

        // =========================================================================
        // 4. ط£ظˆظ„ظٹط§ط، ط§ظ„ط£ظ…ظˆط± (2 Parents + Addresses + Children)
        // =========================================================================

        // --- ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط§ظ„ط£ظˆظ„ ---
        $parent1User = User::create([
            'full_name' => 'ط·ظ‡ ط³ط§ظ„ظ… ط§ظ„ظ‚ظ…ظˆط¯ظٹ',
            'email' => 'parent1@darby.com',
            'phone_number' => '0911111111',
            'password_hash' => Hash::make('12345678'),
            'role_id' => 3,
            'is_active' => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);

        $parent1 = ParentModel::create([
            'user_id' => $parent1User->id,
            'is_trusted' => 1
        ]);

        $address1 = Address::create([
            'parent_id' => $parent1User->id,
            'label' => 'ط§ظ„ط¨ظٹطھ ط§ظ„ط±ط¦ظٹط³ظٹ - ط­ظٹ ط§ظ„ط£ظ†ط¯ظ„ط³ ط®ظ„ظپ ظ…ط±ظƒط² ط§ظ„ظ…ظ‚ط§ط±ط­ط©',
            'lat' => 32.89200000,
            'lng' => 13.17500000,
            'is_default' => true,
            'zone_id' => $zoneId
        ]);

        $child1 = Child::create([
            'parent_id' => $parent1User->id,
            'school_id' => $school1->id,
            'address_id' => $address1->id,
            'full_name' => 'ط³ظ†ط¯ ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ',
            'birth_date' => '2016-04-10',
            'gender' => 'male',
            'grade' => 3,
            'medical_notes' => 'ط­ط³ط§ط³ظٹط© ظ…ظ† ط§ظ„ط؛ط¨ط§ط±'
        ]);

        $child2 = Child::create([
            'parent_id' => $parent1User->id,
            'school_id' => $school1->id,
            'address_id' => $address1->id,
            'full_name' => 'ظ…ط±ظˆط© ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ',
            'birth_date' => '2014-08-20',
            'gender' => 'female',
            'grade' => 5
        ]);

        // --- ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط§ظ„ط«ط§ظ†ظٹ ---
        $parent2User = User::create([
            'full_name' => 'ظ…ط­ظ…ظˆط¯ ط¹ظ„ظٹ ط§ظ„ظˆط±ظپظ„ظٹ',
            'email' => 'parent2@darby.com',
            'phone_number' => '0912222222',
            'password_hash' => Hash::make('12345678'),
            'role_id' => 3,
            'is_active' => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);

        $parent2 = ParentModel::create([
            'user_id' => $parent2User->id,
            'is_trusted' => 1
        ]);

        $address2 = Address::create([
            'parent_id' => $parent2User->id,
            'label' => 'ظ…ظ†ط²ظ„ ط§ظ„ط¹ط§ط¦ظ„ط© - ط¨ظ† ط¹ط§ط´ظˆط± ط¨ط§ظ„ظ‚ط±ط¨ ظ…ظ† ط§ظ„ظ‚ظ†طµظ„ظٹط©',
            'lat' => 32.90100000,
            'lng' => 13.21500000,
            'is_default' => true,
            'zone_id' => $zoneId
        ]);

        $child3 = Child::create([
            'parent_id' => $parent2User->id,
            'school_id' => $school2->id,
            'address_id' => $address2->id,
            'full_name' => 'ط£ظٹظ…ظ† ظ…ط­ظ…ظˆط¯ ط§ظ„ظˆط±ظپظ„ظٹ',
            'birth_date' => '2017-01-15',
            'gender' => 'male',
            'grade' => 2
        ]);


        // =========================================================================
        // 5. ط§ظ„ط³ط§ط¦ظ‚ظٹظ† (2 Drivers + Vehicles)
        // =========================================================================

        // --- ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط£ظˆظ„ ---
        $driver1User = User::create([
            'full_name' => 'ط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ… ط§ظ„ظ…طµط±ط§طھظٹ',
            'email' => 'driver1@darby.com',
            'phone_number' => '0921111111',
            'password_hash' => Hash::make('12345678'),
            'role_id' => 4,
            'is_active' => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);

        $driver1 = Driver::create([
            'user_id' => $driver1User->id,
            'national_id' => '119900112233',
            'license_number' => 'DL-998877',
            'license_expiry' => '2028-12-31',
            'status' => 'Approved',
            'shift' => 3, // ط§ظ„ظپطھط±طھظٹظ†
            'subscription_type' => 'both',
            'accepted_gender' => 'both',
            'gender' => 'male',
            'current_lat' => 32.89000000,
            'current_lng' => 13.18000000
        ]);

        $vehicle1 = Vehicle::create([
            'driver_id' => $driver1->id,
            'type' => 'Bus',
            'brand' => 'طھظˆظٹظˆطھط§',
            'model' => 'ظƒظˆط³طھط±',
            'year' => 2022,
            'color' => 'ط£ط¨ظٹط¶',
            'plate_number' => '5-12345',
            'capacity_manual' => 14,
            'has_ac' => 1,
            'status' => 'Active'
        ]);

        DB::table('driver_zone')->insert([
            'driver_id' => $driver1->id,
            'zone_id' => $zoneId
        ]);

        // --- ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط«ط§ظ†ظٹ ---
        $driver2User = User::create([
            'full_name' => 'ط·ط§ظ‡ط± ط§ظ„ط²ظ†طھط§ظ†ظٹ',
            'email' => 'driver2@darby.com',
            'phone_number' => '0922222222',
            'password_hash' => Hash::make('12345678'),
            'role_id' => 4,
            'is_active' => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);

        $driver2 = Driver::create([
            'user_id' => $driver2User->id,
            'national_id' => '119900445566',
            'license_number' => 'DL-665544',
            'license_expiry' => '2027-10-30',
            'status' => 'Approved',
            'shift' => 1,
            'subscription_type' => 'both',
            'accepted_gender' => 'both',
            'gender' => 'male',
            'current_lat' => 32.91000000,
            'current_lng' => 13.22000000
        ]);

        $vehicle2 = Vehicle::create([
            'driver_id' => $driver2->id,
            'type' => 'Van',
            'brand' => 'ظ‡ظٹظˆظ†ط¯ط§ظٹ',
            'model' => 'H1',
            'year' => 2021,
            'color' => 'ط±طµط§طµظٹ',
            'plate_number' => '5-67890',
            'capacity_manual' => 8,
            'has_ac' => 1,
            'status' => 'Active'
        ]);

        DB::table('driver_zone')->insert([
            'driver_id' => $driver2->id,
            'zone_id' => $zoneId
        ]);


        // =========================================================================
        // 6. ط·ظ„ط¨ط§طھ ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ظˆط§ظ„ط¹ظ‚ظˆط¯ ظˆط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط©
        // =========================================================================

        // --- ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ظ…ظ‚ط¨ظˆظ„ ظˆظ…ط«ط¨طھ ط¨ط¹ظ‚ط¯ ظ„ظ„ظˆظ„ظٹ ط§ظ„ط£ظˆظ„ ظ…ط¹ ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط£ظˆظ„ ---
        $subRequest1 = SubscriptionRequest::create([
            'parent_id' => $parent1->id,
            'driver_id' => $driver1->id,
            'school_id' => $school1->id,
            'subscription_type' => 'multi_day',
            'direction' => 'two_way',
            'timing' => 'morning',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'days_count' => 22,
            'total_price' => 600.00,
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'max_waiting_time' => 15,
            'status' => SubscriptionRequest::STATUS_ACCEPTED,
            'notes' => 'ظٹط±ط¬ظ‰ ط§ظ„ط§ظ„طھط²ط§ظ… ط¨ط§ظ„ط§ظ†طھط¸ط§ط± ط£ظ…ط§ظ… ط§ظ„ط¨ظٹطھ',
            'children_count' => 2
        ]);

        DB::table('request_children')->insert([
            [
                'request_id' => $subRequest1->id,
                'child_id' => $child1->id,
              
                'home_lat' => 32.89200000,
                'home_lng' => 13.17500000,
                'home_label' => 'ط§ظ„ط¨ظٹطھ ط§ظ„ط±ط¦ظٹط³ظٹ',
                'school_lat' => 32.89000000,
                'school_lng' => 13.17000000,
                'school_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯',
                'price_per_child' => 300.00
            ],
            [
                'request_id' => $subRequest1->id,
                'child_id' => $child2->id,
               
                'home_lat' => 32.89200000,
                'home_lng' => 13.17500000,
                'home_label' => 'ط§ظ„ط¨ظٹطھ ط§ظ„ط±ط¦ظٹط³ظٹ',
                'school_lat' => 32.89000000,
                'school_lng' => 13.17000000,
                'school_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯',
                'price_per_child' => 300.00
            ]
        ]);

        // ط§ظ„ط¹ظ‚ط¯ ط§ظ„ط±ط³ظ…ظٹ ط¨ظٹظ† ط·ظ‡ ظˆط¹ط¨ط¯ ط§ظ„ط³ظ„ط§ظ…
        $contract1 = Contract::create([
            'subscription_request_id' => $subRequest1->id,
            'parent_id' => $parent1User->id,
            'driver_id' => $driver1User->id,
            'contract_number' => 'CNT-2026-001',
            'subscription_type' => 'multi_day',
            'direction' => 'two_way',
            'timing' => 'morning',
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'max_waiting_time' => 15,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(30)->toDateString(),
            'days_count' => 22,
            'total_price' => 600.00,
            'status' => 'activated',
            'signed_at' => now()
        ]);

        // ط§ظ„ط§ط´طھط±ط§ظƒط§طھ ط§ظ„ظ†ط´ط·ط© ط§ظ„ظپط¹ظ„ظٹط© ظ„ظ„ط§ط·ظپط§ظ„
        $activeSub1 = ActiveSubscription::create([
            'contract_id' => $contract1->id,
            'child_id' => $child1->id,
            'driver_id' => $driver1->id,
            'parent_id' => $parent1User->id,
            'pickup_lat' => 32.89200000,
            'pickup_lng' => 13.17500000,
            'pickup_label' => 'ظ…ظ†ط²ظ„ ط§ظ„ظˆظ„ظٹ ط·ظ‡',
            'dropoff_lat' => 32.89000000,
            'dropoff_lng' => 13.17000000,
            'dropoff_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯',
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'status' => 'active'
        ]);

        $activeSub2 = ActiveSubscription::create([
            'contract_id' => $contract1->id,
            'child_id' => $child2->id,
            'driver_id' => $driver1->id,
            'parent_id' => $parent1User->id,
            'pickup_lat' => 32.89200000,
            'pickup_lng' => 13.17500000,
            'pickup_label' => 'ظ…ظ†ط²ظ„ ط§ظ„ظˆظ„ظٹ ط·ظ‡',
            'dropoff_lat' => 32.89000000,
            'dropoff_lng' => 13.17000000,
            'dropoff_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط¬ظٹظ„ ط§ظ„ط¬ط¯ظٹط¯',
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'status' => 'active'
        ]);


        // --- ط·ظ„ط¨ ط§ط´طھط±ط§ظƒ ط«ط§ظ†ظٹ ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط± ظ„ظ„ظˆظ„ظٹ ط§ظ„ط«ط§ظ†ظٹ ظ…ط¹ ط§ظ„ط³ط§ط¦ظ‚ ط§ظ„ط«ط§ظ†ظٹ ---
        $subRequest2 = SubscriptionRequest::create([
            'parent_id' => $parent2->id,
            'driver_id' => $driver2->id,
            'school_id' => $school2->id,
            'subscription_type' => 'multi_day',
            'direction' => 'two_way',
            'timing' => 'morning',
            'start_date' => Carbon::today()->addDay()->toDateString(),
            'end_date' => Carbon::today()->addDays(31)->toDateString(),
            'days_count' => 22,
            'total_price' => 350.00,
            'pickup_time' => '07:15:00',
            'dropoff_time' => '14:15:00',
            'max_waiting_time' => 10,
            'status' => SubscriptionRequest::STATUS_PENDING,
            'notes' => 'ط§ظ„ط·ظپظ„ ظپظٹ ط§ظ„طµظپ ط§ظ„ط«ط§ظ†ظٹ ط§ط¨طھط¯ط§ط¦ظٹ',
            'children_count' => 1
        ]);

        DB::table('request_children')->insert([
            'request_id' => $subRequest2->id,
            'child_id' => $child3->id,
        
            'home_lat' => 32.90100000,
            'home_lng' => 13.21500000,
            'home_label' => 'ظ…ظ†ط²ظ„ ظ…ط­ظ…ظˆط¯ ط§ظ„ظˆط±ظپظ„ظٹ',
            'school_lat' => 32.90500000,
            'school_lng' => 13.22000000,
            'school_label' => 'ظ…ط¯ط±ط³ط© ط§ظ„ط´ط±ظˆظ‚ ط§ظ„ط£ظ‡ظ„ظٹط©',
            'price_per_child' => 350.00
        ]);


        // =========================================================================
        // 7. ط§ظ„طھظ‚ظٹظٹظ…ط§طھ ظˆط§ظ„طھط¹ظ„ظٹظ‚ط§طھ (Driver Reviews)
        // =========================================================================
        DriverReview::create([
            'parent_id' => $parent1User->id,
            'driver_id' => $driver1->id,
            'contract_id' => $contract1->id,
            'rating' => 5,
            'comment' => 'ط³ط§ط¦ظ‚ ظ…ظ…طھط§ط² ط¬ط¯ط§ظ‹ ظˆط®ظ„ظˆظ‚طŒ ظ…ظ„طھط²ظ… ط¬ط¯ط§ظ‹ ط¨ط§ظ„ظ…ظˆط§ط¹ظٹط¯ ط§ظ„ظٹظˆظ…ظٹط© ظˆظٹط±ط¹ظ‰ ط§ظ„ط£ط·ظپط§ظ„ ط¨ط§ظ‡طھظ…ط§ظ….',
            'status' => 'active'
        ]);

        DriverReview::create([
            'parent_id' => $parent2User->id,
            'driver_id' => $driver2->id,
            'contract_id' => null,
            'rating' => 4,
            'comment' => 'ظ‚ظٹط§ط¯ط© ط¢ظ…ظ†ط© ظˆط³ظٹط§ط±ط§طھ ط¬ط¯ظٹط¯ط© ظˆظ…ظƒظٹظپط©.',
            'status' => 'active'
        ]);


        // =========================================================================
        // 8. ط§ظ„ط´ظƒط§ظˆظ‰ (Complaints)
        // =========================================================================

        // ط´ظƒظˆظ‰ ظ…ط¹ط§ظ„ط¬ط© طھظ… ط¥ط؛ظ„ط§ظ‚ظ‡ط§ ط¨ط£ظ†ط°ط§ط±
        Complaint::create([
            'submitted_by' => $parent1->id,
            'against_type' => 'DRIVER',
            'against_id' => $driver1->id,
            'driver_id' => $driver1->id,
            'description' => 'طھط£ط®ط± ط§ظ„ط³ط§ط¦ظ‚ ظپظٹ ط§ظ„طھظˆط§ط¬ط¯ ط£ظ…ط§ظ… ط§ظ„ظ…ظ†ط²ظ„ ظ„ظ…ط¯ط© 20 ط¯ظ‚ظٹظ‚ط© ظپظٹ ط±ط­ظ„ط© ط§ظ„طµط¨ط§ط­ ط¨طھط§ط±ظٹط® ط£ظ…ط³.',
            'status' => 'completed',
            'action_taken' => 'warning',
            'action_details' => 'طھظ… ط§ظ„ط§طھطµط§ظ„ ط¨ط§ظ„ط³ط§ط¦ظ‚ ظˆطھظˆط¬ظٹظ‡ ط¥ظ†ط°ط§ط± ط±ط³ظ…ظٹ ظ„ظ„طھظˆط§ط¬ط¯ ظپظٹ ط§ظ„ظ…ظˆط¹ط¯ ط§ظ„ظ…ط­ط¯ط¯.',
            'resolved_by' => $admin,
            'resolved_at' => now()
        ]);

        // ط´ظƒظˆظ‰ ط¬ط¯ظٹط¯ط© ظ…ط¹ظ„ظ‚ط© ط¨ط§ظ†طھط¸ط§ط± ظ…ط±ط§ط¬ط¹ط© ط§ظ„ط£ط¯ظ…ظ†
        Complaint::create([
            'submitted_by' => $parent2->id,
            'against_type' => 'DRIVER',
            'against_id' => $driver2->id,
            'driver_id' => $driver2->id,
            'description' => 'ط¹ط¯ظ… طھط´ط؛ظٹظ„ ظ…ظƒظٹظپ ط§ظ„ط³ظٹط§ط±ط© ط£ط«ظ†ط§ط، ط±ط­ظ„ط© ط§ظ„ط¹ظˆط¯ط© ط¸ظ‡ط± ط§ظ„ظٹظˆظ… ط±ط؛ظ… ط­ط±ط§ط±ط© ط§ظ„ط·ظ‚ط³.',
            'status' => 'pending',
            'action_taken' => 'none'
        ]);


        // =========================================================================
        // 9. ط؛ظٹط§ط¨ ط§ظ„ط£ط·ظپط§ظ„ (Absence Logs)
        // =========================================================================
        AbsenceLog::create([
            'child_id' => $child3->id,
            'absence_date' => Carbon::tomorrow()->toDateString()
        ]);


        // =========================================================================
        // 10. ط§ظ„ظ…ط³ط§ط±ط§طھ ظˆط§ظ„ط±ط­ظ„ط§طھ ط§ظ„ط­ظٹط© (Routes & Trips & Tracking)
        // =========================================================================

        $route1 = Route::create([
            'contract_id' => $contract1->id,
            'driver_id' => $driver1->id,
            'vehicle_id' => $vehicle1->id,
            'route_name' => 'ظ…ط³ط§ط± ط·ظ‡ ط§ظ„ظ‚ظ…ظˆط¯ظٹ - طµط¨ط§ط­ظٹ',
            'route_type' => 'Morning',
            'start_time' => '07:00:00',
            'total_distance' => 6.5,
            'estimated_duration' => 20,
            'status' => 'Active'
        ]);

        // ط±ط­ظ„ط© ظ…ظƒطھظ…ظ„ط© ظ„ظ„ظٹظˆظ…
        $tripCompleted = Trip::create([
            'driver_id' => $driver1->id,
            'route_id' => $route1->id,
            'trip_type' => 'Morning',
            'status' => 'completed',
            'scheduled_at' => Carbon::today()->setHour(7),
            'started_at' => Carbon::today()->setHour(7)->setMinute(5),
            'completed_at' => Carbon::today()->setHour(7)->setMinute(28),
            'scheduled_start_time' => Carbon::today()->setHour(7),
            'actual_start_time' => Carbon::today()->setHour(7)->setMinute(5),
            'trip_date' => Carbon::today()->toDateString(),
            'created_at' => now()
        ]);

        TripEvent::create([
            'trip_id' => $tripCompleted->id,
            'child_id' => $child1->id,
            'subscription_id' => $activeSub1->id,
            'action_type' => 'picked_up',
            'trip_type' => 'ط°ظ‡ط§ط¨',
            'scanned_at' => Carbon::today()->setHour(7)->setMinute(10),
            'location_lat' => 32.89200000,
            'location_lng' => 13.17500000,
            'trip_cost' => 15.00
        ]);

        TripEvent::create([
            'trip_id' => $tripCompleted->id,
            'child_id' => $child1->id,
            'subscription_id' => $activeSub1->id,
            'action_type' => 'dropped_off',
            'trip_type' => 'ط°ظ‡ط§ط¨',
            'scanned_at' => Carbon::today()->setHour(7)->setMinute(25),
            'location_lat' => 32.89000000,
            'location_lng' => 13.17000000,
            'trip_cost' => 15.00
        ]);

        // ط±ط­ظ„ط© ط­ظٹط© ط¬ط§ط±ظٹط© ط§ظ„ط¢ظ† ظ„ظ„ط±ط§ط¯ط§ط±
        $tripInProgress = Trip::create([
            'driver_id' => $driver2->id,
            'route_id' => 0,
            'trip_type' => 'Morning',
            'status' => 'in_progress',
            'scheduled_at' => now(),
            'started_at' => now()->subMinutes(12),
            'scheduled_start_time' => now(),
            'actual_start_time' => now()->subMinutes(12),
            'trip_date' => Carbon::today()->toDateString(),
            'created_at' => now()
        ]);

        TripTracking::create([
            'trip_id' => $tripInProgress->id,
            'latitude' => 32.90200000,
            'longitude' => 13.21800000,
            'speed' => 45.5,
            'recorded_at' => now()
        ]);


        // =========================================================================
        // 11. ط§ظ„ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ط§ظ„ظٹط© (Wallets, Invoices, Requests)
        // =========================================================================

        // ظ…ط­ظپط¸ط© ظˆظ„ظٹ ط§ظ„ط£ظ…ط± ط·ظ‡
        DB::table('wallets')->insert([
            'holder_type' => 'App\Models\User',
            'holder_id' => $parent1User->id,
            'name' => 'ط§ظ„ظ…ط­ظپط¸ط© ط§ظ„ط±ط¦ظٹط³ظٹط©',
            'slug' => 'default',
            'uuid' => Str::uuid()->toString(),
            'balance' => 350,
            'decimal_places' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // ظپط§طھظˆط±ط© ظ„ظ„ط¹ظ‚ط¯
        Invoice::create([
            'contract_id' => $contract1->id,
            'parent_id' => $parent1User->id,
            'driver_id' => $driver1->id,
            'invoice_number' => 'INV-2026-001',
            'amount' => 600.00,
            'status' => 'paid',
            'type' => 'monthly',
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
            'paid_at' => now()
        ]);

        // ط·ظ„ط¨ ط´ط­ظ† ظ…ط­ظپط¸ط© ظ‚ظٹط¯ ط§ظ„ط§ظ†طھط¸ط§ط±
        RechargeRequest::create([
            'parent_id' => $parent2User->id,
            'amount' => 200.00,
            'payment_method' => 'Bank Transfer',
            'status' => 'pending',
            'reference_number' => 'REF-998877'
        ]);

        // ط·ظ„ط¨ ط³ط­ط¨ ط£ط±ط¨ط§ط­ ظ„ظ„ط³ط§ط¦ظ‚ ط§ظ„ط£ظˆظ„
        WithdrawalRequest::create([
            'driver_id' => $driver1->id,
            'amount' => 450.00,
            'wallet_balance_at_request' => 500.00,
            'status' => 'pending',
            'payment_method_details' => ['bank' => 'ظ…طµط±ظپ ط§ظ„ط¬ظ…ظ‡ظˆط±ظٹط©', 'account' => 'LY3300100200300400']
        ]);

<<<<<<< HEAD
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "ًںژ‰ طھظ… ط²ط±ط¹ ظƒط§ظپط© ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ†ط¸ط§ظ… ظˆط§ظ„ط³ظٹظ†ط§ط±ظٹظˆظ‡ط§طھ ط¨ظ†ط¬ط§ط­ ظˆط­ط³ط§ط¨ط§طھ ط§ظ„ط§ط®طھط¨ط§ط± ط¬ط§ظ‡ط²ط© ظ„ظ„ط§ط³طھط®ط¯ط§ظ…!\n";
=======
        
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        echo "🎉 تم زرع كافة بيانات النظام والسيناريوهات بنجاح وحسابات الاختبار جاهزة للاستخدام!\n";
>>>>>>> 7c7e95414ad0f0430534f46f0b6057beb96b09af
    }
}
