<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * DriverSearchTestSeeder
 * ══════════════════════════════════════════════════════════
 * بيانات وهمية متكاملة لاختبار دالة البحث والفلترة والتسعير
 *
 * تغطي السيناريوهات التالية:
 *   ✅ بحث نصي بالاسم
 *   ✅ بحث نصي برقم الهاتف
 *   ✅ فلترة جنس السائق (ذكر / أنثى)
 *   ✅ فلترة وجود مكيف (نعم / لا)
 *   ✅ فلترة ذكية بالمنطقة (نفس زون المدرسة)
 *   ✅ فلترة ذكية بالبلدية (fallback)
 *   ✅ تسعير اشتراك شهري مع سيارة مكيفة
 *   ✅ تسعير اشتراك شهري مع سيارة غير مكيفة
 *   ✅ تسعير اشتراك يومي
 *   ✅ طفل بدون بيانات لوجستية (edge case)
 *   ✅ أكثر من طفل معاً
 *
 * ═══════════════════════════════════════════
 * بيانات الدخول لولي الأمر للاختبار:
 *   email:    parent.test@derbi.ly
 *   password: 12345678
 * ═══════════════════════════════════════════
 */
class DriverSearchTestSeeder extends Seeder
{
    // كلمة مرور موحدة لكل الحسابات
    private string $password;

    // ── معرفات الـ Zones (تُملأ من قاعدة البيانات) ──
    private int $zoneIdBenAshour;     // بن عاشور  → sub_muni: طرابلس المدينة
    private int $zoneIdDahra;         // الظهرة    → sub_muni: طرابلس المدينة
    private int $zoneIdArada;         // عرادة     → sub_muni: سوق الجمعة المركز
    private int $zoneIdAndalus;       // حي الأندلس→ sub_muni: حي الأندلس المركز
    private int $subMuniTripoliId;    // بلدية: طرابلس المدينة
    private int $subMuniSouqId;       // بلدية: سوق الجمعة المركز

    // ── معرفات المدارس ──
    private int $schoolTripoliId;     // مدرسة في زون زاوية الدهماني
    private int $schoolSouqId;        // مدرسة في زون شرفة الملاحة

    // ── role IDs ──
    private int $roleParent = 3;
    private int $roleDriver = 4;

    public function run(): void
    {
        $this->password = Hash::make('12345678');

        $this->command->info('🌍 جلب بيانات المناطق الجغرافية...');
        $this->resolveGeography();

        $this->command->info('🏫 جلب / إنشاء المدارس...');
        $this->resolveSchools();

        $this->command->info('👨‍👩‍👧 إنشاء أولياء الأمور وأطفالهم...');
        $parentId = $this->createParent();

        $this->command->info('🚗 إنشاء السائقين المتنوعين...');
        $this->createDrivers();

        $this->command->info('👶 إنشاء الأطفال مع بياناتهم اللوجستية...');
        $this->createChildren($parentId);

        $this->command->info('✅ اكتمل الـ Seeder بنجاح!');
        $this->printSummary($parentId);
    }

    // ══════════════════════════════════════════
    // [1] جلب بيانات المناطق الجغرافية
    // ══════════════════════════════════════════
    private function resolveGeography(): void
    {
        // جلب sub_municipalities
        $subTrip = DB::table('sub_municipalities')->where('name', 'طرابلس المدينة')->first();
        $subSouq = DB::table('sub_municipalities')->where('name', 'سوق الجمعة المركز')->first();
        $subAndl = DB::table('sub_municipalities')->where('name', 'حي الأندلس المركز')->first();

        if (!$subTrip || !$subSouq) {
            $this->command->error('⚠️  البيانات الجغرافية غير موجودة. شغّل أولاً: TripoliGeographySeeder');
            exit;
        }

        $this->subMuniTripoliId = $subTrip->id;
        $this->subMuniSouqId   = $subSouq->id;

        // جلب zones
        $zBen     = DB::table('zones')->where('name', 'بن عاشور')->first();
        $zDahra   = DB::table('zones')->where('name', 'الظهرة')->first();
        $zArada   = DB::table('zones')->where('name', 'عرادة')->first();
        $zAndalus = DB::table('zones')->where('name', 'حي الأندلس')->first();

        $this->zoneIdBenAshour = $zBen?->id   ?? 1;
        $this->zoneIdDahra     = $zDahra?->id  ?? 2;
        $this->zoneIdArada     = $zArada?->id  ?? 5;
        $this->zoneIdAndalus   = $zAndalus?->id ?? 7;
    }

