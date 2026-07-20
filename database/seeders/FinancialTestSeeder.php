<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Child;
use App\Models\Parent\ChildLogistics;
use App\Models\Parent\School;
use App\Models\Parent\Address;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Shared\Contract;
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Route;
use App\Models\Shared\Trip;
use App\Models\Shared\TripStudentAttendance;
use App\Models\Shared\Invoice;
use App\Models\Shared\WithdrawalRequest;
use App\Models\Shared\RechargeRequest;
use App\Models\Shared\Clause;
use App\Models\Shared\Zone;
use App\Services\Shared\FinancialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FinancialTestSeeder extends Seeder
{
    public function run(): void
    {
        $financialService = app(FinancialService::class);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clean existing test data
        RechargeRequest::where('parent_id', '>', 0)->delete();
        WithdrawalRequest::where('driver_id', '>', 0)->delete();
        Invoice::where('contract_id', '>', 0)->delete();
        TripStudentAttendance::where('trip_id', '>', 0)->delete();
        Trip::where('driver_id', '>', 0)->delete();
        Route::where('driver_id', '>', 0)->delete();
        ActiveSubscription::where('contract_id', '>', 0)->delete();
        Contract::where('id', '>', 0)->delete();
        SubscriptionRequest::where('parent_id', '>', 0)->delete();

        // Clean orphaned drivers & vehicles (FK disabled so cascade won't fire)
        Vehicle::where('plate_number', 'ط-1234')->forceDelete();
        Driver::where('national_id', '123456789')->forceDelete();

        DB::table('admins')->where('user_id', '>', 0)->delete();

        // Clean specific users
        User::where('email', 'finance.parent@test.com')->forceDelete();
        User::where('email', 'finance.parent.low@test.com')->forceDelete();
        User::where('email', 'finance.driver@test.com')->forceDelete();
        User::where('email', 'finance.admin@test.com')->forceDelete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ================================================================
        // 1. CREATE USERS & PARENT/DRIVER PROFILES
        // ================================================================
        // Note: FK checks are enabled but contracts.parent_id/driver_id FK
        // constraints reference users.id while model relationships expect
        // parents.id / drivers.id. We disable FK checks during insert to
        // satisfy the model relationships that the service layer relies on.

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Parent 1 - Sufficient balance
        $parentUser = User::create([
            'full_name'       => 'أحمد المالية',
            'email'           => 'finance.parent@test.com',
            'phone_number'    => '0921000001',
            'password_hash'   => Hash::make('12345678'),
            'role_id'         => 3,
            'is_active'       => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $parent1 = ParentModel::create([
            'user_id'    => $parentUser->id,
            'is_trusted' => 1,
        ]);

        // Parent 2 - Low balance
        $parentLowUser = User::create([
            'full_name'       => 'خالد المالية',
            'email'           => 'finance.parent.low@test.com',
            'phone_number'    => '0921000002',
            'password_hash'   => Hash::make('12345678'),
            'role_id'         => 3,
            'is_active'       => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $parent2 = ParentModel::create([
            'user_id'    => $parentLowUser->id,
            'is_trusted' => 1,
        ]);

        // Driver
        $driverUser = User::create([
            'full_name'       => 'محمود المالية',
            'email'           => 'finance.driver@test.com',
            'phone_number'    => '0921000003',
            'password_hash'   => Hash::make('12345678'),
            'role_id'         => 4,
            'is_active'       => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $driver = Driver::create([
            'user_id'         => $driverUser->id,
            'gender'          => 'male',
            'shift'           => 1,
            'subscription_type' => 'monthly',
            'national_id'     => '123456789',
            'license_number'  => 'LIC-987654',
            'license_expiry'  => '2028-12-31',
            'status'          => 'Approved',
        ]);

        // Admin user for financial management
        $adminUser = User::create([
            'full_name'       => 'مشرف المالية',
            'email'           => 'finance.admin@test.com',
            'phone_number'    => '0921000004',
            'password_hash'   => Hash::make('12345678'),
            'role_id'         => 1,
            'is_active'       => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        DB::table('admins')->insert([
            'user_id'    => $adminUser->id,
            'created_by' => $adminUser->id,
        ]);

        // ================================================================
        // 2. FUND WALLETS
        // ================================================================

        // Parent 1 gets 500 LYD (50000 cents)
        $parent1->deposit(50000);

        // Parent 2 gets only 10 LYD (1000 cents) - insufficient
        $parent2->deposit(1000);

        // Driver gets 200 LYD (20000 cents) from previous settlements
        $driver->deposit(20000);

        // ================================================================
        // 3. SCHOOL & CHILDREN
        // ================================================================

        $school = School::first();
        if (!$school) {
            $zone = Zone::first();
            $school = School::create([
                'name'      => 'مدرسة المالية التجريبية',
                'name_en'   => 'Finance Experimental School',
                'zone_id'   => $zone?->id ?? 1,
                'address'   => 'طرابلس - وسط المدينة',
            ]);
        }

        $address = Address::create([
            'parent_id'   => $parentUser->id,
            'label'       => 'المنزل',
            'lat'         => 32.87519,
            'lng'         => 13.18746,
            'is_default'  => 1,
        ]);

        // Child for parent 1
        $child1 = Child::create([
            'parent_id'    => $parentUser->id,
            'school_id'    => $school->id,
            'address_id'   => $address->id,
            'full_name'    => 'يوسف المالية',
            'birth_date'   => '2015-06-15',
            'gender'       => 'male',
            'grade'        => 4,
            'medical_notes'=> null,
            'notification_radius' => 500,
        ]);

        ChildLogistics::create([
            'child_id'            => $child1->id,
            'preferred_time_slot' => 'morning',
            'trip_direction'      => 'go',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '14:00:00',
            'start_date'          => now()->subDays(30)->toDateString(),
            'end_date'            => now()->toDateString(),
            'subscription_type'   => 'monthly',
            'is_active'           => true,
        ]);

        // ================================================================
        // 4. VEHICLE FOR DRIVER
        // ================================================================

        $vehicle = Vehicle::create([
            'driver_id'       => $driver->id,
            'plate_number'    => 'ط-1234',
            'brand'           => 'Toyota',
            'model'           => 'Hiace',
            'year'            => 2023,
            'color'           => 'أبيض',
            'type'            => 'van',
            'capacity_manual' => 8,
            'has_ac'          => true,
            'status'          => 'Active',
        ]);

        // ================================================================
        // 5. SUBSCRIPTION REQUEST (SCENARIO A: SUFFICIENT BALANCE)
        // ================================================================

        $clauses = Clause::all()->pluck('clause_text')->toArray();

        $request1 = SubscriptionRequest::create([
            'parent_id'         => $parent1->id,
            'driver_id'         => $driver->id,
            'school_id'         => $school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->subDays(30)->toDateString(),
            'end_date'          => now()->toDateString(),
            'days_count'        => 22,
            'total_price'       => 300.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => 'accepted',
            'notes'             => 'طلب اختبار مالي - رصيد كافٍ',
            'children_count'    => 1,
        ]);

        // Attach child to request
        DB::table('request_children')->insert([
            'request_id'        => $request1->id,
            'child_id'          => $child1->id,
            'pickup_address_id' => $address->id,
            'dropoff_address_id'=> $school->id,
            'home_lat'          => 32.87519,
            'home_lng'          => 13.18746,
            'school_lat'        => 32.89520,
            'school_lng'        => 13.17900,
            'price_per_child'   => 300.00,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ================================================================
        // 6. CREATE CONTRACT (TRIGGERS OBSERVER -> ROUTES + TRIPS)
        // ================================================================

        $contract1 = Contract::create([
            'subscription_request_id' => $request1->id,
            'parent_id'               => $parentUser->id,
            'driver_id'               => $driverUser->id,
            'contract_number'         => Contract::generateContractNumber(),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(30)->toDateString(),
            'end_date'                => now()->toDateString(),
            'days_count'              => 22,
            'total_price'             => 300.00,
            'clauses'                 => $clauses,
            'status'                  => 'active',
            'signed_at'               => now()->subDays(30),
        ]);

        // ================================================================
        // 7. CREATE ACTIVE SUBSCRIPTIONS MANUALLY
        // ================================================================

        ActiveSubscription::create([
            'contract_id'   => $contract1->id,
            'child_id'      => $child1->id,
            'driver_id'     => $driver->id,
            'parent_id'     => $parentUser->id,
            'pickup_lat'    => 32.87519,
            'pickup_lng'    => 13.18746,
            'pickup_label'  => 'المنزل',
            'dropoff_lat'   => 32.89520,
            'dropoff_lng'   => 13.17900,
            'dropoff_label' => 'المدرسة',
            'pickup_time'   => '07:00:00',
            'dropoff_time'  => '14:00:00',
            'status'        => 'active',
        ]);

        // ================================================================
        // 8. CREATE TRIPS & ATTENDANCE DATA
        // ================================================================

        // The ContractObserver already created 1 Morning trip.
        // Let's add more trips and update them.

        $morningRoute = Route::where('contract_id', $contract1->id)->first();
        if (!$morningRoute) {
            $morningRoute = Route::create([
                'driver_id'   => $driver->id,
                'vehicle_id'  => $vehicle->id,
                'contract_id' => $contract1->id,
                'route_name'  => 'صباحي - توصيل للمدرسة - ' . $contract1->contract_number,
                'route_type'  => 'Morning',
                'start_time'  => '07:00:00',
                'status'      => 'Active',
            ]);
        }

        // Update the observer-created trip + add more trips for the billing period
        Trip::where('route_id', $morningRoute->id)->delete();

        for ($day = 30; $day >= 1; $day--) {
            $tripDate = now()->subDays($day)->toDateString();
            $isWeekend = now()->subDays($day)->isWeekend();
            if ($isWeekend) continue;

            $isDriverAbsent = in_array($day, [5, 12]); // Driver absent on day 5 and 12

            $trip = Trip::create([
                'driver_id'           => $driver->id,
                'route_id'            => $morningRoute->id,
                'trip_type'           => 'Morning',
                'status'              => 'completed',
                'scheduled_at'        => $tripDate . ' 07:00:00',
                'started_at'          => $tripDate . ' 07:05:00',
                'completed_at'        => $tripDate . ' 07:45:00',
                'scheduled_start_time'=> $tripDate . ' 07:00:00',
                'actual_start_time'   => $tripDate . ' 07:05:00',
                'trip_date'           => $tripDate,
                'created_at'          => $tripDate . ' 06:00:00',
                'driver_attendance'   => !$isDriverAbsent,
            ]);

            // Student attendance: absent on day 4 (Thu), late on day 7 (Mon)
            $studentStatus = 'present';
            if ($day == 4) $studentStatus = 'absent';
            if ($day == 7) $studentStatus = 'late';

            TripStudentAttendance::create([
                'trip_id'            => $trip->id,
                'child_id'           => $child1->id,
                'attendance_status'  => $studentStatus,
            ]);
        }

        // ================================================================
        // 9. CREATE PROFORMA INVOICE (SCENARIO A)
        // ================================================================

        $totalTrips = Trip::where('route_id', $morningRoute->id)->count();

        Invoice::create([
            'contract_id'      => $contract1->id,
            'parent_id'        => $parentUser->id,
            'driver_id'        => $driver->id,
            'invoice_number'   => 'INV-' . $contract1->contract_number,
            'amount'           => 300.00,
            'type'             => 'proforma',
            'status'           => 'pending',
            'due_date'         => now()->toDateString(),
            'subscription_type'=> 'monthly',
            'total_trips'      => $totalTrips,
            'completed_trips'  => 0,
            'driver_absences'  => 0,
            'student_absences' => 0,
        ]);

        // ================================================================
        // 10. SCENARIO B: INSUFFICIENT BALANCE
        // ================================================================

        $request2 = SubscriptionRequest::create([
            'parent_id'         => $parent2->id,
            'driver_id'         => $driver->id,
            'school_id'         => $school->id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => now()->subDays(30)->toDateString(),
            'end_date'          => now()->toDateString(),
            'days_count'        => 22,
            'total_price'       => 300.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '14:00:00',
            'max_waiting_time'  => 15,
            'status'            => 'accepted',
            'notes'             => 'طلب اختبار مالي - رصيد غير كافٍ',
            'children_count'    => 1,
        ]);

        $contract2 = Contract::create([
            'subscription_request_id' => $request2->id,
            'parent_id'               => $parentLowUser->id,
            'driver_id'               => $driverUser->id,
            'contract_number'         => Contract::generateContractNumber(),
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(30)->toDateString(),
            'end_date'                => now()->toDateString(),
            'days_count'              => 22,
            'total_price'             => 300.00,
            'clauses'                 => $clauses,
            'status'                  => 'active',
            'signed_at'               => now()->subDays(30),
        ]);

        $route2 = Route::create([
            'driver_id'   => $driver->id,
            'vehicle_id'  => $vehicle->id,
            'contract_id' => $contract2->id,
            'route_name'  => 'صباحي - ' . $contract2->contract_number,
            'route_type'  => 'Morning',
            'start_time'  => '07:00:00',
            'status'      => 'Active',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $tripDate = now()->subDays($i)->toDateString();
            Trip::create([
                'driver_id'           => $driver->id,
                'route_id'            => $route2->id,
                'trip_type'           => 'Morning',
                'status'              => 'completed',
                'scheduled_at'        => $tripDate . ' 07:00:00',
                'started_at'          => $tripDate . ' 07:05:00',
                'completed_at'        => $tripDate . ' 07:45:00',
                'trip_date'           => $tripDate,
                'created_at'          => $tripDate . ' 06:00:00',
                'driver_attendance'   => true,
            ]);
        }

        Invoice::create([
            'contract_id'      => $contract2->id,
            'parent_id'        => $parentLowUser->id,
            'driver_id'        => $driver->id,
            'invoice_number'   => 'INV-' . $contract2->contract_number,
            'amount'           => 300.00,
            'type'             => 'proforma',
            'status'           => 'pending',
            'due_date'         => now()->toDateString(),
            'subscription_type'=> 'monthly',
            'total_trips'      => 5,
            'completed_trips'  => 0,
            'driver_absences'  => 0,
            'student_absences' => 0,
        ]);

        // ================================================================
        // 11. WITHDRAWAL REQUEST SCENARIOS
        // ================================================================

        WithdrawalRequest::create([
            'driver_id'                => $driver->id,
            'amount'                   => 100.00,
            'wallet_balance_at_request' => 200.00,
            'status'                   => 'pending',
            'payment_method_details'   => json_encode([
                'bank_name'      => 'المصرف التجاري الوطني',
                'account_number' => '123-456-789',
                'account_name'   => 'محمود المالية',
            ]),
        ]);

        WithdrawalRequest::create([
            'driver_id'                => $driver->id,
            'amount'                   => 50.00,
            'wallet_balance_at_request' => 200.00,
            'status'                   => 'approved',
            'admin_id'                 => null,
            'processed_at'            => now()->subDays(2),
        ]);

        // ================================================================
        // 12. RECHARGE REQUEST SCENARIOS
        // ================================================================

        RechargeRequest::create([
            'parent_id'        => $parentUser->id,
            'amount'           => 200.00,
            'payment_method'   => 'ncb',
            'reference_number'  => 'NCB-REF-12345',
            'status'           => 'pending',
            'notes'            => 'شحن عبر المصرف التجاري الوطني - 200 د.ل',
        ]);

        RechargeRequest::create([
            'parent_id'        => $parentUser->id,
            'amount'           => 150.00,
            'payment_method'   => 'libyana',
            'reference_number' => null,
            'status'           => 'completed',
            'admin_id'         => null,
            'completed_at'     => now()->subDay(),
        ]);

        $this->command->info('====================================');
        $this->command->info('✅ تم إنشاء بيانات اختبار المالية');
        $this->command->info('====================================');
        $this->command->info('📍 Parent 1 (رصيد كافٍ):');
        $this->command->info('   email: finance.parent@test.com');
        $this->command->info('   pass:  12345678');
        $this->command->info('   الرصيد: 500 د.ل');
        $this->command->info('');
        $this->command->info('📍 Parent 2 (رصيد غير كافٍ):');
        $this->command->info('   email: finance.parent.low@test.com');
        $this->command->info('   pass:  12345678');
        $this->command->info('   الرصيد: 10 د.ل');
        $this->command->info('');
        $this->command->info('📍 Driver:');
        $this->command->info('   email: finance.driver@test.com');
        $this->command->info('   pass:  12345678');
        $this->command->info('   الرصيد: 200 د.ل');
        $this->command->info('');
        $this->command->info('📍 Admin:');
        $this->command->info('   phone: 0921000004');
        $this->command->info('   pass:  12345678');
        $this->command->info('');
        $this->command->info('📊 بيانات الفواتير:');
        $this->command->info('   - عقد Parent 1: 300 د.ل (22 يوم, 2 غياب سائق)');
        $this->command->info('   - عقد Parent 2: 300 د.ل (5 أيام, رصيد غير كافٍ)');
        $this->command->info('   - طلب سحب: 1 معلق + 1 تمت الموافقة عليه');
        $this->command->info('   - طلب شحن: 1 معلق + 1 مكتمل');
        $this->command->info('====================================');
    }
}
