<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class E2eTestSeeder extends Seeder
{
    private string $password;
    private int $roleParent = 3;
    private int $roleDriver = 4;

    private int $zoneId;
    private int $schoolId;

    public function run(): void
    {
        $this->password = Hash::make('12345678');

        if (DB::table('users')->where('email', 'parent.e2e@test.com')->exists()) {
            $this->command->warn('⚠️ بيانات الاختبار موجودة مسبقاً. يتم تخطي التهيئة.');
            $this->printSummary();
            return;
        }

        $this->command->info('🧪 بدء تهيئة بيانات اختبار E2E للمسارات والرحلات...');

        $this->resolveGeography();
        $this->resolveSchool();
        $this->createParentAndChild();
        $this->createDriver();
        $this->createSubscriptionRequest();

        $this->command->info('✅ اكتمل! البيانات جاهزة لاختبار سيناريو القبول → العقد → المسار → الرحلة.');
        $this->printSummary();
    }

    private function resolveGeography(): void
    {
        $zone = DB::table('zones')->first();
        if (!$zone) {
            $subMuni = DB::table('sub_municipalities')->first();
            if (!$subMuni) {
                $muniId = DB::table('municipalities')->insertGetId([
                    'name' => 'بلدية الاختبار',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $subMuniId = DB::table('sub_municipalities')->insertGetId([
                    'municipality_id' => $muniId,
                    'name' => 'بلدية الاختبار المركز',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $subMuniId = $subMuni->id;
            }
            $this->zoneId = DB::table('zones')->insertGetId([
                'sub_municipality_id' => $subMuniId,
                'name' => 'منطقة الاختبار',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->zoneId = $zone->id;
        }
    }

    private function resolveSchool(): void
    {
        $school = DB::table('schools')->first();
        if (!$school) {
            $this->schoolId = DB::table('schools')->insertGetId([
                'name'    => 'مدرسة الاختبار',
                'zone_id' => $this->zoneId,
                'lat'     => 32.8872,
                'lng'     => 13.1913,
                'address' => 'طرابلس',
                'status'  => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->schoolId = $school->id;
        }
    }

    private function createParentAndChild(): void
    {
        $parentUserId = DB::table('users')->insertGetId([
            'full_name'     => 'ولي أمر الاختبار',
            'email'         => 'parent.e2e@test.com',
            'phone_number'  => '0911111111',
            'password_hash' => $this->password,
            'role_id'       => $this->roleParent,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $parentId = DB::table('parents')->insertGetId([
            'user_id'    => $parentUserId,
            'is_trusted' => 1,
        ]);

        $addressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $parentUserId,
            'label'      => 'منزل الاختبار',
            'lat'        => 32.9014,
            'lng'        => 13.2000,
            'is_default' => true,
            'zone_id'    => $this->zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = DB::table('children')->insertGetId([
            'parent_id'           => $parentUserId,
            'school_id'           => $this->schoolId,
            'address_id'          => $addressId,
            'full_name'           => 'طفل الاختبار',
            'birth_date'          => '2015-03-10',
            'gender'              => 'male',
            'grade'               => 4,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-E2E-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        DB::table('child_logistics')->insert([
            'child_id'            => $childId,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '13:30:00',
            'trip_direction'      => 'both',
            'subscription_type'   => 'monthly',
            'start_date'          => Carbon::now()->startOfMonth()->toDateString(),
            'end_date'            => Carbon::now()->endOfMonth()->toDateString(),
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        DB::table('addresses')->insert([
            'parent_id'  => $parentUserId,
            'label'      => 'منزل الاختبار - دومين',
            'lat'        => 32.8760,
            'lng'        => 13.2350,
            'is_default' => false,
            'zone_id'    => $this->zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDriver(): void
    {
        $driverUserId = DB::table('users')->insertGetId([
            'full_name'     => 'سائق الاختبار',
            'email'         => 'driver.e2e@test.com',
            'phone_number'  => '0922222222',
            'password_hash' => $this->password,
            'role_id'       => $this->roleDriver,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $driverId = DB::table('drivers')->insertGetId([
            'user_id'                   => $driverUserId,
            'gender'                    => 'male',
            'accepted_gender'           => 'both',
            'subscription_type'         => 'both',
            'shift'                     => 3,
            'status'                    => 'Approved',
            'license_number'            => 'LIC-E2E-001',
            'license_expiry'            => '2028-12-31',
            'national_id'               => 'NAT-E2E-001',
            'rating_avg'                => 5.00,
            'completed_trips_count'     => 0,
            'active_subs_count'         => 0,
            'total_subs_count'          => 0,
            'cancelled_by_driver_count' => 0,
            'cancelled_by_parent_count' => 0,
            'retention_rate'            => 100.00,
            'current_lat'               => 32.9000,
            'current_lng'               => 13.1900,
            'last_ping_at'              => now(),
            'driver_waiting_minutes'    => 5,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        DB::table('vehicles')->insert([
            'driver_id'       => $driverId,
            'plate_number'    => 'LY-E2E-01',
            'brand'           => 'Toyota',
            'model'           => 'Hiace',
            'year'            => '2025',
            'color'           => 'أبيض',
            'type'            => 'Van',
            'capacity_manual' => 12,
            'has_ac'          => 1,
            'status'          => 'Active',
            'is_verified'     => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('driver_zone')->insert([
            'driver_id'  => $driverId,
            'zone_id'    => $this->zoneId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubscriptionRequest(): void
    {
        $parent = DB::table('parents')
            ->join('users', 'parents.user_id', '=', 'users.id')
            ->where('users.email', 'parent.e2e@test.com')
            ->select('parents.id as parent_id', 'parents.user_id as user_id')
            ->first();

        $driver = DB::table('drivers')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->where('users.email', 'driver.e2e@test.com')
            ->select('drivers.id as driver_id')
            ->first();

        $child = DB::table('children')
            ->join('users', 'children.parent_id', '=', 'users.id')
            ->where('users.email', 'parent.e2e@test.com')
            ->select('children.id as child_id', 'children.school_id')
            ->first();

        $addresses = DB::table('addresses')
            ->where('parent_id', $parent->user_id)
            ->get();

        $homeAddress = $addresses->firstWhere('is_default', true);
        $schoolAddress = $addresses->last();

        $requestId = DB::table('requests')->insertGetId([
            'parent_id'         => $parent->parent_id,
            'driver_id'         => $driver->driver_id,
            'school_id'         => $child->school_id,
            'subscription_type' => 'monthly',
            'direction'         => 'both',
            'timing'            => 'MORNING',
            'start_date'        => Carbon::now()->startOfMonth()->toDateString(),
            'end_date'          => Carbon::now()->endOfMonth()->toDateString(),
            'days_count'        => 22,
            'total_price'       => 440.00,
            'pickup_time'       => '07:00:00',
            'dropoff_time'      => '13:30:00',
            'max_waiting_time'  => 15,
            'children_count'    => 1,
            'status'            => 'pending',
            'created_at'        => now(),
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $requestId,
            'child_id'           => $child->child_id,
            'pickup_address_id'  => $homeAddress?->id,
            'home_lat'           => $homeAddress?->lat,
            'home_lng'           => $homeAddress?->lng,
            'home_label'         => $homeAddress?->label,
            'dropoff_address_id' => $child->school_id,
            'school_lat'         => DB::table('schools')->where('id', $child->school_id)->value('lat'),
            'school_lng'         => DB::table('schools')->where('id', $child->school_id)->value('lng'),
            'school_label'       => DB::table('schools')->where('id', $child->school_id)->value('name'),
            'price_per_child'    => 440.00,
            'child_notes'        => 'اختبار E2E',
        ]);
    }

    private function printSummary(): void
    {
        $parent = DB::table('parents')
            ->join('users', 'parents.user_id', '=', 'users.id')
            ->where('users.email', 'parent.e2e@test.com')
            ->select('parents.id as pid', 'users.id as uid', 'users.full_name')
            ->first();

        $driver = DB::table('drivers')
            ->join('users', 'drivers.user_id', '=', 'users.id')
            ->where('users.email', 'driver.e2e@test.com')
            ->select('drivers.id as did', 'users.id as uid', 'users.full_name')
            ->first();

        $request = DB::table('requests')
            ->where('parent_id', $parent->pid)
            ->latest('id')
            ->first();

        $this->command->newLine();
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info('  📋 بيانات اختبار E2E');
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info("  👤 ولي الأمر : {$parent->full_name}");
        $this->command->info("     ID (users): {$parent->uid} | parent_id: {$parent->pid}");
        $this->command->info("     📧 parent.e2e@test.com  /  🔑 12345678");
        $this->command->newLine();
        $this->command->info("  🚗 السائق    : {$driver->full_name}");
        $this->command->info("     ID (drivers): {$driver->did} | user_id: {$driver->uid}");
        $this->command->info("     📧 driver.e2e@test.com   /  🔑 12345678");
        $this->command->newLine();
        $this->command->info("  📄 الطلب ID  : {$request->id}");
        $this->command->info("     الحالة    : {$request->status}");
        $this->command->info('══════════════════════════════════════════════');
    }
}
