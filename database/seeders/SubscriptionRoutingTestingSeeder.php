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
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\Trip;
use App\Enums\driver\DriverShift;

class SubscriptionRoutingTestingSeeder extends Seeder
{
    public function run(): void
    {
        // تنظيف البيانات السابقة للاختبار إن وجدت لضمان إمكانية تشغيل الـ Seeder أكثر من مرة بدون مشاكل
        $testEmails = [
            'driver.experienced@darby.test',
            'driver.newbie@darby.test',
            'parent.testing@darby.test',
        ];

        $oldUserIds = DB::table('users')->whereIn('email', $testEmails)->pluck('id');
        if ($oldUserIds->isNotEmpty()) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            $driverIds = DB::table('drivers')->whereIn('user_id', $oldUserIds)->pluck('id');
            $parentIds = DB::table('parents')->whereIn('user_id', $oldUserIds)->pluck('id');

            DB::table('driver_seat_slots')->whereIn('driver_id', $driverIds)->delete();
            DB::table('active_subscriptions')->whereIn('driver_id', $driverIds)->orWhereIn('parent_id', $parentIds)->delete();
            DB::table('trips')->whereIn('driver_id', $driverIds)->delete();
            DB::table('routes')->whereIn('driver_id', $driverIds)->delete();
            DB::table('vehicles')->whereIn('driver_id', $driverIds)->delete();
            DB::table('contracts')->whereIn('driver_id', $oldUserIds)->orWhereIn('parent_id', $oldUserIds)->delete();

            $reqIds = DB::table('requests')->whereIn('driver_id', $driverIds)->orWhereIn('parent_id', $parentIds)->pluck('id');
            DB::table('request_children')->whereIn('request_id', $reqIds)->delete();
            DB::table('requests')->whereIn('id', $reqIds)->delete();

            $childIds = DB::table('children')->whereIn('parent_id', $parentIds)->pluck('id');
            DB::table('children')->whereIn('id', $childIds)->delete();

            DB::table('drivers')->whereIn('id', $driverIds)->delete();
            DB::table('parents')->whereIn('id', $parentIds)->delete();
            DB::table('users')->whereIn('id', $oldUserIds)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // 0. التأكد من وجود الأدوار (Roles)
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير النظام'],
            ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
            ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
        ]);

        // 1. المدرسة الافتراضية
        $schoolId = DB::table('schools')->insertGetId([
            'name'       => 'مدرسة طرابلس النظيفة النموذجية',
            'address'    => 'شارع عمر المختار، طرابلس',
            'lat'        => 32.8870,
            'lng'        => 13.1910,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // =========================================================================
        // 2. إنشاء السائق الأول: خبير (لديه مسارات ورحلات سابقة وحجوزات مقاعد)
        // =========================================================================
        $userDriver1 = User::create([
            'full_name'     => 'الكابتن عبد السلام الخبير',
            'email'         => 'driver.experienced@darby.test',
            'phone_number'  => '0912222222',
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $driver1 = Driver::create([
            'user_id'           => $userDriver1->id,
            'national_id'       => 'NAT-111111111',
            'license_number'    => 'LIC-111111111',
            'license_expiry'    => now()->addYears(3)->format('Y-m-d'),
            'status'            => 'Approved',
            'subscription_type' => 'both',
            'accepted_gender'   => 'both',
            'gender'            => 'male',
            'shift'             => DriverShift::BOTH,
            'morning_go'        => true,
            'morning_return'    => true,
            'afternoon_go'      => true,
            'afternoon_return'  => true,
            'current_lat'       => 32.8800,
            'current_lng'       => 13.1800,
        ]);

        // سيارة السائق 1
        $vehicle1Id = DB::table('vehicles')->insertGetId([
            'driver_id'       => $driver1->id,
            'plate_number'    => '10-54321',
            'brand'           => 'Toyota',
            'model'           => 'Coaster',
            'year'            => '2023',
            'color'           => 'White',
            'type'            => 'Bus',
            'capacity_manual' => 4, // سعة كرت 4 مقاعد للاختبار الدقيق
            'is_verified'     => 1,
            'has_ac'          => 1,
            'status'          => 'Active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // مقاعد السائق 1 (حجز مقعد 1 من أصل 4)
        DriverSeatSlot::create([
            'driver_id'      => $driver1->id,
            'slot'           => DriverSeatSlot::MORNING_GO,
            'total_seats'    => 4,
            'reserved_seats' => 1,
        ]);
        DriverSeatSlot::create([
            'driver_id'      => $driver1->id,
            'slot'           => DriverSeatSlot::MORNING_RETURN,
            'total_seats'    => 4,
            'reserved_seats' => 1,
        ]);

        // =========================================================================
        // 3. إنشاء السائق الثاني: جديد (ليس لديه أي مسارات أو رحلات سابقاً - فترة صباحية ذهاب فقط)
        // =========================================================================
        $userDriver2 = User::create([
            'full_name'     => 'الكابتن محمد الجديد',
            'email'         => 'driver.newbie@darby.test',
            'phone_number'  => '0913333333',
            'password_hash' => Hash::make('password123'),
            'role_id'       => 2,
            'is_active'     => 1,
        ]);

        $driver2 = Driver::create([
            'user_id'           => $userDriver2->id,
            'national_id'       => 'NAT-222222222',
            'license_number'    => 'LIC-222222222',
            'license_expiry'    => now()->addYears(2)->format('Y-m-d'),
            'status'            => 'Approved',
            'subscription_type' => 'monthly',
            'accepted_gender'   => 'both',
            'gender'            => 'male',
            'shift'             => DriverShift::MORNING,
            'morning_go'        => true,  // يعمل صباحي ذهاب فقط
            'morning_return'    => false,
            'afternoon_go'      => false,
            'afternoon_return'  => false,
            'current_lat'       => 32.8700,
            'current_lng'       => 13.1700,
        ]);

        // سيارة السائق 2 (سعة 2 مقعد فقط)
        DB::table('vehicles')->insert([
            'driver_id'       => $driver2->id,
            'plate_number'    => '5-99887',
            'brand'           => 'Hyundai',
            'model'           => 'H1',
            'year'            => '2022',
            'color'           => 'Silver',
            'type'            => 'Van',
            'capacity_manual' => 2,
            'is_verified'     => 1,
            'has_ac'          => 1,
            'status'          => 'Active',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // مقاعد السائق 2 (متاحة بالكامل 2/2)
        DriverSeatSlot::create([
            'driver_id'      => $driver2->id,
            'slot'           => DriverSeatSlot::MORNING_GO,
            'total_seats'    => 2,
            'reserved_seats' => 0,
        ]);

        // =========================================================================
        // 4. إنشاء ولي الأمر الشامل والاختبارات
        // =========================================================================
        $userParent = User::create([
            'full_name'     => 'الأستاذ أحمد ولي الأمر الاختباري',
            'email'         => 'parent.testing@darby.test',
            'phone_number'  => '0921111111',
            'password_hash' => Hash::make('password123'),
            'role_id'       => 3,
            'is_active'     => 1,
        ]);

        $parentModel = ParentModel::create([
            'user_id'    => $userParent->id,
            'is_trusted' => 1,
        ]);

        // عناوين اختبارية لربط request_children
        $homeAddressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $userParent->id,
            'label'      => 'منزل الاختبار الرئيسية',
            'lat'        => 32.8840,
            'lng'        => 13.1870,
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schoolAddressId = DB::table('addresses')->insertGetId([
            'parent_id'  => $userParent->id,
            'label'      => 'مدرسة الاختبار الرئيسية',
            'lat'        => 32.8870,
            'lng'        => 13.1910,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -------------------------------------------------------------------------
        // الحالة A: الطفل 1 (علي) — اشتراك نشط ومسار ورحلات سابقة للسائق 1
        // -------------------------------------------------------------------------
        $child1 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'علي أحمد (اشتراك نشط مسبقاً)',
            'gender'     => 'male',
            'grade'      => 4,
            'birth_date' => '2016-03-15',
        ]);

        // 1. إنشاء الطلب المقبول أولاً للاشتراك النشط
        $acceptedReq = SubscriptionRequest::create([
            'parent_id'                => $parentModel->id,
            'driver_id'                => $driver1->id,
            'school_id'                => $schoolId,
            'timing'                   => 'MORNING',
            'direction'                => 'both',
            'status'                   => 'accepted',
            'subscription_type'        => 'monthly',
            'children_count'           => 1,
            'children_acceptance_mode' => 'all',
        ]);

        // 2. عقد الطفل 1 مع السائق 1
        $contract1 = Contract::create([
            'subscription_request_id' => $acceptedReq->id,
            'parent_id'               => $userParent->id,
            'driver_id'               => $userDriver1->id,
            'contract_number'         => 'DRBY-ACTIVE-001',
            'subscription_type'       => 'monthly',
            'direction'               => 'both',
            'timing'                  => 'MORNING',
            'pickup_time'             => '07:00:00',
            'dropoff_time'            => '14:00:00',
            'max_waiting_time'        => 15,
            'start_date'              => now()->subDays(10)->format('Y-m-d'),
            'end_date'                => now()->addDays(20)->format('Y-m-d'),
            'days_count'              => 22,
            'total_price'             => 250.00,
            'clauses'                 => [],
            'status'                  => 'active',
        ]);

        // مسار السائق 1 النشط
        $route1 = RouteModel::create([
            'contract_id'        => $contract1->id,
            'driver_id'          => $driver1->id,
            'vehicle_id'         => $vehicle1Id,
            'route_name'         => 'مسار طرابلس الصباحي (نشط)',
            'route_type'         => 'Morning',
            'start_time'         => '07:00:00',
            'total_distance'     => 8.50,
            'estimated_duration' => 25,
            'optimized_points'   => json_encode([
                [
                    'node_id'      => 'NODE_ALI_PICKUP',
                    'child_id'     => $child1->id,
                    'type'         => 'pickup',
                    'lat'          => 32.8820,
                    'lng'          => 13.1850,
                    'service_time' => 3,
                    'bell_time'    => '07:45',
                    'status'       => 'pending',
                ],
                [
                    'node_id'      => 'NODE_ALI_DROPOFF',
                    'child_id'     => $child1->id,
                    'type'         => 'dropoff',
                    'lat'          => 32.8870,
                    'lng'          => 13.1910,
                    'service_time' => 2,
                    'bell_time'    => '07:45',
                    'status'       => 'pending',
                ]
            ]),
            'status'             => 'Active',
        ]);

        // ربط اشتراك الطفل 1 بالمسار 1
        ActiveSubscription::create([
            'contract_id'        => $contract1->id,
            'parent_id'          => $userParent->id,
            'driver_id'          => $driver1->id,
            'child_id'           => $child1->id,
            'route_id'           => $route1->id,
            'pickup_lat'         => 32.8820,
            'pickup_lng'         => 13.1850,
            'dropoff_lat'        => 32.8870,
            'dropoff_lng'        => 13.1910,
            'pickup_time'        => '07:05:00',
            'dropoff_time'       => '07:25:00',
            'status'             => 'active',
        ]);

        // رحلة سابقة ومكتملة للسائق 1
        Trip::create([
            'route_id'             => $route1->id,
            'driver_id'            => $driver1->id,
            'trip_type'            => 'Morning',
            'trip_date'            => now()->subDay()->format('Y-m-d'),
            'scheduled_start_time' => '07:00:00',
            'actual_start_time'    => '07:02:00',
            'status'               => 'completed',
        ]);

        // -------------------------------------------------------------------------
        // الحالة B: الطفل 2 (عمر) — طلب معلق قياسي مطبق على السائق 1 (حالة نجاح)
        // -------------------------------------------------------------------------
        $child2 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'عمر أحمد (طلب معلق - موافق القيود والمسار)',
            'gender'     => 'male',
            'grade'      => 2,
            'birth_date' => '2018-06-10',
        ]);

        $req2 = SubscriptionRequest::create([
            'parent_id'                => $parentModel->id,
            'driver_id'                => $driver1->id,
            'school_id'                => $schoolId,
            'subscription_type'        => 'monthly',
            'direction'                => 'both',
            'timing'                   => 'MORNING',
            'start_date'               => now()->addDays(2)->format('Y-m-d'),
            'end_date'                 => now()->addDays(32)->format('Y-m-d'),
            'days_count'               => 22,
            'total_price'              => 200.00,
            'status'                   => 'pending',
            'notes'                    => 'طلب قياسي للاختبار والتوصية',
            'children_count'           => 1,
            'children_acceptance_mode' => 'all',
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $req2->id,
            'child_id'           => $child2->id,
            'pickup_address_id'  => $homeAddressId,
            'dropoff_address_id' => $schoolId,
            'home_lat'           => 32.8840,
            'home_lng'           => 13.1870,
            'home_label'         => 'بيت عمر',
            'school_lat'         => 32.8870,
            'school_lng'         => 13.1910,
            'school_label'       => 'مدرسة طرابلس',
            'price_per_child'    => 200.00,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // -------------------------------------------------------------------------
        // الحالة C: الطفل 3 (فاطمة) — طلب معلق يطلب مسائي من سائق 2 لا يعمل مسائي
        // -------------------------------------------------------------------------
        $child3 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'فاطمة أحمد (طلب معلق - فترة غير مدعومة لدى السائق)',
            'gender'     => 'female',
            'grade'      => 5,
            'birth_date' => '2015-09-20',
        ]);

        $req3 = SubscriptionRequest::create([
            'parent_id'                => $parentModel->id,
            'driver_id'                => $driver2->id, // السائق 2 يشتغل صباحي ذهاب فقط
            'school_id'                => $schoolId,
            'subscription_type'        => 'monthly',
            'direction'                => 'both',
            'timing'                   => 'EVENING', // طلب مسائي!
            'start_date'               => now()->addDays(2)->format('Y-m-d'),
            'end_date'                 => now()->addDays(32)->format('Y-m-d'),
            'days_count'               => 22,
            'total_price'              => 220.00,
            'status'                   => 'pending',
            'notes'                    => 'اختبار رفض الفلاتر بسبب الفترة',
            'children_count'           => 1,
            'children_acceptance_mode' => 'all',
        ]);

        DB::table('request_children')->insert([
            'request_id'         => $req3->id,
            'child_id'           => $child3->id,
            'pickup_address_id'  => $homeAddressId,
            'dropoff_address_id' => $schoolId,
            'home_lat'           => 32.8750,
            'home_lng'           => 13.1750,
            'price_per_child'    => 220.00,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // -------------------------------------------------------------------------
        // الحالة D: الأطفال 4 و 5 (سارة وحسن) — طلب متعدد أطفال بتجاوز المقاعد وقبول فردي
        // -------------------------------------------------------------------------
        $child4 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'سارة أحمد (طلب أطفال متعددين - 1)',
            'gender'     => 'female',
            'grade'      => 1,
            'birth_date' => '2019-01-01',
        ]);

        $child5 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'حسن أحمد (طلب أطفال متعددين - 2)',
            'gender'     => 'male',
            'grade'      => 3,
            'birth_date' => '2017-04-12',
        ]);

        $child6 = Child::create([
            'parent_id'  => $parentModel->id,
            'school_id'  => $schoolId,
            'full_name'  => 'زياد أحمد (طلب أطفال متعددين - 3)',
            'gender'     => 'male',
            'grade'      => 6,
            'birth_date' => '2014-08-11',
        ]);

        $req4 = SubscriptionRequest::create([
            'parent_id'                => $parentModel->id,
            'driver_id'                => $driver2->id, // السائق 2 لديه مقعدين متاحين فقط!
            'school_id'                => $schoolId,
            'subscription_type'        => 'monthly',
            'direction'                => 'go',
            'timing'                   => 'MORNING',
            'start_date'               => now()->addDays(2)->format('Y-m-d'),
            'end_date'                 => now()->addDays(32)->format('Y-m-d'),
            'days_count'               => 22,
            'total_price'              => 500.00,
            'status'                   => 'pending',
            'notes'                    => 'طلب 3 أطفال لسائق لديه مقعدان بوضع قبول فردي',
            'children_count'           => 3,
            'children_acceptance_mode' => 'individual', // قبول فردي!
        ]);

        foreach ([$child4, $child5, $child6] as $ch) {
            DB::table('request_children')->insert([
                'request_id'         => $req4->id,
                'child_id'           => $ch->id,
                'pickup_address_id'  => $homeAddressId,
                'dropoff_address_id' => $schoolId,
                'home_lat'           => 32.8710,
                'home_lng'           => 13.1720,
                'price_per_child'    => 166.66,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }
}
