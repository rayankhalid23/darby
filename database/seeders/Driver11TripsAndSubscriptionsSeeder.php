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

        echo "🚀 بدء إنشاء وزرع بيانات سيناريوهات الرحلات والاشتراكات للسائق user_id = 11...\n";

        // =========================================================================
        // 0. التأكد من وجود الأدوار الأساسية في النظام (Roles)
        // =========================================================================
        DB::table('roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'super_admin', 'display_name' => 'سوبر أدمن'],
            ['id' => 2, 'name' => 'admin',       'display_name' => 'مشرف النظام'],
            ['id' => 3, 'name' => 'parent',      'display_name' => 'ولي أمر'],
            ['id' => 4, 'name' => 'driver',      'display_name' => 'سائق'],
        ]);

        // =========================================================================
        // 1. تجهيز أو إنشاء حساب المستخدم للسائق user_id = 11 (User & Driver)
        // =========================================================================
        // تحرير رقم الهاتف والبريد من أي حسابات أخرى لتفادي Duplicate Entry
        DB::table('users')->where('phone_number', '0911111111')->where('id', '!=', 11)->update(['phone_number' => '0910000011']);
        DB::table('users')->where('email', 'driver11@darby.ly')->where('id', '!=', 11)->update(['email' => 'driver11_old@darby.ly']);

        $driverUser = User::find(11);

        if (!$driverUser) {
            DB::table('users')->insert([
                'id'                => 11,
                'full_name'         => 'الكابتن عبد السلام المهدوي',
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
            echo "✅ تم إنشاء المستخدم المطلوب user_id = 11 (الكابتن عبد السلام المهدوي)\n";
        } else {
            $driverUser->update([
                'full_name'    => 'الكابتن عبد السلام المهدوي',
                'email'        => 'driver11@darby.ly',
                'phone_number' => '0911111111',
                'is_active'    => 1,
            ]);
            echo "ℹ️ تم التحديث على حساب المستخدم الموجود user_id = 11\n";
        }

        // إنشاء أو تحديث سجل السائق (Driver Profile)
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
        echo "✅ السائق جاهز (Driver ID: {$driverId}, User ID: 11)\n";

        // =========================================================================
        // 2. إنشاء مركبة السائق وحجوزات المقاعد (Vehicle & Seat Slots)
        // =========================================================================
        $vehicleId = DB::table('vehicles')->where('driver_id', $driverId)->value('id');
        if (!$vehicleId) {
            $vehicleId = DB::table('vehicles')->insertGetId([
                'driver_id'       => $driverId,
                'plate_number'    => '5-88111',
                'brand'           => 'تويوتا',
                'model'           => 'هايس توين كابينة',
                'year'            => '2023',
                'color'           => 'أبيض ملكي',
                'type'            => 'Van',
                'capacity_manual' => 14,
                'is_verified'     => 1,
                'has_ac'          => 1,
                'status'          => 'Active',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // ضبط حجوزات المقاعد لكل الفترة
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
        // 3. إنشاء المدارس الواقعية في طرابلس (Schools)
        // =========================================================================
        $schoolsSpecs = [
            'school_1' => [
                'name'    => 'مدرسة الجيل الجديد الدولية (حي الأندلس)',
                'address' => 'حي الأندلس - بالقرب من جامع الأندلس',
                'lat'     => 32.89000000,
                'lng'     => 13.17000000,
            ],
            'school_2' => [
                'name'    => 'مدرسة القدس النموذجية (شارع دمشق)',
                'address' => 'شارع دمشق - طرابلس',
                'lat'     => 32.86500000,
                'lng'     => 13.19000000,
            ],
            'school_3' => [
                'name'    => 'مدرسة النواة الأولى (سوق الجمعة)',
                'address' => 'سوق الجمعة - قرب مركز البريد',
                'lat'     => 32.89500000,
                'lng'     => 13.22000000,
            ],
            'school_4' => [
                'name'    => 'مدرسة ليبيا الحديثة (عين زارة)',
                'address' => 'عين زارة - قرب الطرق الدائري',
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
        // 4. إنشاء أولياء الأمور والأطفال (Parents & Children)
        // =========================================================================
        $parentsData = [
            [
                'email'     => 'parent11_1@darby.ly',
                'name'      => 'علي عبد الله الزوي',
                'phone'     => '0912220011',
                'children'  => [
                    [
                        'name'          => 'طارق علي الزوي',
                        'gender'        => 'male',
                        'grade'         => 4,
                        'school_id'     => $schools['school_1']->id,
                        'qr_token'      => 'QR_CHILD_1101_TAREQ',
                        'home_lat'      => 32.88750000,
                        'home_lng'      => 13.17200000,
                        'sub_scenario'  => 'active_full',
                    ],
                    [
                        'name'          => 'سارة علي الزوي',
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
                'name'      => 'محمد البشير الورفلي',
                'phone'     => '0912220022',
                'children'  => [
                    [
                        'name'          => 'عمر محمد الورفلي',
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
                'name'      => 'عبد الله إبراهيم الترهوني',
                'phone'     => '0912220033',
                'children'  => [
                    [
                        'name'          => 'خديجة عبد الله الترهوني',
                        'gender'        => 'female',
                        'grade'         => 3,
                        'school_id'     => $schools['school_2']->id,
                        'qr_token'      => 'QR_CHILD_1104_KHADIJA',
                        'home_lat'      => 32.89500000,
                        'home_lng'      => 13.22000000,
                        'sub_scenario'  => 'active_morning_only',
                    ],
                    [
                        'name'          => 'أنس عبد الله الترهوني',
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
                'name'      => 'طارق مصطفى القراماني',
                'phone'     => '0912220044',
                'children'  => [
                    [
                        'name'          => 'ياسمين طارق القراماني',
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
                'name'      => 'عمر فرج الكيلاني',
                'phone'     => '0912220055',
                'children'  => [
                    [
                        'name'          => 'مالك عمر الكيلاني',
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
                'name'      => 'خالد سعد الفيتوري',
                'phone'     => '0912220066',
                'children'  => [
                    [
                        'name'          => 'فاطمة خالد الفيتوري',
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
            // تحرير رقم الهاتف إن كان مستخدماً من قبل حسـاب آخر
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

        echo "✅ تم زرع أولياء الأمور والأطفال والرموز السرية QR بنجاح!\n";

        // =========================================================================
        // 5. إنشاء وتنوع سيناريوهات العقود والاشتراكات (Contracts & Subscriptions)
        // =========================================================================
        
        // أ) العقود والاشتراكات النشطة الكاملة (Active Full Subscriptions)
        foreach (['طارق علي الزوي', 'سارة علي الزوي', 'عمر محمد الورفلي'] as $childName) {
            $ch = $createdChildren[$childName];
            $parentModel = $ch->parent;

            $subReq = SubscriptionRequest::firstOrCreate(
                ['parent_id' => $parentModel->id, 'driver_id' => $driverId, 'status' => 'accepted'],
                [
                    'school_id'         => $ch->school_id,
                    'subscription_type' => 'monthly',
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
                    'subscription_type' => 'monthly',
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
                    'pickup_label'  => 'المنزل - حي الأندلس / السياحية',
                    'dropoff_lat'   => $ch->school->lat,
                    'dropoff_lng'   => $ch->school->lng,
                    'dropoff_label' => $ch->school->name,
                    'pickup_time'   => '07:00:00',
                    'dropoff_time'  => '13:30:00',
                    'status'        => 'active',
                ]
            );
        }

        // ب) الاشتراكات الصباحية فقط (Active Morning Only)
        foreach (['خديجة عبد الله الترهوني', 'أنس عبد الله الترهوني'] as $childName) {
            $ch = $createdChildren[$childName];
            $parentModel = $ch->parent;

            $subReq = SubscriptionRequest::firstOrCreate(
                ['parent_id' => $parentModel->id, 'driver_id' => $driverId, 'direction' => 'go', 'status' => 'accepted'],
                [
                    'school_id'         => $ch->school_id,
                    'subscription_type' => 'monthly',
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
                    'subscription_type' => 'monthly',
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
                    'pickup_label'  => 'المنزل - سوق الجمعة',
                    'dropoff_lat'   => $ch->school->lat,
                    'dropoff_lng'   => $ch->school->lng,
                    'dropoff_label' => $ch->school->name,
                    'pickup_time'   => '07:15:00',
                    'dropoff_time'  => '07:45:00',
                    'status'        => 'active',
                ]
            );
        }

        // ج) اشتراك ملغى / موقف (Cancelled Subscription)
        $chPaused = $createdChildren['ياسمين طارق القراماني'];
        $subReqPaused = SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chPaused->parent->id, 'driver_id' => $driverId, 'status' => 'cancelled'],
            [
                'school_id'         => $chPaused->school_id,
                'subscription_type' => 'monthly',
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
                'subscription_type' => 'monthly',
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
                'pickup_label'  => 'المنزل - عين زارة',
                'dropoff_lat'   => $chPaused->school->lat,
                'dropoff_lng'   => $chPaused->school->lng,
                'dropoff_label' => $chPaused->school->name,
                'status'        => 'cancelled',
            ]
        );

        // د) اشتراك مكتمل / منتهي الصلاحية (Completed Subscription)
        $chExpired = $createdChildren['مالك عمر الكيلاني'];
        $subReqExpired = SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chExpired->parent->id, 'driver_id' => $driverId, 'status' => 'accepted'],
            [
                'school_id'         => $chExpired->school_id,
                'subscription_type' => 'monthly',
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
                'subscription_type' => 'monthly',
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
                'pickup_label'  => 'المنزل - جنزور',
                'dropoff_lat'   => $chExpired->school->lat,
                'dropoff_lng'   => $chExpired->school->lng,
                'dropoff_label' => $chExpired->school->name,
                'status'        => 'completed',
            ]
        );

        // هـ) طلب اشتراك قيد الانتظار (Pending Request)
        $chPending = $createdChildren['فاطمة خالد الفيتوري'];
        SubscriptionRequest::firstOrCreate(
            ['parent_id' => $chPending->parent->id, 'driver_id' => $driverId, 'status' => 'pending'],
            [
                'school_id'         => $chPending->school_id,
                'subscription_type' => 'monthly',
                'direction'         => 'both',
                'timing'            => 'BOTH',
                'start_date'        => Carbon::tomorrow()->toDateString(),
                'end_date'          => Carbon::tomorrow()->addMonth()->toDateString(),
                'days_count'        => 20,
                'total_price'       => 320.00,
                'children_count'    => 1,
                'notes'             => 'طلب اشتراك جديد لنهاية الفصل الدراسي',
            ]
        );

        echo "✅ تم إدخال جميع سيناريوهات الاشتراكات (نشط كامل، نشط صباحي، موقف، منتهي، قيد الانتظار)\n";

        // =========================================================================
        // 6. إنشاء المسارات الهيكلية المرجعية للسائق (Master Routes & RouteStops)
        // =========================================================================
        
        // المسار 1: الذهاب الصباحي (Morning Go)
        $routeMorning = RouteModel::updateOrCreate(
            ['driver_id' => $driverId, 'route_type' => 'Morning', 'shift_slot' => DriverSeatSlot::MORNING_GO],
            [
                'vehicle_id'         => $vehicleId,
                'route_name'         => 'مسار الذهاب الصباحي - حي الأندلس والسياحية إلى المدارس',
                'start_time'         => '07:00:00',
                'estimated_duration' => 45,
                'status'             => 'Active',
            ]
        );

        // مسح محطات المسار القديمة لإعادة زرعها بانتظام
        RouteStop::where('route_id', $routeMorning->id)->delete();

        $morningStops = [
            ['type' => 'home',   'child' => 'طارق علي الزوي',          'school' => null,                   'lat' => 32.8875, 'lng' => 13.1720, 'label' => 'منزل عائلة الزوي', 'seq' => 1],
            ['type' => 'home',   'child' => 'عمر محمد الورفلي',        'school' => null,                   'lat' => 32.8790, 'lng' => 13.1580, 'label' => 'منزل عائلة الورفلي', 'seq' => 2],
            ['type' => 'home',   'child' => 'خديجة عبد الله الترهوني',  'school' => null,                   'lat' => 32.8950, 'lng' => 13.2200, 'label' => 'منزل عائلة الترهوني', 'seq' => 3],
            ['type' => 'home',   'child' => 'أنس عبد الله الترهوني',   'school' => null,                   'lat' => 32.8950, 'lng' => 13.2200, 'label' => 'منزل عائلة الترهوني', 'seq' => 4],
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

        // ربط الاشتراكات النشطة بهذا المسار
        ActiveSubscription::where('driver_id', $driverId)->update(['route_id' => $routeMorning->id]);

        // المسار 2: العودة الصباحية (Morning Return)
        $routeReturn = RouteModel::updateOrCreate(
            ['driver_id' => $driverId, 'route_type' => 'Morning', 'shift_slot' => DriverSeatSlot::MORNING_RETURN],
            [
                'vehicle_id'         => $vehicleId,
                'route_name'         => 'مسار العودة الصباحي - من المدارس إلى المنازل',
                'start_time'         => '13:30:00',
                'estimated_duration' => 40,
                'status'             => 'Active',
            ]
        );

        echo "✅ تم إنشاء وتحديث المسارات الهيكلية ومحطاتها بنجاح!\n";

        // =========================================================================
        // 7. زرع غياب مجدول لطفل وللسائق (Absence Scenarios)
        // =========================================================================
        
        // غياب الطفل "أنس الترهوني" اليوم ومستقبلاً من ولي الأمر
        $childAnas = $createdChildren['أنس عبد الله الترهوني'];
        AbsenceLog::updateOrCreate(
            ['child_id' => $childAnas->id, 'absence_date' => $today],
            [
                'absence_type' => AbsenceLog::TYPE_BOTH,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]
        );

        // غياب السائق بعد 3 أيام (Driver Absence)
        DriverAbsence::updateOrCreate(
            ['driver_id' => $driverId, 'absence_date' => Carbon::tomorrow()->addDays(3)->toDateString()]
        );

        // =========================================================================
        // 8. زرع كافة سيناريوهات الرحلات التشغيلية (Trip Scenarios)
        // =========================================================================

        // -------------------------------------------------------------------------
        // السيناريو الأول: رحلة صباحية جارية حالياً اليوم (Ongoing Active Trip: status = started)
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

        // تنظيف المحطات والأحداث القديمة لهذه الرحلة
        TripStop::where('trip_id', $tripOngoing->id)->delete();
        TripEvent::where('trip_id', $tripOngoing->id)->delete();
        TripTracking::where('trip_id', $tripOngoing->id)->delete();

        // محطات الرحلة الجارية مع تنوع كامل في الحالات الدقيقة (boarded, pending, skipped, absent_pre)
        $ongoingStopsData = [
            // طارق الزوي: صعد للحافلة وتم مسح الـ QR
            [
                'child'     => 'طارق علي الزوي',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_BOARDED,
                'lat'       => 32.8875,
                'lng'       => 13.1720,
                'label'     => 'منزل طارق الزوي (حي الأندلس)',
                'seq'       => 1,
                'eta'       => '07:08',
            ],
            // سارة الزوي: صعدت للحافلة أيضاً
            [
                'child'     => 'سارة علي الزوي',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_BOARDED,
                'lat'       => 32.8875,
                'lng'       => 13.1720,
                'label'     => 'منزل سارة الزوي (حي الأندلس)',
                'seq'       => 2,
                'eta'       => '07:09',
            ],
            // عمر الورفلي: المحطة القادمة للسائق (قيد الانتظار)
            [
                'child'     => 'عمر محمد الورفلي',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_PENDING,
                'lat'       => 32.8790,
                'lng'       => 13.1580,
                'label'     => 'منزل عمر الورفلي (السياحية) - المحطة القادمة',
                'seq'       => 3,
                'eta'       => '07:22',
            ],
            // خديجة الترهوني: تم تخطي محطتها لعدم الاستجابة عند الباب
            [
                'child'     => 'خديجة عبد الله الترهوني',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_SKIPPED_UNRESPONSIVE,
                'lat'       => 32.8950,
                'lng'       => 13.2200,
                'label'     => 'منزل خديجة الترهوني (تم التخطي)',
                'seq'       => 4,
                'eta'       => '07:30',
            ],
            // أنس الترهوني: غائب بعلم مسبق من ولي الأمر
            [
                'child'     => 'أنس عبد الله الترهوني',
                'school'    => null,
                'type'      => TripStop::TYPE_HOME,
                'status'    => TripStop::STATUS_ABSENT_PRE,
                'lat'       => 32.8950,
                'lng'       => 13.2200,
                'label'     => 'منزل أنس الترهوني (غائب مسبقاً)',
                'seq'       => 0,
                'eta'       => null,
            ],
            // محطة الوصول للمدرسة الأولى
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
            // محطة الوصول للمدرسة الثانية
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

        // أحداث الرحلة الجارية (Trip Events: pickups, skip)
        $chTareq = $createdChildren['طارق علي الزوي'];
        $chSara  = $createdChildren['سارة علي الزوي'];
        $chKhad  = $createdChildren['خديجة عبد الله الترهوني'];

        $subTareqId = ActiveSubscription::where('child_id', $chTareq->id)->value('id') ?? 1;
        $subSaraId  = ActiveSubscription::where('child_id', $chSara->id)->value('id') ?? 1;
        $subKhadId  = ActiveSubscription::where('child_id', $chKhad->id)->value('id') ?? 1;

        TripEvent::create([
            'trip_id'         => $tripOngoing->id,
            'child_id'        => $chTareq->id,
            'subscription_id' => $subTareqId,
            'action_type'     => 'picked_up',
            'trip_type'       => 'ذهاب',
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
            'trip_type'       => 'ذهاب',
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
            'trip_type'       => 'ذهاب',
            'location_lat'    => 32.89500000,
            'location_lng'    => 13.22000000,
            'scanned_at'      => Carbon::now()->subMinutes(5),
            'trip_cost'       => 0.00,
        ]);

        // سجلات التتبع الحي المباشر بالحافلة (Trip Live Tracking GPS)
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

        echo "✅ السيناريو 1: تم إيقاع رحلة جارية الآن (Trip ID: {$tripOngoing->id}) مع تتبع حي ومزيج حقيقي من حالات الطلاب!\n";

        // -------------------------------------------------------------------------
        // السيناريو الثاني: رحلة مكتملة بنجاح اليوم (Completed Trip Today: status = completed)
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

        foreach (['طارق علي الزوي', 'سارة علي الزوي', 'عمر محمد الورفلي'] as $idx => $cName) {
            $chObj = $createdChildren[$cName];
            
            TripStop::create([
                'trip_id'        => $tripCompletedToday->id,
                'stop_type'      => TripStop::TYPE_HOME,
                'child_id'       => $chObj->id,
                'lat'            => $chObj->home_lat,
                'lng'            => $chObj->home_lng,
                'label'          => "منزل {$chObj->full_name}",
                'sequence_order' => $idx + 1,
                'status'         => TripStop::STATUS_DELIVERED_HOME,
            ]);

            $subId = ActiveSubscription::where('child_id', $chObj->id)->value('id') ?? 1;

            TripEvent::create([
                'trip_id'         => $tripCompletedToday->id,
                'child_id'        => $chObj->id,
                'subscription_id' => $subId,
                'action_type'     => 'dropped_off',
                'trip_type'       => 'عودة',
                'location_lat'    => $chObj->home_lat,
                'location_lng'    => $chObj->home_lng,
                'scanned_at'      => Carbon::now()->subHours(3)->addMinutes($idx * 10),
                'trip_cost'       => 15.00,
            ]);
        }

        echo "✅ السيناريو 2: تم إيقاع رحلة مكتملة اليوم بنجاح (Trip ID: {$tripCompletedToday->id})\n";

        // -------------------------------------------------------------------------
        // السيناريو الثالث: رحلة قادمة/مجدولة لم تبدأ بعد (Pending Upcoming Trip: status = pending)
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

        foreach (['طارق علي الزوي', 'عمر محمد الورفلي'] as $idx => $cName) {
            $chObj = $createdChildren[$cName];
            TripStop::create([
                'trip_id'        => $tripPendingUpcoming->id,
                'stop_type'      => TripStop::TYPE_HOME,
                'child_id'       => $chObj->id,
                'lat'            => $chObj->home_lat,
                'lng'            => $chObj->home_lng,
                'label'          => "منزل {$chObj->full_name}",
                'sequence_order' => $idx + 1,
                'status'         => TripStop::STATUS_PENDING,
                'eta_minutes'    => ($idx + 1) * 10,
            ]);
        }

        echo "✅ السيناريو 3: تم إيقاع رحلة قادمة مجدولة لاختبار بدء الرحلة (Trip ID: {$tripPendingUpcoming->id})\n";

        // -------------------------------------------------------------------------
        // السيناريو الرابع: رحلة تاريخية مكتملة البارحة (Historical Completed Trip Yesterday)
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

        echo "✅ السيناريو 4: تم إيقاع رحلة سابقة مكتملة من يوم أمس (Trip ID: {$tripHistorical->id})\n";

        // -------------------------------------------------------------------------
        // السيناريو الخامس: رحلة ملغاة لسبب طارئ (Cancelled Trip: status = cancelled)
        // -------------------------------------------------------------------------
        $tripCancelled = Trip::create([
            'driver_id'         => $driverId,
            'route_id'          => $routeReturn->id,
            'trip_type'         => 'Morning',
            'shift_slot'        => DriverSeatSlot::MORNING_RETURN,
            'status'            => 'suspended_breakdown',
            'suspension_reason' => 'عطل طارئ في الحافلة وتم توفير حافلة بديلة لنقل الطلاب',
            'scheduled_at'      => Carbon::yesterday()->setTime(13, 30, 0),
            'trip_date'         => $yesterday,
            'created_at'        => Carbon::yesterday(),
        ]);

        echo "✅ السيناريو 5: تم إيقاع رحلة ملغاة لاختبار حالة الإلغاء (Trip ID: {$tripCancelled->id})\n";

        // =========================================================================
        // 9. طباعة التقرير النهائي وسجل الملخص للاختبار
        // =========================================================================
        echo "\n" . str_repeat("=", 75) . "\n";
        echo "🎉 اكتمل زرع بيانات السائق user_id = 11 بجميع السيناريوهات والاشتراكات والرحلات!\n";
        echo str_repeat("=", 75) . "\n";
        echo "👤 معلومات السائق:\n";
        echo "   - User ID: 11\n";
        echo "   - Driver ID: {$driverId}\n";
        echo "   - الاسم: الكابتن عبد السلام المهدوي\n";
        echo "   - البريد الإلكتروني: driver11@darby.ly\n";
        echo "   - رقم الهاتف: 0911111111\n";
        echo "   - كلمة المرور: password123\n";
        echo "-------------------------------------------------------------------------\n";
        echo "🚌 بيانات الرحلات الجاهزة للاختبار المباشر:\n";
        echo "   1️⃣ رحلة جارية الآن (Started / Live Tracking): ID {$tripOngoing->id}\n";
        echo "      -> طلاب صعدوا (boarded): طارق الزوي، سارة الزوي\n";
        echo "      -> طفل قيد الانتظار (pending): عمر الورفلي\n";
        echo "      -> طفل تم تخطيه (skipped): خديجة الترهوني\n";
        echo "      -> طفل غائب مسبقاً (absent_pre): أنس الترهوني\n";
        echo "   2️⃣ رحلة قادمة مجدولة (Pending - جاهزة لبدء الرحلة): ID {$tripPendingUpcoming->id}\n";
        echo "   3️⃣ رحلة مكتملة اليوم (Completed Today): ID {$tripCompletedToday->id}\n";
        echo "   4️⃣ رحلة تاريخية البارحة (Historical Completed): ID {$tripHistorical->id}\n";
        echo "   5️⃣ رحلة ملغاة (Cancelled Trip): ID {$tripCancelled->id}\n";
        echo "-------------------------------------------------------------------------\n";
        echo "🔑 الرموز السرية للاختبار (QR Code Tokens):\n";
        echo "   - طارق الزوي:  QR_CHILD_1101_TAREQ\n";
        echo "   - سارة الزوي:   QR_CHILD_1102_SARA\n";
        echo "   - عمر الورفلي:  QR_CHILD_1103_OMAR\n";
        echo "   - خديجة الترهوني: QR_CHILD_1104_KHADIJA\n";
        echo str_repeat("=", 75) . "\n\n";
    }
}
