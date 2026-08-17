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
        

        echo "🚀 بداية زرع البيانات الشاملة للنظام...\n";

        // تنظيف البيانات والتنظيف الوقائي لتكرار الاستدعاء
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
        // 1. زرع الأدوار (Roles)
        // =========================================================================
        if (DB::table('roles')->count() === 0) {
            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'super_admin', 'display_name' => 'سوبر أدمن'],
                ['id' => 2, 'name' => 'admin', 'display_name' => 'مشرف النظام'],
                ['id' => 3, 'name' => 'parent', 'display_name' => 'ولي أمر'],
                ['id' => 4, 'name' => 'driver', 'display_name' => 'سائق'],
            ]);
        }

        // =========================================================================
        // 2. زرع الجغرافيا والمدارس (Geography & Schools)
        // =========================================================================
        $municipalityId = DB::table('municipalities')->insertGetId([
            'name' => 'طرابلس المركز',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $subMunicipalityId = DB::table('sub_municipalities')->insertGetId([
            'municipality_id' => $municipalityId,
            'name' => 'حي الأندلس',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $zoneId = DB::table('zones')->insertGetId([
            'sub_municipality_id' => $subMunicipalityId,
            'name' => 'منطقة السياحية',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $school1 = School::create([
            'name' => 'مدرسة الجيل الجديد الدولية',
            'lat' => 32.89000000,
            'lng' => 13.17000000,
            'address' => 'حي الأندلس - بالقرب من جامع الأندلس',
            'zone_id' => $zoneId,
            'status' => 'approved'
        ]);

        $school2 = School::create([
            'name' => 'مدرسة الشروق الأهلية',
            'lat' => 32.90500000,
            'lng' => 13.22000000,
            'address' => 'بن عاشور - الشارع الغربي',
            'zone_id' => $zoneId,
            'status' => 'approved'
        ]);

        // =========================================================================
        // 3. حساب مدير النظام (Admin Account)
        // =========================================================================
        $adminUser = User::create([
            'full_name' => 'أحمد المنصوري (الأدمن)',
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
        // 4. أولياء الأمور (2 Parents + Addresses + Children)
        // =========================================================================

        // --- ولي الأمر الأول ---
        $parent1User = User::create([
            'full_name' => 'طه سالم القمودي',
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
            'label' => 'البيت الرئيسي - حي الأندلس خلف مركز المقارحة',
            'lat' => 32.89200000,
            'lng' => 13.17500000,
            'is_default' => true,
            'zone_id' => $zoneId
        ]);

        $child1 = Child::create([
            'parent_id' => $parent1User->id,
            'school_id' => $school1->id,
            'address_id' => $address1->id,
            'full_name' => 'سند طه القمودي',
            'birth_date' => '2016-04-10',
            'gender' => 'male',
            'grade' => 3,
            'medical_notes' => 'حساسية من الغبار'
        ]);

        $child2 = Child::create([
            'parent_id' => $parent1User->id,
            'school_id' => $school1->id,
            'address_id' => $address1->id,
            'full_name' => 'مروة طه القمودي',
            'birth_date' => '2014-08-20',
            'gender' => 'female',
            'grade' => 5
        ]);

        // --- ولي الأمر الثاني ---
        $parent2User = User::create([
            'full_name' => 'محمود علي الورفلي',
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
            'label' => 'منزل العائلة - بن عاشور بالقرب من القنصلية',
            'lat' => 32.90100000,
            'lng' => 13.21500000,
            'is_default' => true,
            'zone_id' => $zoneId
        ]);

        $child3 = Child::create([
            'parent_id' => $parent2User->id,
            'school_id' => $school2->id,
            'address_id' => $address2->id,
            'full_name' => 'أيمن محمود الورفلي',
            'birth_date' => '2017-01-15',
            'gender' => 'male',
            'grade' => 2
        ]);


        // =========================================================================
        // 5. السائقين (2 Drivers + Vehicles)
        // =========================================================================

        // --- السائق الأول ---
        $driver1User = User::create([
            'full_name' => 'عبد السلام المصراتي',
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
            'shift' => 3, // الفترتين
            'subscription_type' => 'both',
            'accepted_gender' => 'both',
            'gender' => 'male',
            'current_lat' => 32.89000000,
            'current_lng' => 13.18000000
        ]);

        $vehicle1 = Vehicle::create([
            'driver_id' => $driver1->id,
            'type' => 'Bus',
            'brand' => 'تويوتا',
            'model' => 'كوستر',
            'year' => 2022,
            'color' => 'أبيض',
            'plate_number' => '5-12345',
            'capacity_manual' => 14,
            'has_ac' => 1,
            'status' => 'Active'
        ]);

        DB::table('driver_zone')->insert([
            'driver_id' => $driver1->id,
            'zone_id' => $zoneId
        ]);

        // --- السائق الثاني ---
        $driver2User = User::create([
            'full_name' => 'طاهر الزنتاني',
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
            'brand' => 'هيونداي',
            'model' => 'H1',
            'year' => 2021,
            'color' => 'رصاصي',
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
        // 6. طلبات الاشتراكات والعقود والاشتراكات النشطة
        // =========================================================================

        // --- طلب اشتراك مقبول ومثبت بعقد للولي الأول مع السائق الأول ---
        $subRequest1 = SubscriptionRequest::create([
            'parent_id' => $parent1->id,
            'driver_id' => $driver1->id,
            'school_id' => $school1->id,
            'subscription_type' => 'monthly',
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
            'notes' => 'يرجى الالتزام بالانتظار أمام البيت',
            'children_count' => 2
        ]);

        DB::table('request_children')->insert([
            [
                'request_id' => $subRequest1->id,
                'child_id' => $child1->id,
                'pickup_address_id' => $address1->id,
                'dropoff_address_id' => $address1->id,
                'home_lat' => 32.89200000,
                'home_lng' => 13.17500000,
                'home_label' => 'البيت الرئيسي',
                'school_lat' => 32.89000000,
                'school_lng' => 13.17000000,
                'school_label' => 'مدرسة الجيل الجديد',
                'price_per_child' => 300.00
            ],
            [
                'request_id' => $subRequest1->id,
                'child_id' => $child2->id,
                'pickup_address_id' => $address1->id,
                'dropoff_address_id' => $address1->id,
                'home_lat' => 32.89200000,
                'home_lng' => 13.17500000,
                'home_label' => 'البيت الرئيسي',
                'school_lat' => 32.89000000,
                'school_lng' => 13.17000000,
                'school_label' => 'مدرسة الجيل الجديد',
                'price_per_child' => 300.00
            ]
        ]);

        // العقد الرسمي بين طه وعبد السلام
        $contract1 = Contract::create([
            'subscription_request_id' => $subRequest1->id,
            'parent_id' => $parent1User->id,
            'driver_id' => $driver1User->id,
            'contract_number' => 'CNT-2026-001',
            'subscription_type' => 'monthly',
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

        // الاشتراكات النشطة الفعلية للاطفال
        $activeSub1 = ActiveSubscription::create([
            'contract_id' => $contract1->id,
            'child_id' => $child1->id,
            'driver_id' => $driver1->id,
            'parent_id' => $parent1User->id,
            'pickup_lat' => 32.89200000,
            'pickup_lng' => 13.17500000,
            'pickup_label' => 'منزل الولي طه',
            'dropoff_lat' => 32.89000000,
            'dropoff_lng' => 13.17000000,
            'dropoff_label' => 'مدرسة الجيل الجديد',
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
            'pickup_label' => 'منزل الولي طه',
            'dropoff_lat' => 32.89000000,
            'dropoff_lng' => 13.17000000,
            'dropoff_label' => 'مدرسة الجيل الجديد',
            'pickup_time' => '07:00:00',
            'dropoff_time' => '14:00:00',
            'status' => 'active'
        ]);


        // --- طلب اشتراك ثاني قيد الانتظار للولي الثاني مع السائق الثاني ---
        $subRequest2 = SubscriptionRequest::create([
            'parent_id' => $parent2->id,
            'driver_id' => $driver2->id,
            'school_id' => $school2->id,
            'subscription_type' => 'monthly',
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
            'notes' => 'الطفل في الصف الثاني ابتدائي',
            'children_count' => 1
        ]);

        DB::table('request_children')->insert([
            'request_id' => $subRequest2->id,
            'child_id' => $child3->id,
            'pickup_address_id' => $address2->id,
            'dropoff_address_id' => $address2->id,
            'home_lat' => 32.90100000,
            'home_lng' => 13.21500000,
            'home_label' => 'منزل محمود الورفلي',
            'school_lat' => 32.90500000,
            'school_lng' => 13.22000000,
            'school_label' => 'مدرسة الشروق الأهلية',
            'price_per_child' => 350.00
        ]);


        // =========================================================================
        // 7. التقييمات والتعليقات (Driver Reviews)
        // =========================================================================
        DriverReview::create([
            'parent_id' => $parent1User->id,
            'driver_id' => $driver1->id,
            'contract_id' => $contract1->id,
            'rating' => 5,
            'comment' => 'سائق ممتاز جداً وخلوق، ملتزم جداً بالمواعيد اليومية ويرعى الأطفال باهتمام.',
            'status' => 'active'
        ]);

        DriverReview::create([
            'parent_id' => $parent2User->id,
            'driver_id' => $driver2->id,
            'contract_id' => null,
            'rating' => 4,
            'comment' => 'قيادة آمنة وسيارات جديدة ومكيفة.',
            'status' => 'active'
        ]);


        // =========================================================================
        // 8. الشكاوى (Complaints)
        // =========================================================================

        // شكوى معالجة تم إغلاقها بأنذار
        Complaint::create([
            'submitted_by' => $parent1->id,
            'against_type' => 'DRIVER',
            'against_id' => $driver1->id,
            'driver_id' => $driver1->id,
            'description' => 'تأخر السائق في التواجد أمام المنزل لمدة 20 دقيقة في رحلة الصباح بتاريخ أمس.',
            'status' => 'completed',
            'action_taken' => 'warning',
            'action_details' => 'تم الاتصال بالسائق وتوجيه إنذار رسمي للتواجد في الموعد المحدد.',
            'resolved_by' => $admin,
            'resolved_at' => now()
        ]);

        // شكوى جديدة معلقة بانتظار مراجعة الأدمن
        Complaint::create([
            'submitted_by' => $parent2->id,
            'against_type' => 'DRIVER',
            'against_id' => $driver2->id,
            'driver_id' => $driver2->id,
            'description' => 'عدم تشغيل مكيف السيارة أثناء رحلة العودة ظهر اليوم رغم حرارة الطقس.',
            'status' => 'pending',
            'action_taken' => 'none'
        ]);


        // =========================================================================
        // 9. غياب الأطفال (Absence Logs)
        // =========================================================================
        AbsenceLog::create([
            'child_id' => $child3->id,
            'absence_date' => Carbon::tomorrow()->toDateString()
        ]);


        // =========================================================================
        // 10. المسارات والرحلات الحية (Routes & Trips & Tracking)
        // =========================================================================

        $route1 = Route::create([
            'contract_id' => $contract1->id,
            'driver_id' => $driver1->id,
            'vehicle_id' => $vehicle1->id,
            'route_name' => 'مسار طه القمودي - صباحي',
            'route_type' => 'Morning',
            'start_time' => '07:00:00',
            'total_distance' => 6.5,
            'estimated_duration' => 20,
            'status' => 'Active'
        ]);

        // رحلة مكتملة لليوم
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
            'trip_type' => 'ذهاب',
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
            'trip_type' => 'ذهاب',
            'scanned_at' => Carbon::today()->setHour(7)->setMinute(25),
            'location_lat' => 32.89000000,
            'location_lng' => 13.17000000,
            'trip_cost' => 15.00
        ]);

        // رحلة حية جارية الآن للرادار
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
        // 11. البيانات المالية (Wallets, Invoices, Requests)
        // =========================================================================

        // محفظة ولي الأمر طه
        DB::table('wallets')->insert([
            'holder_type' => 'App\Models\User',
            'holder_id' => $parent1User->id,
            'name' => 'المحفظة الرئيسية',
            'slug' => 'default',
            'uuid' => Str::uuid()->toString(),
            'balance' => 350,
            'decimal_places' => 2,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // فاتورة للعقد
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

        // طلب شحن محفظة قيد الانتظار
        RechargeRequest::create([
            'parent_id' => $parent2User->id,
            'amount' => 200.00,
            'payment_method' => 'Bank Transfer',
            'status' => 'pending',
            'reference_number' => 'REF-998877'
        ]);

        // طلب سحب أرباح للسائق الأول
        WithdrawalRequest::create([
            'driver_id' => $driver1->id,
            'amount' => 450.00,
            'wallet_balance_at_request' => 500.00,
            'status' => 'pending',
            'payment_method_details' => ['bank' => 'مصرف الجمهورية', 'account' => 'LY3300100200300400']
        ]);

        
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        echo "🎉 تم زرع كافة بيانات النظام والسيناريوهات بنجاح وحسابات الاختبار جاهزة للاستخدام!\n";
    }
}
