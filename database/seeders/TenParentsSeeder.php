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
use App\Models\Shared\SubscriptionRequest;
use App\Models\Shared\Contract;
use App\Models\Shared\ActiveSubscription;
use App\Models\Shared\DriverReview;
use App\Models\Shared\Complaint;
use App\Models\Shared\AbsenceLog;
use App\Models\Shared\Invoice;
use App\Models\Shared\RechargeRequest;
use Carbon\Carbon;

class TenParentsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        echo "🚀 بداية زرع 10 أولياء أمور واشتراكاتهم مع السائقين الموجودين للنظام...\n";

        // 1. جلب السائقين والمدارس والمناطق المتاحة حالياً في قاعدة البيانات
        $drivers = Driver::with('user')->get();
        if ($drivers->isEmpty()) {
            echo "❌ لا يوجد سائقون في قاعدة البيانات! يُرجى تشغيل FullSystemSeeder أولاً.\n";
            return;
        }

        $driver1 = $drivers->first(); // السائق الأول (عبد السلام المصراتي)
        $driver2 = $drivers->skip(1)->first() ?? $driver1; // السائق الثاني (طاهر الزنتاني)

        $school1 = School::first() ?? School::create([
            'name' => 'مدرسة الجيل الجديد الدولية',
            'lat' => 32.89000000,
            'lng' => 13.17000000,
            'address' => 'حي الأندلس',
            'status' => 'approved'
        ]);

        $school2 = School::skip(1)->first() ?? $school1;
        $zoneId = DB::table('zones')->value('id') ?? 1;
        $adminId = DB::table('admins')->value('id') ?? 1;

        // 2. قائمة بيانات الـ 10 أولياء أمور
        $parentsData = [
            [
                'full_name' => 'سالم فتحي البوسيفي',
                'email' => 'parent3@darby.com',
                'phone' => '0913333333',
                'address' => 'حي الأندلس - بالقرب من مصحة المسرة',
                'lat' => 32.89300000,
                'lng' => 13.17600000,
                'children' => [
                    ['name' => 'محمد سالم البوسيفي', 'birth' => '2016-03-12', 'gender' => 'male', 'grade' => 3],
                    ['name' => 'فاطمة سالم البوسيفي', 'birth' => '2014-06-25', 'gender' => 'female', 'grade' => 5],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'سائق ممتاز وخلوق جداً، الالتزام بالمواعيد ممتاز.',
                'complaint' => null
            ],
            [
                'full_name' => 'طارق مصطفى التاجوري',
                'email' => 'parent4@darby.com',
                'phone' => '0914444444',
                'address' => 'بن عاشور - خلف جامع الصقع',
                'lat' => 32.90300000,
                'lng' => 13.21800000,
                'children' => [
                    ['name' => 'علي طارق التاجوري', 'birth' => '2017-09-10', 'gender' => 'male', 'grade' => 2],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'الرحلة مريحة والتكييف يعمل بشكل ممتاز.',
                'complaint' => null
            ],
            [
                'full_name' => 'عمر خالد الزاوي',
                'email' => 'parent5@darby.com',
                'phone' => '0915555555',
                'address' => 'السياحية - بالقرب من مجمع المهن الموسيقية',
                'lat' => 32.89150000,
                'lng' => 13.17200000,
                'children' => [
                    ['name' => 'يوسف عمر الزاوي', 'birth' => '2015-11-05', 'gender' => 'male', 'grade' => 4],
                    ['name' => 'عائشة عمر الزاوي', 'birth' => '2018-01-30', 'gender' => 'female', 'grade' => 1],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'خدمة ممتازة والتواصل مع السائق سهل ومباشر.',
                'complaint' => [
                    'desc' => 'تأخر السائق 15 دقيقة عن موعد الحافلة الصباحي يوم الثلاثاء الماضي.',
                    'status' => 'completed',
                    'action' => 'warning',
                    'details' => 'تم التواصل مع السائق وتنبيهه للالتزام بالجدول الزمني.'
                ]
            ],
            [
                'full_name' => 'هشام عبد الله الشريف',
                'email' => 'parent6@darby.com',
                'phone' => '0916666666',
                'address' => 'النوفليين - بالقرب من الفنار',
                'lat' => 32.89800000,
                'lng' => 13.20500000,
                'children' => [
                    ['name' => 'أحمد هشام الشريف', 'birth' => '2016-07-18', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver2,
                'status' => 'pending',
                'rating' => null,
                'review' => null,
                'complaint' => null
            ],
            [
                'full_name' => 'مصطفى عادل الكيلاني',
                'email' => 'parent7@darby.com',
                'phone' => '0917777777',
                'address' => 'طريق الشط - خف مطعم برج الفاتح',
                'lat' => 32.89700000,
                'lng' => 13.18500000,
                'children' => [
                    ['name' => 'سارة مصطفى الكيلاني', 'birth' => '2014-04-14', 'gender' => 'female', 'grade' => 5],
                    ['name' => 'نور مصطفى الكيلاني', 'birth' => '2017-08-22', 'gender' => 'female', 'grade' => 2],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'سائق محترم جداً وننصح بالتعامل معه.',
                'complaint' => null
            ],
            [
                'full_name' => 'عبد الوهاب إبراهيم المقرحي',
                'email' => 'parent8@darby.com',
                'phone' => '0918888888',
                'address' => 'حي الأندلس - الشارع الغربي',
                'lat' => 32.89400000,
                'lng' => 13.17800000,
                'children' => [
                    ['name' => 'إبراهيم عبد الوهاب المقرحي', 'birth' => '2016-01-11', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'الحافلة نظيفة والرحلة آمنة للاطفال.',
                'complaint' => [
                    'desc' => 'تجاوز السائق السرعة المحددة أثناء رحلة العودة ظهر أمس.',
                    'status' => 'pending',
                    'action' => 'none',
                    'details' => null
                ]
            ],
            [
                'full_name' => 'فتحي خليفة القرقني',
                'email' => 'parent9@darby.com',
                'phone' => '0919999999',
                'address' => 'قرجي - بالقرب من الدوار',
                'lat' => 32.88500000,
                'lng' => 13.16000000,
                'children' => [
                    ['name' => 'مريم فتحي القرقني', 'birth' => '2015-05-19', 'gender' => 'female', 'grade' => 4],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'ممتاز جداً وأخلاق عالية في التعامل مع الأطفال.',
                'complaint' => null
            ],
            [
                'full_name' => 'وليد فرج السويحلي',
                'email' => 'parent10@darby.com',
                'phone' => '0910101010',
                'address' => 'زاوية الدهماني - قرب ميناء طرابلس',
                'lat' => 32.90000000,
                'lng' => 13.19500000,
                'children' => [
                    ['name' => 'فرج وليد السويحلي', 'birth' => '2017-02-28', 'gender' => 'male', 'grade' => 2],
                    ['name' => 'هدى وليد السويحلي', 'birth' => '2019-10-15', 'gender' => 'female', 'grade' => 1],
                ],
                'driver' => $driver2,
                'status' => 'pending',
                'rating' => null,
                'review' => null,
                'complaint' => null
            ],
            [
                'full_name' => 'حسام نوري الغرياني',
                'email' => 'parent11@darby.com',
                'phone' => '0910202020',
                'address' => 'حي الأندلس - عمارات التامين',
                'lat' => 32.89100000,
                'lng' => 13.17400000,
                'children' => [
                    ['name' => 'نوري حسام الغرياني', 'birth' => '2016-12-04', 'gender' => 'male', 'grade' => 3],
                ],
                'driver' => $driver1,
                'status' => 'active',
                'rating' => 5,
                'review' => 'سائق ممتاز ونظام التتبع الدقيق يمنحنا راحة بال كاملة.',
                'complaint' => null
            ],
            [
                'full_name' => 'ناجي عثمان الترهوني',
                'email' => 'parent12@darby.com',
                'phone' => '0910303030',
                'address' => 'فشلوم - بالقرب من المستوصف',
                'lat' => 32.89600000,
                'lng' => 13.21000000,
                'children' => [
                    ['name' => 'عثمان ناجي الترهوني', 'birth' => '2015-08-08', 'gender' => 'male', 'grade' => 4],
                ],
                'driver' => $driver2,
                'status' => 'active',
                'rating' => 4,
                'review' => 'تعامل مهني وراقي من قبل السائق.',
                'complaint' => null
            ],
        ];

        $counter = 100;

        foreach ($parentsData as $index => $data) {
            $counter++;

            // تنظيف الحساب القديم إن وجد لتفادي أي تضارب دون المساس بباقي الجدول
            $oldUser = User::where('email', $data['email'])->first();
            if ($oldUser) {
                $oldParent = ParentModel::where('user_id', $oldUser->id)->first();
                if ($oldParent) {
                    $oldSubReqs = SubscriptionRequest::where('parent_id', $oldParent->id)->pluck('id');
                    DB::table('request_children')->whereIn('request_id', $oldSubReqs)->delete();
                    SubscriptionRequest::where('parent_id', $oldParent->id)->delete();
                    Complaint::where('submitted_by', $oldParent->id)->delete();
                    $oldParent->delete();
                }
                Contract::where('parent_id', $oldUser->id)->delete();
                ActiveSubscription::where('parent_id', $oldUser->id)->delete();
                DriverReview::where('parent_id', $oldUser->id)->delete();
                Address::where('parent_id', $oldUser->id)->delete();
                DB::table('wallets')->where('holder_id', $oldUser->id)->delete();
                $oldUser->forceDelete();
            }

            // أ) إنشاء حساب المستخدم ولي الأمر
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone'],
                'password_hash' => Hash::make('12345678'),
                'role_id' => 3, // ولي أمر
                'is_active' => 1,
                'email_verified_at' => now(),
                'phone_verified_at' => now()
            ]);

            // ب) إنشاء نموذج ولي الأمر
            $parentModel = ParentModel::create([
                'user_id' => $user->id,
                'is_trusted' => 1
            ]);

            // ج) إنشاء عنوان السكن
            $address = Address::create([
                'parent_id' => $user->id,
                'label' => $data['address'],
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'is_default' => true,
                'zone_id' => $zoneId
            ]);

            // د) إنشاء المحفظة المالية لولي الأمر
            DB::table('wallets')->insert([
                'holder_type' => 'App\Models\User',
                'holder_id' => $user->id,
                'name' => 'المحفظة الرئيسية',
                'slug' => 'default',
                'uuid' => Str::uuid()->toString(),
                'balance' => rand(150, 500),
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // هـ) إنشاء الأطفال
            $createdChildren = [];
            $school = ($index % 2 === 0) ? $school1 : $school2;

            foreach ($data['children'] as $childData) {
                $child = Child::create([
                    'parent_id' => $user->id,
                    'school_id' => $school->id,
                    'address_id' => $address->id,
                    'full_name' => $childData['name'],
                    'birth_date' => $childData['birth'],
                    'gender' => $childData['gender'],
                    'grade' => $childData['grade']
                ]);
                $createdChildren[] = $child;
            }

            // و) إنشاء طلب الاشتراك والعقد للاشتراكات المقبولة
            $driver = $data['driver'];
            $reqStatus = ($data['status'] === 'active') ? SubscriptionRequest::STATUS_ACCEPTED : SubscriptionRequest::STATUS_PENDING;

            $subRequest = SubscriptionRequest::create([
                'parent_id' => $parentModel->id,
                'driver_id' => $driver->id,
                'school_id' => $school->id,
                'subscription_type' => 'monthly',
                'direction' => 'two_way',
                'timing' => 'morning',
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->addDays(30)->toDateString(),
                'days_count' => 22,
                'total_price' => count($createdChildren) * 300.00,
                'pickup_time' => '07:00:00',
                'dropoff_time' => '14:00:00',
                'max_waiting_time' => 15,
                'status' => $reqStatus,
                'notes' => 'يرجى توخي الحذر عند التوقف أمام المنزل',
                'children_count' => count($createdChildren)
            ]);

            foreach ($createdChildren as $childObj) {
                DB::table('request_children')->insert([
                    'request_id' => $subRequest->id,
                    'child_id' => $childObj->id,
                    'pickup_address_id' => $address->id,
                    'dropoff_address_id' => $address->id,
                    'home_lat' => $data['lat'],
                    'home_lng' => $data['lng'],
                    'home_label' => 'منزل ' . $data['full_name'],
                    'school_lat' => $school->lat ?? 32.89000000,
                    'school_lng' => $school->lng ?? 13.17000000,
                    'school_label' => $school->name,
                    'price_per_child' => 300.00
                ]);
            }

            if ($data['status'] === 'active') {
                $contractNum = 'CNT-2026-' . $counter;
                $contract = Contract::create([
                    'subscription_request_id' => $subRequest->id,
                    'parent_id' => $user->id,
                    'driver_id' => $driver->user_id ?? $driver->id,
                    'contract_number' => $contractNum,
                    'subscription_type' => 'monthly',
                    'direction' => 'two_way',
                    'timing' => 'morning',
                    'pickup_time' => '07:00:00',
                    'dropoff_time' => '14:00:00',
                    'max_waiting_time' => 15,
                    'start_date' => Carbon::today()->toDateString(),
                    'end_date' => Carbon::today()->addDays(30)->toDateString(),
                    'days_count' => 22,
                    'total_price' => count($createdChildren) * 300.00,
                    'status' => 'active',
                    'signed_at' => now()
                ]);

                foreach ($createdChildren as $childObj) {
                    ActiveSubscription::create([
                        'contract_id' => $contract->id,
                        'child_id' => $childObj->id,
                        'driver_id' => $driver->id,
                        'parent_id' => $user->id,
                        'pickup_lat' => $data['lat'],
                        'pickup_lng' => $data['lng'],
                        'pickup_label' => 'منزل ' . $data['full_name'],
                        'dropoff_lat' => $school->lat ?? 32.89000000,
                        'dropoff_lng' => $school->lng ?? 13.17000000,
                        'dropoff_label' => $school->name,
                        'pickup_time' => '07:00:00',
                        'dropoff_time' => '14:00:00',
                        'status' => 'active'
                    ]);
                }

                // فاتورة للعقد
                Invoice::create([
                    'contract_id' => $contract->id,
                    'parent_id' => $user->id,
                    'driver_id' => $driver->id,
                    'invoice_number' => 'INV-2026-' . $counter,
                    'amount' => count($createdChildren) * 300.00,
                    'status' => 'paid',
                    'type' => 'monthly',
                    'due_date' => Carbon::today()->addDays(5)->toDateString(),
                    'paid_at' => now()
                ]);
            }

            // ز) إنشاء التقييمات إن وجدت
            if ($data['rating']) {
                DriverReview::create([
                    'parent_id' => $user->id,
                    'driver_id' => $driver->id,
                    'contract_id' => isset($contract) ? $contract->id : null,
                    'rating' => $data['rating'],
                    'comment' => $data['review'],
                    'status' => 'active'
                ]);
            }

            // ح) إنشاء الشكاوى إن وجدت
            if ($data['complaint']) {
                Complaint::create([
                    'submitted_by' => $parentModel->id,
                    'against_type' => 'DRIVER',
                    'against_id' => $driver->id,
                    'driver_id' => $driver->id,
                    'description' => $data['complaint']['desc'],
                    'status' => $data['complaint']['status'],
                    'action_taken' => $data['complaint']['action'],
                    'action_details' => $data['complaint']['details'],
                    'resolved_by' => ($data['complaint']['status'] === 'completed') ? $adminId : null,
                    'resolved_at' => ($data['complaint']['status'] === 'completed') ? now() : null
                ]);
            }

            // ط) إضافة تسجيل غياب لأحد الأطفال كسيناريو واقعي
            if ($index === 2 && !empty($createdChildren)) {
                AbsenceLog::create([
                    'child_id' => $createdChildren[0]->id,
                    'absence_date' => Carbon::tomorrow()->toDateString()
                ]);
            }

            // ي) إضافة طلب شحن محفظة قيد الانتظار كسيناريو واقعي
            if ($index === 5) {
                RechargeRequest::create([
                    'parent_id' => $user->id,
                    'amount' => 150.00,
                    'payment_method' => 'Bank Transfer',
                    'status' => 'pending',
                    'reference_number' => 'REF-TEN-' . $counter
                ]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "🎉 تم زرع 10 أولياء أمور بنجاح مع كافة أطفالهم، اشتراكاتهم، عقودهم، تقييماتهم، وشكاويهم دون المساس بقاعدة البيانات!\n";
    }
}