    // ══════════════════════════════════════════
    // [2] جلب أو إنشاء مدارس للاختبار
    // ══════════════════════════════════════════
    private function resolveSchools(): void
    {
        // المدرسة 1 في زون "زاوية الدهماني" (ضمن sub_muni: طرابلس المدينة)
        $zDehm = DB::table('zones')->where('name', 'زاوية الدهماني')->first();
        $s1 = DB::table('schools')->where('name', 'مدرسة طرابلس المركزية')->first();
        if (!$s1) {
            $this->schoolTripoliId = DB::table('schools')->insertGetId([
                'name'    => 'مدرسة طرابلس المركزية',
                'zone_id' => $zDehm?->id,
                'lat'     => 32.8872,
                'lng'     => 13.1913,
                'address' => 'زاوية الدهماني، طرابلس',
                'status'  => 'approved',
            ]);
        } else {
            $this->schoolTripoliId = $s1->id;
        }

        // المدرسة 2 في زون "شرفة الملاحة" (ضمن sub_muni: سوق الجمعة المركز)
        $zSharaf = DB::table('zones')->where('name', 'شرفة الملاحة')->first();
        $s2 = DB::table('schools')->where('name', 'مدرسة حطين')->first();
        if (!$s2) {
            $this->schoolSouqId = DB::table('schools')->insertGetId([
                'name'    => 'مدرسة حطين',
                'zone_id' => $zSharaf?->id,
                'lat'     => 32.8721,
                'lng'     => 13.2380,
                'address' => 'شرفة الملاحة، سوق الجمعة',
                'status'  => 'approved',
            ]);
        } else {
            $this->schoolSouqId = $s2->id;
        }
    }

