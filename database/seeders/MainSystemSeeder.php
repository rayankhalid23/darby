<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Admin\Admin;
use App\Models\Parent\ParentModel;
use App\Models\Parent\Address;
use App\Models\Parent\School;
use App\Models\Parent\Child;
use App\Models\Driver\Driver;
use App\Models\Driver\Vehicle;
use App\Models\Driver\DriverSeatSlot;
use Carbon\Carbon;

/**
 * MainSystemSeeder - السيدر الشامل لبيانات النظام الأساسية
 *
 * يشمل:
 *   1. الأدوار (Roles)
 *   2. الجغرافيا الكاملة: بلديات → بلديات فرعية → مناطق (طرابلس)
 *   3. المدارس (8 مدارس)
 *   4. مدير النظام (Admin)
 *   5. مشرفَان (Supervisors)
 *   6. 3 أولياء أمور + عناوين + أطفال
 *   7. 3 سائقون + مركبات + وثائق + سلوتات المقاعد
 *
 * كلمة المرور الموحدة لجميع الحسابات: Darby2026
 */
class MainSystemSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->cleanOldData();

        $this->command->info('🌱 بدء زرع بيانات النظام الأساسية...');

        $this->seedRoles();
        $zones   = $this->seedGeography();
        $schools = $this->seedSchools($zones);
        $adminUser = $this->seedAdmin();
        $this->seedSupervisors($adminUser);
        $this->seedParents($zones, $schools);
        $this->seedDrivers($zones, $adminUser);

        Schema::enableForeignKeyConstraints();

        $this->command->info('');
        $this->command->info('✅ تم زرع جميع بيانات النظام بنجاح!');
        $this->command->info('📋 كلمة المرور الموحدة لجميع الحسابات: Darby2026');
        $this->command->info('');
        $this->command->info('📧 حسابات الدخول:');
        $this->command->info('   Admin     → admin@darby.ly');
        $this->command->info('   Sup 1     → supervisor1@darby.ly');
        $this->command->info('   Sup 2     → supervisor2@darby.ly');
        $this->command->info('   Parent 1  → parent1@darby.ly');
        $this->command->info('   Parent 2  → parent2@darby.ly');
        $this->command->info('   Parent 3  → parent3@darby.ly');
        $this->command->info('   Driver 1  → driver1@darby.ly  (معتمد)');
        $this->command->info('   Driver 2  → driver2@darby.ly  (معتمد)');
        $this->command->info('   Driver 3  → driver3@darby.ly  (قيد الانتظار)');
    }

    // =========================================================
    // تنظيف البيانات القديمة
    // =========================================================
    private function cleanOldData(): void
    {
        $emails = [
            'admin@darby.ly',
            'supervisor1@darby.ly', 'supervisor2@darby.ly',
            'parent1@darby.ly', 'parent2@darby.ly', 'parent3@darby.ly',
            'driver1@darby.ly', 'driver2@darby.ly', 'driver3@darby.ly',
        ];

        $oldUserIds = User::withTrashed()->whereIn('email', $emails)->pluck('id');
        if ($oldUserIds->count() === 0) return;

        $oldDriverIds = DB::table('drivers')->whereIn('user_id', $oldUserIds)->pluck('id');
        if ($oldDriverIds->count() > 0) {
            DB::table('driver_seat_slots')->whereIn('driver_id', $oldDriverIds)->delete();
            DB::table('driver_zone')->whereIn('driver_id', $oldDriverIds)->delete();
            DB::table('driver_documents')->whereIn('driver_id', $oldDriverIds)->delete();
            DB::table('vehicles')->whereIn('driver_id', $oldDriverIds)->delete();
            DB::table('drivers')->whereIn('user_id', $oldUserIds)->delete();
        }

        $oldParentIds = DB::table('parents')->whereIn('user_id', $oldUserIds)->pluck('id');
        if ($oldParentIds->count() > 0) {
            DB::table('children')->whereIn('parent_id', $oldParentIds)->delete();
            DB::table('addresses')->whereIn('parent_id', $oldParentIds)->delete();
            DB::table('parents')->whereIn('user_id', $oldUserIds)->delete();
        }

        DB::table('admins')->whereIn('user_id', $oldUserIds)->delete();
        User::withTrashed()->whereIn('id', $oldUserIds)->forceDelete();

        $this->command->info('   🗑 تم تنظيف البيانات القديمة');
    }

    // =========================================================
    // 1. الأدوار
    // =========================================================
    private function seedRoles(): void
    {
        $roles = [
            [
                'id' => 1, 'name' => 'admin', 'display_name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة وغير مقيدة لإدارة المنصة بالكامل.',
                'permissions' => json_encode([
                    'all' => true,
                    'dashboard'  => ['view', 'stats', 'active_trips'],
                    'admins'     => ['view', 'create', 'update', 'delete'],
                    'drivers'    => ['view', 'approve', 'reject', 'update', 'delete'],
                    'parents'    => ['view', 'update', 'delete'],
                    'children'   => ['view', 'update'],
                    'complaints' => ['view', 'review', 'resolve'],
                    'financial'  => ['view_summary', 'manage_wallets', 'recharges', 'withdrawals'],
                    'reports'    => ['kpi', 'financial', 'trips', 'export'],
                    'geography'  => ['municipalities', 'sub_municipalities', 'zones'],
                    'schools'    => ['view', 'create', 'update', 'delete'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => 2, 'name' => 'supervisor', 'display_name' => 'مشرف',
                'description' => 'صلاحيات إشرافية لمراجعة السائقين ومراقبة الرحلات وحل الشكاوى.',
                'permissions' => json_encode([
                    'dashboard'      => ['view', 'stats', 'active_trips'],
                    'drivers'        => ['view', 'review', 'pending_changes', 'update'],
                    'schools'        => ['view', 'create', 'update'],
                    'zones'          => ['view', 'create', 'update'],
                    'complaints'     => ['view', 'review'],
                    'driver_reviews' => ['view', 'delete'],
                    'trips'          => ['view', 'monitor_live'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => 3, 'name' => 'parent', 'display_name' => 'ولي أمر',
                'description' => 'حساب ولي الأمر لإضافة الأبناء وإدارة الاشتراكات وتتبع الرحلات.',
                'permissions' => json_encode([
                    'profile'       => ['view', 'update', 'change_email'],
                    'children'      => ['view', 'create', 'update', 'delete', 'set_absence', 'confirm_pickup'],
                    'addresses'     => ['view', 'create', 'update', 'delete'],
                    'drivers'       => ['search', 'view_profile', 'review'],
                    'subscriptions' => ['request', 'view_active', 'cancel'],
                    'contracts'     => ['view', 'accept', 'reject', 'pdf'],
                    'trips'         => ['view_active', 'track_live', 'timeline', 'history', 'dispute'],
                    'wallet'        => ['view_balance', 'recharge', 'hold_trip'],
                    'complaints'    => ['create', 'view', 'update', 'delete'],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => 4, 'name' => 'driver', 'display_name' => 'سائق',
                'description' => 'حساب السائق لإدارة المركبة والوثائق واستقبال طلبات التوصيل وتنفيذ الرحلات.',
                'permissions' => json_encode([
                    'profile'       => ['view', 'update', 'complete_profile', 'update_vehicle', 'update_legal_data'],
                    'preferences'   => ['view', 'update', 'manage_zones'],
                    'subscriptions' => ['view_requests', 'accept', 'reject', 'feasibility_check', 'view_active', 'cancel'],
                    'routes'        => ['view', 'create', 'update', 'delete', 'reorder_stops', 'assign_subscription'],
                    'trips'         => ['start', 'live_tracking', 'update_location', 'pickup', 'absent', 'dropoff', 'verify_qr', 'complete'],
                    'wallet'        => ['view_balance', 'withdraw'],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }

        $this->command->info('   ✓ تم زرع 4 أدوار');
    }

    // =========================================================
    // 2. الجغرافيا الكاملة لطرابلس
    // =========================================================
    private function seedGeography(): array
    {
        $geography = [
            'طرابلس المركز' => [
                'طرابلس المدينة' => [
                    'بن عاشور', 'الظهرة', 'زاوية الدهماني', 'فشلوم',
                    'المنصورة', 'أبو نواس', 'طريق السور', 'الـ4 شوارع زناتة',
                ],
                'النوفليين' => [
                    'النوفليين', 'راس حسن', 'العزيزية',
                ],
                'بن غشير' => [
                    'بن غشير', 'قصر بن غشير', 'طريق المطار',
                ],
            ],
            'حي الأندلس' => [
                'حي الأندلس المركز' => [
                    'حي الأندلس', 'قرقارش', 'غوط الشعال', 'السراج', 'السياحية',
                ],
                'قرجي' => [
                    'قرجي', 'الأصابعة',
                ],
            ],
            'سوق الجمعة' => [
                'سوق الجمعة المركز' => [
                    'سوق الجمعة', 'عرادة', 'شرفة الملاحة', 'الهانى', 'طريق الشط - سوق الجمعة',
                ],
                'تاجوراء' => [
                    'تاجوراء', 'تاجوراء الغربية', 'تاجوراء الشرقية', 'النوفليين الشرقية',
                ],
            ],
            'بوسليم' => [
                'بوسليم المركز' => [
                    'بوسليم', 'الهضبة الخضراء', 'السبعة', 'الدريبي',
                ],
                'صلاح الدين' => [
                    'صلاح الدين', 'عين زارة', 'السواني',
                ],
            ],
            'جنزور' => [
                'جنزور المركز' => [
                    'جنزور', 'الجمايل', 'صرمان',
                ],
            ],
        ];

        $savedZones = [];
        $totalZones = 0;

        foreach ($geography as $muniName => $subMunis) {
            $muni = DB::table('municipalities')->where('name', $muniName)->first();
            if (!$muni) {
                $muniId = DB::table('municipalities')->insertGetId([
                    'name' => $muniName, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $muniId = $muni->id;
            }

            foreach ($subMunis as $subName => $zoneNames) {
                $subMuni = DB::table('sub_municipalities')
                    ->where('name', $subName)
                    ->where('municipality_id', $muniId)
                    ->first();

                if (!$subMuni) {
                    $subMuniId = DB::table('sub_municipalities')->insertGetId([
                        'municipality_id' => $muniId,
                        'name'            => $subName,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                } else {
                    $subMuniId = $subMuni->id;
                }

                foreach ($zoneNames as $zoneName) {
                    $zone = DB::table('zones')->where('name', $zoneName)->first();
                    if (!$zone) {
                        $zoneId = DB::table('zones')->insertGetId([
                            'sub_municipality_id' => $subMuniId,
                            'name'                => $zoneName,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    } else {
                        $zoneId = $zone->id;
                    }
                    $savedZones[$zoneName] = $zoneId;
                    $totalZones++;
                }
            }
        }

        $this->command->info("   ✓ تم تجهيز الجغرافيا: 5 بلديات، 10 بلديات فرعية، {$totalZones} منطقة");
        return $savedZones;
    }

    // =========================================================
    // 3. المدارس
    // =========================================================
    private function seedSchools(array $zones): array
    {
        $get = fn(string $name) => $zones[$name] ?? (count($zones) > 0 ? reset($zones) : 1);

        $schoolsData = [
            [
                'name'    => 'مدرسة الجيل الجديد الدولية',
                'lat'     => 32.89200, 'lng' => 13.16800,
                'address' => 'حي الأندلس - بالقرب من جامع الأندلس',
                'zone_id' => $get('حي الأندلس'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة الشروق الأهلية',
                'lat'     => 32.90500, 'lng' => 13.22100,
                'address' => 'بن عاشور - الشارع الغربي',
                'zone_id' => $get('بن عاشور'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة طرابلس المركزية',
                'lat'     => 32.88700, 'lng' => 13.19200,
                'address' => 'زاوية الدهماني - شارع الاستقلال',
                'zone_id' => $get('زاوية الدهماني'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة النور الابتدائية',
                'lat'     => 32.89900, 'lng' => 13.20500,
                'address' => 'النوفليين - بجانب المستشفى المركزي',
                'zone_id' => $get('النوفليين'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة الأمل للتعليم',
                'lat'     => 32.88100, 'lng' => 13.18300,
                'address' => 'فشلوم - شارع البستان',
                'zone_id' => $get('فشلوم'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة الرواد الأهلية',
                'lat'     => 32.87600, 'lng' => 13.24800,
                'address' => 'سوق الجمعة - بالقرب من دوار قاطوشة',
                'zone_id' => $get('سوق الجمعة'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة المستقبل المتكاملة',
                'lat'     => 32.86400, 'lng' => 13.16000,
                'address' => 'بوسليم - الطريق الرئيسي',
                'zone_id' => $get('بوسليم'), 'status' => 'active',
            ],
            [
                'name'    => 'مدرسة تاجوراء الإسلامية',
                'lat'     => 32.89300, 'lng' => 13.36500,
                'address' => 'تاجوراء - شارع الجمهورية',
                'zone_id' => $get('تاجوراء'), 'status' => 'active',
            ],
        ];

        $savedSchools = [];
        foreach ($schoolsData as $data) {
            $school = School::updateOrCreate(['name' => $data['name']], $data);
            $savedSchools[$data['name']] = $school;
        }

        $this->command->info('   ✓ تم زرع 8 مدارس');
        return $savedSchools;
    }

    // =========================================================
    // 4. مدير النظام
    // =========================================================
    private function seedAdmin(): User
    {
        $adminUser = User::create([
            'full_name'         => 'أحمد المنصوري',
            'email'             => 'admin@darby.ly',
            'phone_number'      => '0910000001',
            'password_hash'     => Hash::make('Darby2026'),
            'role_id'           => 1,
            'is_active'         => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        Admin::create(['user_id' => $adminUser->id, 'created_by' => $adminUser->id]);

        $this->command->info('   ✓ مدير النظام → admin@darby.ly');
        return $adminUser;
    }

    // =========================================================
    // 5. المشرفون
    // =========================================================
    private function seedSupervisors(User $adminUser): void
    {
        $password = Hash::make('Darby2026');

        $supervisors = [
            ['full_name' => 'علي عمر المشرف الترهوني',  'email' => 'supervisor1@darby.ly', 'phone_number' => '0911000001'],
            ['full_name' => 'فاطمة محمد العريبي',        'email' => 'supervisor2@darby.ly', 'phone_number' => '0922000002'],
        ];

        foreach ($supervisors as $sup) {
            $user = User::create([
                'full_name'         => $sup['full_name'],
                'email'             => $sup['email'],
                'phone_number'      => $sup['phone_number'],
                'password_hash'     => $password,
                'role_id'           => 2,
                'is_active'         => 1,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
            Admin::create(['user_id' => $user->id, 'created_by' => $adminUser->id]);
        }

        $this->command->info('   ✓ 2 مشرف → supervisor1@darby.ly, supervisor2@darby.ly');
    }

    // =========================================================
    // 6. أولياء الأمور + العناوين + الأطفال
    // =========================================================
    private function seedParents(array $zones, array $schools): void
    {
        $password = Hash::make('Darby2026');
        $get      = fn(string $name) => $zones[$name] ?? (count($zones) > 0 ? reset($zones) : 1);

        $schoolsList = array_values($schools);
        $school1 = $schoolsList[0] ?? School::first();
        $school2 = $schoolsList[1] ?? $school1;
        $school3 = $schoolsList[2] ?? $school1;

        // ─── ولي الأمر الأول ────────────────────────────────
        $p1User = User::create([
            'full_name'         => 'طه سالم القموضي',
            'email'             => 'parent1@darby.ly',
            'phone_number'      => '0913000001',
            'alternative_phone' => '0211234567',
            'password_hash'     => $password,
            'role_id'           => 3,
            'is_active'         => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $p1 = ParentModel::create(['user_id' => $p1User->id, 'is_trusted' => 1]);

        $addr1a = Address::create([
            'parent_id' => $p1->id, 'label' => 'المنزل الرئيسي',
            'lat' => 32.89200, 'lng' => 13.17500,
        ]);
        Address::create([
            'parent_id' => $p1->id, 'label' => 'منزل الجد',
            'lat' => 32.88500, 'lng' => 13.19800,
        ]);

        Child::create([
            'parent_id'   => $p1->id, 'school_id'   => $school1->id,
            'address_id'  => $addr1a->id, 'full_name'   => 'سند طه القموضي',
            'birth_date'  => '2016-04-10', 'gender'      => 'male',
            'grade'       => 3, 'school_stage' => 'primary',
            'medical_notes' => 'حساسية من الغبار', 'notification_radius' => 500,
        ]);
        Child::create([
            'parent_id'   => $p1->id, 'school_id'   => $school1->id,
            'address_id'  => $addr1a->id, 'full_name'   => 'مروة طه القموضي',
            'birth_date'  => '2014-08-20', 'gender'      => 'female',
            'grade'       => 5, 'school_stage' => 'primary',
            'notification_radius' => 500,
        ]);

        // ─── ولي الأمر الثاني ───────────────────────────────
        $p2User = User::create([
            'full_name'         => 'محمود علي الورفلي',
            'email'             => 'parent2@darby.ly',
            'phone_number'      => '0924000002',
            'alternative_phone' => '0217654321',
            'password_hash'     => $password,
            'role_id'           => 3,
            'is_active'         => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $p2 = ParentModel::create(['user_id' => $p2User->id, 'is_trusted' => 1]);

        $addr2a = Address::create([
            'parent_id' => $p2->id, 'label' => 'المنزل',
            'lat' => 32.90100, 'lng' => 13.21500,
        ]);
        Address::create([
            'parent_id' => $p2->id, 'label' => 'مقر العمل',
            'lat' => 32.88800, 'lng' => 13.19000,
        ]);

        Child::create([
            'parent_id'   => $p2->id, 'school_id'   => $school2->id,
            'address_id'  => $addr2a->id, 'full_name'   => 'أيمن محمود الورفلي',
            'birth_date'  => '2017-01-15', 'gender'      => 'male',
            'grade'       => 2, 'school_stage' => 'primary',
            'notification_radius' => 400,
        ]);

        // ─── ولي الأمر الثالث ───────────────────────────────
        $p3User = User::create([
            'full_name'     => 'خديجة مصطفى البركي',
            'email'         => 'parent3@darby.ly',
            'phone_number'  => '0935000003',
            'password_hash' => $password,
            'role_id'       => 3,
            'is_active'     => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $p3 = ParentModel::create(['user_id' => $p3User->id, 'is_trusted' => 1]);

        $addr3a = Address::create([
            'parent_id' => $p3->id, 'label' => 'البيت',
            'lat' => 32.89900, 'lng' => 13.20300,
        ]);

        Child::create([
            'parent_id'   => $p3->id, 'school_id'   => $school3->id,
            'address_id'  => $addr3a->id, 'full_name'   => 'ريم خالد البركي',
            'birth_date'  => '2015-06-05', 'gender'      => 'female',
            'grade'       => 4, 'school_stage' => 'primary',
            'notification_radius' => 600,
        ]);
        Child::create([
            'parent_id'   => $p3->id, 'school_id'   => $school3->id,
            'address_id'  => $addr3a->id, 'full_name'   => 'يوسف خالد البركي',
            'birth_date'  => '2013-11-22', 'gender'      => 'male',
            'grade'       => 6, 'school_stage' => 'primary',
            'notification_radius' => 600,
        ]);

        $this->command->info('   ✓ 3 أولياء أمور + 5 عناوين + 5 أطفال');
    }

    // =========================================================
    // 7. السائقون + المركبات + الوثائق + سلوتات المقاعد
    // =========================================================
    private function seedDrivers(array $zones, User $adminUser): void
    {
        $password = Hash::make('Darby2026');
        $get      = fn(string $name) => $zones[$name] ?? (count($zones) > 0 ? reset($zones) : 1);

        // ─── السائق الأول (معتمد - صباحي) ──────────────────
        $d1User = User::create([
            'full_name'         => 'عبد السلام يوسف المصراتي',
            'email'             => 'driver1@darby.ly',
            'phone_number'      => '0921000001',
            'password_hash'     => $password,
            'role_id'           => 4,
            'is_active'         => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $d1 = Driver::create([
            'user_id'                => $d1User->id,
            'national_id'            => '119900112233',
            'license_number'         => 'DL-998877',
            'license_expiry'         => '2028-12-31',
            'status'                 => 'Approved',
            'gender'                 => 'male',
            'shift'                  => 3,
            'morning_go'             => 1,
            'morning_return'         => 1,
            'afternoon_go'           => 0,
            'afternoon_return'       => 0,
            'subscription_type'      => 'both',
            'accepted_gender'        => 'both',
            'school_stages'          => json_encode(['primary', 'middle']),
            'current_lat'            => 32.89000,
            'current_lng'            => 13.18000,
            'driver_waiting_minutes' => 10,
            'rating_avg'             => 4.8,
        ]);
        $v1 = Vehicle::create([
            'driver_id' => $d1->id, 'type' => 'Bus',
            'brand' => 'تويوتا', 'model' => 'كوستر', 'year' => 2022,
            'color' => 'أبيض', 'plate_number' => '5-112233',
            'capacity_manual' => 14, 'has_ac' => 1, 'is_verified' => 1, 'status' => 'Active',
        ]);
        $this->insertDriverDocs($d1->id, $v1->id, $adminUser->id, '2028-12-31', '2027-06-30');
        
        $d1Zones = array_unique([$get('حي الأندلس'), $get('بن عاشور'), $get('السراج')]);
        foreach ($d1Zones as $zId) {
            DB::table('driver_zone')->updateOrInsert(['driver_id' => $d1->id, 'zone_id' => $zId]);
        }

        $this->insertSeatSlots($d1->id, [
            'morning_go'     => [14, 3],
            'morning_return' => [14, 3],
        ]);

        // ─── السائق الثاني (معتمد - صباحي) ──────────────────
        $d2User = User::create([
            'full_name'         => 'طاهر فرج الزنتاني',
            'email'             => 'driver2@darby.ly',
            'phone_number'      => '0932000002',
            'password_hash'     => $password,
            'role_id'           => 4,
            'is_active'         => 1,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $d2 = Driver::create([
            'user_id'                => $d2User->id,
            'national_id'            => '119900445566',
            'license_number'         => 'DL-665544',
            'license_expiry'         => '2027-10-30',
            'status'                 => 'Approved',
            'gender'                 => 'male',
            'shift'                  => 1,
            'morning_go'             => 1,
            'morning_return'         => 1,
            'afternoon_go'           => 0,
            'afternoon_return'       => 0,
            'subscription_type'      => 'both',
            'accepted_gender'        => 'both',
            'school_stages'          => json_encode(['primary']),
            'current_lat'            => 32.91000,
            'current_lng'            => 13.22000,
            'driver_waiting_minutes' => 8,
            'rating_avg'             => 4.5,
        ]);
        $v2 = Vehicle::create([
            'driver_id' => $d2->id, 'type' => 'Van',
            'brand' => 'هيونداي', 'model' => 'H1', 'year' => 2021,
            'color' => 'رصاصي', 'plate_number' => '5-667788',
            'capacity_manual' => 8, 'has_ac' => 1, 'is_verified' => 1, 'status' => 'Active',
        ]);
        $this->insertDriverDocs($d2->id, $v2->id, $adminUser->id, '2027-10-30', '2027-03-31');
        
        $d2Zones = array_unique([$get('النوفليين'), $get('زاوية الدهماني'), $get('الظهرة')]);
        foreach ($d2Zones as $zId) {
            DB::table('driver_zone')->updateOrInsert(['driver_id' => $d2->id, 'zone_id' => $zId]);
        }

        $this->insertSeatSlots($d2->id, [
            'morning_go'     => [8, 1],
            'morning_return' => [8, 1],
        ]);

        // ─── السائق الثالث (Pending - مسائي) ─────────────────
        $d3User = User::create([
            'full_name'         => 'رمضان عبد القادر المجبري',
            'email'             => 'driver3@darby.ly',
            'phone_number'      => '0943000003',
            'password_hash'     => $password,
            'role_id'           => 4,
            'is_active'         => 0,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $d3 = Driver::create([
            'user_id'                => $d3User->id,
            'national_id'            => '119900778899',
            'license_number'         => 'DL-332211',
            'license_expiry'         => '2029-05-15',
            'status'                 => 'Pending',
            'gender'                 => 'male',
            'shift'                  => 2,
            'morning_go'             => 0,
            'morning_return'         => 0,
            'afternoon_go'           => 1,
            'afternoon_return'       => 1,
            'subscription_type'      => 'both',
            'accepted_gender'        => 'both',
            'school_stages'          => json_encode(['primary', 'middle', 'high']),
            'current_lat'            => 32.87500,
            'current_lng'            => 13.16500,
            'driver_waiting_minutes' => 12,
        ]);
        $v3 = Vehicle::create([
            'driver_id' => $d3->id, 'type' => 'Bus',
            'brand' => 'نيسان', 'model' => 'سيفيليان', 'year' => 2023,
            'color' => 'ذهبي', 'plate_number' => '5-998877',
            'capacity_manual' => 18, 'has_ac' => 1, 'is_verified' => 0, 'status' => 'Active',
        ]);
        $this->insertDriverDocs($d3->id, $v3->id, null, '2029-05-15', '2028-12-31', 'Pending');
        
        $d3Zones = array_unique([$get('قرجي'), $get('غوط الشعال')]);
        foreach ($d3Zones as $zId) {
            DB::table('driver_zone')->updateOrInsert(['driver_id' => $d3->id, 'zone_id' => $zId]);
        }

        $this->insertSeatSlots($d3->id, [
            'afternoon_go'     => [18, 0],
            'afternoon_return' => [18, 0],
        ]);

        $this->command->info('   ✓ 3 سائقين (2 معتمد + 1 قيد الانتظار) + مركبات + وثائق + مقاعد');
    }

    // ─── مساعد: إدخال وثائق السائق ───────────────────────────
    private function insertDriverDocs(
        int $driverId,
        int $vehicleId,
        ?int $reviewedBy,
        string $licenseExpiry,
        string $insuranceExpiry,
        string $status = 'Verified'
    ): void {
        $docs = [
            'LICENSE'         => "uploads/drivers/docs/license_{$driverId}.jpg",
            'VEHICLE_LOGBOOK' => "uploads/drivers/docs/logbook_{$driverId}.jpg",
            'INSURANCE'       => "uploads/drivers/docs/insurance_{$driverId}.jpg",
        ];

        foreach ($docs as $type => $url) {
            DB::table('driver_documents')->insert([
                'driver_id'             => $driverId,
                'vehicle_id'            => $vehicleId,
                'doc_type'              => $type,
                'file_url'              => $url,
                'license_expiry_date'   => $licenseExpiry,
                'insurance_expiry_date' => $insuranceExpiry,
                'status'                => $status,
                'reviewed_by'           => $reviewedBy,
                'reviewed_at'           => $reviewedBy ? now() : null,
                'uploaded_at'           => now(),
            ]);
        }
    }

    // ─── مساعد: إدخال سلوتات المقاعد ─────────────────────────
    private function insertSeatSlots(int $driverId, array $slots): void
    {
        foreach ($slots as $slotName => [$total, $reserved]) {
            DriverSeatSlot::create([
                'driver_id'      => $driverId,
                'slot'           => $slotName,
                'total_seats'    => $total,
                'reserved_seats' => $reserved,
            ]);
        }
    }
}
