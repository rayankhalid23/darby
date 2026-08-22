<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ParentPrerequisitesSeeder extends Seeder
{
    /**
     * تشغيل السيدر لتجهيز البنية التحتية المطلوبة لعمليات ولي الأمر
     */
    public function run(): void
    {
        // =========================================================================
        // 1. جدول الأدوار (Roles Table) — إجباري لتسجيل المستخدمين
        // =========================================================================
        $roles = [
            [
                'id'           => 1,
                'name'         => 'admin',
                'display_name' => 'مدير النظام',
                'description'  => 'صلاحيات كاملة لإدارة النظام والتحكم المالي والإشرافي'
            ],
            [
                'id'           => 2,
                'name'         => 'supervisor',
                'display_name' => 'مشرف',
                'description'  => 'صلاحيات إشرافية ومراجعة السائقين والشكاوى'
            ],
            [
                'id'           => 3,
                'name'         => 'parent',
                'display_name' => 'ولي أمر',
                'description'  => 'حساب ولي الأمر لإضافة الأبناء وإدارة الاشتراكات والتتبع'
            ],
            [
                'id'           => 4,
                'name'         => 'driver',
                'display_name' => 'سائق',
                'description'  => 'حساب السائق لتنفيذ الرحلات واستقبال طلبات التوصيل'
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                $role
            );
        }

        // =========================================================================
        // 2. التقسيمات الجغرافية (Municipalities -> Sub-Municipalities -> Zones)
        // =========================================================================
        $geography = [
            'طرابلس المركز' => [
                'طرابلس المدينة' => [
                    ['name' => 'بن عاشور', 'lat' => 32.8850, 'lng' => 13.1900],
                    ['name' => 'الظهرة', 'lat' => 32.8920, 'lng' => 13.1870],
                    ['name' => 'زاوية الدهماني', 'lat' => 32.8960, 'lng' => 13.1980],
                    ['name' => 'فشلوم', 'lat' => 32.8830, 'lng' => 13.1950],
                ],
                'النوفليين' => [
                    ['name' => 'النوفليين', 'lat' => 32.8750, 'lng' => 13.2050],
                    ['name' => 'راس حسن', 'lat' => 32.8710, 'lng' => 13.1980],
                ]
            ],
            'حي الأندلس' => [
                'حي الأندلس المركز' => [
                    ['name' => 'حي الأندلس', 'lat' => 32.8780, 'lng' => 13.1420],
                    ['name' => 'قرقارش', 'lat' => 32.8680, 'lng' => 13.1250],
                    ['name' => 'غوط الشعال', 'lat' => 32.8600, 'lng' => 13.1380],
                ]
            ],
            'سوق الجمعة' => [
                'سوق الجمعة المركز' => [
                    ['name' => 'عرادة', 'lat' => 32.8810, 'lng' => 13.2500],
                    ['name' => 'شرفة الملاحة', 'lat' => 32.8900, 'lng' => 13.2420],
                    ['name' => 'طريق الشط - سوق الجمعة', 'lat' => 32.9010, 'lng' => 13.2350],
                ]
            ]
        ];

        $zoneMap = [];

        foreach ($geography as $muniName => $subMunis) {
            $muni = DB::table('municipalities')->where('name', $muniName)->first();
            $muniId = $muni ? $muni->id : DB::table('municipalities')->insertGetId([
                'name'       => $muniName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($subMunis as $subName => $zones) {
                $subMuni = DB::table('sub_municipalities')
                    ->where('municipality_id', $muniId)
                    ->where('name', $subName)
                    ->first();

                $subMuniId = $subMuni ? $subMuni->id : DB::table('sub_municipalities')->insertGetId([
                    'municipality_id' => $muniId,
                    'name'            => $subName,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                foreach ($zones as $zoneData) {
                    $zone = DB::table('zones')
                        ->where('sub_municipality_id', $subMuniId)
                        ->where('name', $zoneData['name'])
                        ->first();

                    $zoneId = $zone ? $zone->id : DB::table('zones')->insertGetId([
                        'sub_municipality_id' => $subMuniId,
                        'name'                => $zoneData['name'],
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    $zoneMap[$zoneData['name']] = $zoneId;
                }
            }
        }

        // =========================================================================
        // 3. المدارس المعتمدة (Schools Table) — إجباري لإضافة أطفال (school_id)
        // =========================================================================
        $schools = [
            [
                'name'      => 'مدرسة طرابلس المركزية الحديثة',
                'zone_name' => 'زاوية الدهماني',
                'lat'       => 32.896200,
                'lng'       => 13.198500,
                'address'   => 'زاوية الدهماني - شارع المدارس',
                'status'    => 'active',
            ],
            [
                'name'      => 'مدرسة النخبة الابتدائية والإعدادية',
                'zone_name' => 'بن عاشور',
                'lat'       => 32.885500,
                'lng'       => 13.191200,
                'address'   => 'بن عاشور - بالقرب من جامع الصقع',
                'status'    => 'active',
            ],
            [
                'name'      => 'مدرسة حطين النموذجية',
                'zone_name' => 'شرفة الملاحة',
                'lat'       => 32.891000,
                'lng'       => 13.243000,
                'address'   => 'سوق الجمعة - طريق الملاحة',
                'status'    => 'active',
            ],
            [
                'name'      => 'مدرسة الأندلس الدولية للغات',
                'zone_name' => 'حي الأندلس',
                'lat'       => 32.879000,
                'lng'       => 13.143000,
                'address'   => 'حي الأندلس - بالقرب من مجمع القرقني',
                'status'    => 'active',
            ],
            [
                'name'      => 'روضة ومدرسة أجيال المستقبل',
                'zone_name' => 'النوفليين',
                'lat'       => 32.876000,
                'lng'       => 13.206000,
                'address'   => 'النوفليين - شارع الفاتح سابقاً',
                'status'    => 'active',
            ],
        ];

        foreach ($schools as $school) {
            $zoneId = $zoneMap[$school['zone_name']] ?? (DB::table('zones')->first()?->id ?? 1);

            DB::table('schools')->updateOrInsert(
                ['name' => $school['name']],
                [
                    'zone_id' => $zoneId,
                    'lat'     => $school['lat'],
                    'lng'     => $school['lng'],
                    'address' => $school['address'],
                    'status'  => $school['status'],
                ]
            );
        }

        // =========================================================================
        // 4. بنود العقود والشروط الأساسية (Clauses Table)
        // =========================================================================
        $clauses = [
            [
                'category'    => 'safety',
                'clause_text' => 'التزام التسليم من الباب إلى الباب والتأكد من دخول الطالب للمدرسة صباحاً والمنزل مساءً بأمان.'
            ],
            [
                'category'    => 'safety',
                'clause_text' => 'الحظر المطلق لإركاب أو نقل أي أشخاص غرباء غير مسجلين في التطبيق طوال فترة تواجد الطلاب.'
            ],
            [
                'category'    => 'financial',
                'clause_text' => 'تُدفع قيمة الاشتراك المتفق عليها مقدماً لحجز المقعد وتثبيت الرحلات اليومية.'
            ],
            [
                'category'    => 'parent_rights',
                'clause_text' => 'من حق ولي الأمر تتبع خط سير الحافلة مباشرة عبر التطبيق واستلام إشعار عند الاقتراب.'
            ],
            [
                'category'    => 'driver_rights',
                'clause_text' => 'يلتزم السائق بالانتظار أمام منزل الطالب لمدة أقصاها 3 إلى 5 دقائق تفادياً لتأخير بقية المشتركين.'
            ],
        ];

        foreach ($clauses as $clause) {
            DB::table('clauses')->updateOrInsert(
                ['clause_text' => $clause['clause_text']],
                $clause
            );
        }

        // =========================================================================
        // 5. رمز OTP تجريبي في جدول (otp_codes) لاختبار التسجيل المباشر
        // =========================================================================
        $testEmails = [
            'parent_test@darby.com',
            'parent_user@example.com',
            'kilani.parent@derbi.ly',
        ];

        foreach ($testEmails as $email) {
            DB::table('otp_codes')->updateOrInsert(
                [
                    'email'   => $email,
                    'purpose' => 'REGISTER',
                ],
                [
                    'code_hash'  => Hash::make('123456'),
                    'expires_at' => Carbon::now()->addMonths(6),
                    'is_used'    => false,
                    'attempts'   => 0,
                    'created_at' => Carbon::now(),
                ]
            );
        }

        // =========================================================================
        // 6. سائق معتمد ومركبة نشطة (لتجربة بحث وفلترة وطلب الاشتراكات لولي الأمر)
        // =========================================================================
        $driverUser = DB::table('users')->where('email', 'driver_demo@darby.com')->first();
        if (!$driverUser) {
            $driverUserId = DB::table('users')->insertGetId([
                'full_name'         => 'علي مصطفى الترهوني (سائق تجريبي)',
                'email'             => 'driver_demo@darby.com',
                'phone_number'      => '0910001122',
                'password_hash'     => Hash::make('Driver123456'),
                'role_id'           => 4,
                'is_active'         => 1,
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } else {
            $driverUserId = $driverUser->id;
        }

        $driver = DB::table('drivers')->where('user_id', $driverUserId)->first();
        if (!$driver) {
            $driverId = DB::table('drivers')->insertGetId([
                'user_id'           => $driverUserId,
                'national_id'       => '119900998877',
                'license_number'    => 'LIC-998877',
                'license_expiry'    => Carbon::now()->addYears(2)->toDateString(),
                'gender'            => 'male',
                'status'            => 'Approved',
                'subscription_type' => 'both',
                'school_stages'     => json_encode(['kindergarten', 'primary', 'middle', 'secondary']),
                'morning_go'        => 1,
                'morning_return'    => 1,
                'afternoon_go'      => 1,
                'afternoon_return'  => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } else {
            $driverId = $driver->id;
            DB::table('drivers')->where('id', $driverId)->update([
                'status'            => 'Approved',
                'subscription_type' => 'both',
                'school_stages'     => json_encode(['kindergarten', 'primary', 'middle', 'secondary']),
                'morning_go'        => 1,
                'morning_return'    => 1,
                'afternoon_go'      => 1,
                'afternoon_return'  => 1,
            ]);
        }

        // ربط السائق بمناطق العمل
        foreach ($zoneMap as $zoneName => $zId) {
            DB::table('driver_zone')->updateOrInsert([
                'driver_id' => $driverId,
                'zone_id'   => $zId,
            ]);
        }

        // إنشاء مقاعد السائق
        $slots = ['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'];
        foreach ($slots as $slot) {
            DB::table('driver_seat_slots')->updateOrInsert(
                ['driver_id' => $driverId, 'slot' => $slot],
                [
                    'total_seats'     => 14,
                    'available_seats' => 14,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }

        // مركبة معتمدة للسائق
        DB::table('vehicles')->updateOrInsert(
            ['driver_id' => $driverId, 'plate_number' => '5-998877'],
            [
                'brand'           => 'Hyundai',
                'model'           => 'H1 Grand',
                'year'            => 2023,
                'color'           => 'أبيض',
                'type'            => 'Van',
                'capacity_manual' => 14,
                'is_verified'     => 1,
                'has_ac'          => 1,
                'status'          => 'Active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );
    }
}