    // ══════════════════════════════════════════
    // [3] إنشاء ولي الأمر + سجل parents + عناوين
    // ══════════════════════════════════════════
    private function createParent(): int
    {
        // المستخدم الرئيسي لولي الأمر
        $userId = DB::table('users')->insertGetId([
            'full_name'     => 'محمد عبد الله الكيلاني',
            'email'         => 'parent.test@derbi.ly',
            'phone_number'  => '0913334455',
            'password_hash' => $this->password,
            'role_id'       => $this->roleParent,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // سجل ولي الأمر في جدول parents
        DB::table('parents')->insertGetId([
            'user_id'    => $userId,
            'is_trusted' => 1,
        ]);

        // ── عنوان منزل الطفل الأول (بن عاشور – قريب من مدرسة طرابلس) ──
        // إحداثيات: بن عاشور، طرابلس (32.9014, 13.2000)
        DB::table('addresses')->insert([
            'parent_id'  => $userId,
            'label'      => 'منزل بن عاشور',
            'lat'        => 32.9014,
            'lng'        => 13.2000,
            'is_default' => true,
            'zone_id'    => $this->zoneIdBenAshour,
        ]);

        // ── عنوان منزل الطفل الثاني (عرادة – قريب من مدرسة سوق الجمعة) ──
        // إحداثيات: عرادة، طرابلس (32.8760, 13.2350)
        DB::table('addresses')->insert([
            'parent_id'  => $userId,
            'label'      => 'منزل عرادة',
            'lat'        => 32.8760,
            'lng'        => 13.2350,
            'is_default' => false,
            'zone_id'    => $this->zoneIdArada,
        ]);

        return $userId;
    }

    // ══════════════════════════════════════════
    // [4] إنشاء الأطفال مع بيانات لوجستية متنوعة
    // ══════════════════════════════════════════
    private function createChildren(int $parentId): void
    {
        // جلب العناوين
        $addresses = DB::table('addresses')->where('parent_id', $parentId)->get();
        $addr1 = $addresses->firstWhere('label', 'منزل بن عاشور');
        $addr2 = $addresses->firstWhere('label', 'منزل عرادة');

        // ────────────────────────────────────────────
        // الطفل 1: ذكر | اشتراك شهري | مدرسة طرابلس
        // الغرض: اختبار التسعير الشهري + السيارة المكيفة
        // ────────────────────────────────────────────
        $child1Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolTripoliId,
            'address_id'          => $addr1?->id,
            'full_name'           => 'يوسف محمد الكيلاني',
            'birth_date'          => '2015-03-10',
            'gender'              => 'male',
            'grade'               => 4,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST001-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child1Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '13:30:00',
            'trip_direction'      => 'both',
            'subscription_type'   => 'monthly',
            'start_date'          => '2026-09-01',
            'end_date'            => '2026-09-30',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // ────────────────────────────────────────────
        // الطفل 2: أنثى | اشتراك يومي | مدرسة سوق الجمعة
        // الغرض: اختبار التسعير اليومي + فلترة الجنس
        // ────────────────────────────────────────────
        $child2Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolSouqId,
            'address_id'          => $addr2?->id,
            'full_name'           => 'ريم محمد الكيلاني',
            'birth_date'          => '2017-07-22',
            'gender'              => 'female',
            'grade'               => 2,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST002-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child2Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:15:00',
            'dropoff_time'        => '13:00:00',
            'trip_direction'      => 'go',
            'subscription_type'   => 'daily',
            'start_date'          => '2026-09-05',
            'end_date'            => '2026-09-05',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // ────────────────────────────────────────────
        // الطفل 3: ذكر | اشتراك شهري | نفس مدرسة طرابلس
        // الغرض: اختبار أكثر من طفل (child_ids=[1,3])
        // ────────────────────────────────────────────
        $child3Id = DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => $this->schoolTripoliId,
            'address_id'          => $addr1?->id,
            'full_name'           => 'عمر محمد الكيلاني',
            'birth_date'          => '2013-11-15',
            'gender'              => 'male',
            'grade'               => 6,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST003-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        DB::table('child_logistics')->insert([
            'child_id'            => $child3Id,
            'preferred_time_slot' => 'morning',
            'pickup_time'         => '07:00:00',
            'dropoff_time'        => '13:30:00',
            'trip_direction'      => 'both',
            'subscription_type'   => 'monthly',
            'start_date'          => '2026-09-01',
            'end_date'            => '2026-09-30',
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // ────────────────────────────────────────────
        // الطفل 4: ذكر | بدون بيانات لوجستية (edge case)
        // الغرض: اختبار حالة الطفل الناقص
        // ────────────────────────────────────────────
        DB::table('children')->insertGetId([
            'parent_id'           => $parentId,
            'school_id'           => null,        // بدون مدرسة
            'address_id'          => null,        // بدون عنوان
            'full_name'           => 'سلمان محمد الكيلاني',
            'birth_date'          => '2019-01-01',
            'gender'              => 'male',
            'grade'               => 1,
            'notification_radius' => 500,
            'qr_code_token'       => 'CHLD-TEST004-' . time(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        // ⚠️ هذا الطفل بدون child_logistics عمداً (edge case)
    }

    // ══════════════════════════════════════════
    // [5] إنشاء السائقين المتنوعين
    // ══════════════════════════════════════════
    private function createDrivers(): void
    {
        $driversData = [
            // ──────────────────────────────────────────
            // السائق 1: ذكر | مكيف | زون بن عاشور | شهري
            // ← مثالي لسيناريو: طفل ذكر + نفس المنطقة + مكيف + شهري
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'خالد مصطفى الورفلي',
                    'email'        => 'khalid.driver@derbi.ly',
                    'phone_number' => '0917001001',
                    'alternative_phone' => '0910001001',
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'monthly',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.8,
                    'completed_trips_count' => 120,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-5521',
                    'brand'           => 'Toyota',
                    'model'           => 'Hiace',
                    'year'            => '2022',
                    'color'           => 'أبيض',
                    'type'            => 'Van',
                    'capacity_manual' => 12,
                    'has_ac'          => 1,   // ← مكيف
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdBenAshour],  // بن عاشور
            ],

            // ──────────────────────────────────────────
            // السائق 2: ذكر | غير مكيف | زون الظهرة | شهري
            // ← اختبار: نفس البلدية (طرابلس المدينة) + غير مكيف
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'عبد الرحمن سالم الزروق',
                    'email'        => 'abdurahman.driver@derbi.ly',
                    'phone_number' => '0920002002',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'male',   // يقبل فقط الذكور
                    'subscription_type' => 'monthly',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.2,
                    'completed_trips_count' => 85,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-3344',
                    'brand'           => 'Hyundai',
                    'model'           => 'H1',
                    'year'            => '2020',
                    'color'           => 'فضي',
                    'type'            => 'Van',
                    'capacity_manual' => 8,
                    'has_ac'          => 0,   // ← غير مكيف
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdDahra],  // الظهرة (نفس بلدية طرابلس المدينة)
            ],

            // ──────────────────────────────────────────
            // السائق 3: أنثى | مكيف | زون حي الأندلس | يومي + شهري
            // ← اختبار: فلترة جنس السائق أنثى + has_ac + daily
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'سلمى أحمد المصراتي',
                    'email'        => 'salma.driver@derbi.ly',
                    'phone_number' => '0913003003',
                    'alternative_phone' => '0920003003',
                ],
                'driver' => [
                    'gender'            => 'female',
                    'accepted_gender'   => 'female',  // تقبل فقط الإناث
                    'subscription_type' => 'both',
                    'shift'             => 3,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.9,
                    'completed_trips_count' => 200,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-7788',
                    'brand'           => 'Kia',
                    'model'           => 'Carnival',
                    'year'            => '2023',
                    'color'           => 'أزرق',
                    'type'            => 'Van',
                    'capacity_manual' => 7,
                    'has_ac'          => 1,   // ← مكيف
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdAndalus],  // حي الأندلس
            ],

            // ──────────────────────────────────────────
            // السائق 4: ذكر | مكيف | زون عرادة | شهري
            // ← اختبار: نفس منطقة الطفل الثاني (عرادة)
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'إبراهيم محمود البدري',
                    'email'        => 'ibrahim.driver@derbi.ly',
                    'phone_number' => '0944004004',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'both',
                    'shift'             => 2,
                    'status'            => 'Approved',
                    'rating_avg'        => 4.5,
                    'completed_trips_count' => 60,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-1199',
                    'brand'           => 'Nissan',
                    'model'           => 'Urvan',
                    'year'            => '2021',
                    'color'           => 'رمادي',
                    'type'            => 'Van',
                    'capacity_manual' => 14,
                    'has_ac'          => 1,   // ← مكيف
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdArada],  // عرادة
            ],

            // ──────────────────────────────────────────
            // السائق 5: ذكر | غير مكيف | بدون منطقة | يومي
            // ← اختبار: سائق لا يغطي أي منطقة (لا يظهر في فلترة المنطقة)
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'محمود علي الفيتوري',
                    'email'        => 'mahmoud.fituri.driver@derbi.ly',
                    'phone_number' => '0955005005',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'daily',
                    'shift'             => 1,
                    'status'            => 'Approved',
                    'rating_avg'        => 3.9,
                    'completed_trips_count' => 30,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-6677',
                    'brand'           => 'Mercedes',
                    'model'           => 'Sprinter',
                    'year'            => '2019',
                    'color'           => 'أبيض',
                    'type'            => 'Bus',
                    'capacity_manual' => 20,
                    'has_ac'          => 0,   // ← غير مكيف
                    'status'          => 'Active',
                ],
                'zones' => [],  // ← بدون منطقة (fallback test)
            ],

            // ──────────────────────────────────────────
            // السائق 6: ذكر | مكيف | معلق (Suspended)
            // ← اختبار: يجب ألا يظهر في النتائج (غير معتمد)
            // ──────────────────────────────────────────
            [
                'user' => [
                    'full_name'    => 'فرج الله عثمان المبروك',
                    'email'        => 'farajallah.driver@derbi.ly',
                    'phone_number' => '0966006006',
                    'alternative_phone' => null,
                ],
                'driver' => [
                    'gender'            => 'male',
                    'accepted_gender'   => 'both',
                    'subscription_type' => 'monthly',
                    'shift'             => 1,
                    'status'            => 'Suspended',  // ← معلق - يجب ألا يظهر!
                    'rating_avg'        => 4.0,
                    'completed_trips_count' => 10,
                ],
                'vehicle' => [
                    'plate_number'    => 'LY-9900',
                    'brand'           => 'Ford',
                    'model'           => 'Transit',
                    'year'            => '2018',
                    'color'           => 'أسود',
                    'type'            => 'Van',
                    'capacity_manual' => 9,
                    'has_ac'          => 1,
                    'status'          => 'Active',
                ],
                'zones' => [$this->zoneIdBenAshour],
            ],
        ];

        foreach ($driversData as $data) {
            $this->insertDriver($data);
        }
    }

    // ══════════════════════════════════════════
    // Helper: إدراج سائق كامل
    // ══════════════════════════════════════════
    private function insertDriver(array $data): void
    {
        $u = $data['user'];
        $d = $data['driver'];
        $v = $data['vehicle'];

        // ── users ──
        $userId = DB::table('users')->insertGetId([
            'full_name'         => $u['full_name'],
            'email'             => $u['email'],
            'phone_number'      => $u['phone_number'],
            'alternative_phone' => $u['alternative_phone'] ?? null,
            'password_hash'     => $this->password,
            'role_id'           => $this->roleDriver,
            'is_active'         => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ── drivers ──
        $driverId = DB::table('drivers')->insertGetId([
            'user_id'                   => $userId,
            'gender'                    => $d['gender'],
            'accepted_gender'           => $d['accepted_gender'],
            'subscription_type'         => $d['subscription_type'],
            'shift'                     => $d['shift'],
            'status'                    => $d['status'],
            'rating_avg'                => $d['rating_avg'],
            'completed_trips_count'     => $d['completed_trips_count'],
            'active_subs_count'         => 0,
            'total_subs_count'          => 0,
            'cancelled_by_driver_count' => 0,
            'cancelled_by_parent_count' => 0,
            'retention_rate'            => 100.00,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        // ── vehicles ──
        DB::table('vehicles')->insert([
            'driver_id'       => $driverId,
            'plate_number'    => $v['plate_number'],
            'brand'           => $v['brand'],
            'model'           => $v['model'],
            'year'            => $v['year'],
            'color'           => $v['color'],
            'type'            => $v['type'],
            'capacity_manual' => $v['capacity_manual'],
            'has_ac'          => $v['has_ac'],
            'status'          => $v['status'],
            'is_verified'     => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // ── driver_zone ──
        foreach ($data['zones'] as $zoneId) {
            DB::table('driver_zone')->insert([
                'driver_id'  => $driverId,
                'zone_id'    => $zoneId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ══════════════════════════════════════════
    // طباعة ملخص البيانات للـ Terminal
    // ══════════════════════════════════════════
    private function printSummary(int $parentId): void
    {
        $children = DB::table('children')->where('parent_id', $parentId)->get();

        $this->command->newLine();
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info('  📋 ملخص البيانات المُدخلة للاختبار');
        $this->command->info('══════════════════════════════════════════════');
        $this->command->info("  👤 ولي الأمر ID : {$parentId}");
        $this->command->info("  📧 Email        : parent.test@derbi.ly");
        $this->command->info("  🔑 Password     : 12345678");
        $this->command->newLine();
        foreach ($children as $c) {
            $this->command->info("  👶 طفل: {$c->full_name} | ID: {$c->id} | الجنس: {$c->gender}");
        }
        $this->command->newLine();
        $this->command->info('  🚗 السائقون المُضافون: 6 سائقين (1 معلق - يجب ألا يظهر)');
        $this->command->info('══════════════════════════════════════════════');
    }
}
