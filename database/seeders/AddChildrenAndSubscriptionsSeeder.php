<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
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
use Carbon\Carbon;

class AddChildrenAndSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        echo "🚀 بدء إضافة الأطفال والاشتراكات لأولياء الأمور الموجودين في قاعدة البيانات بدون مسح أي بيانات...\n";

        // 1. جلب المدارس والسائقين والمناطق المتاحة
        $drivers = Driver::with('user')->get();
        if ($drivers->isEmpty()) {
            echo "❌ لا يوجد سائقون في قاعدة البيانات! يُرجى التأكد من وجود سائقين أولاً.\n";
            return;
        }

        $schools = School::all();
        if ($schools->isEmpty()) {
            $school1 = School::create([
                'name' => 'مدرسة الجيل الجديد الدولية',
                'lat' => 32.89000000,
                'lng' => 13.17000000,
                'address' => 'حي الأندلس - طرابلس',
                'status' => 'approved'
            ]);
            $schools = collect([$school1]);
        }
        $school1 = $schools->first();
        $school2 = $schools->skip(1)->first() ?? $school1;

        $zoneId = DB::table('zones')->value('id') ?? 1;

        // 2. جلب كافة أولياء الأمور المسجلين في النظام
        $parents = ParentModel::with('user')->get();

        if ($parents->isEmpty()) {
            $parentUsers = User::where('role_id', 3)->get();
            foreach ($parentUsers as $pUser) {
                ParentModel::firstOrCreate(['user_id' => $pUser->id], ['is_trusted' => 1]);
            }
            $parents = ParentModel::with('user')->get();
        }

        if ($parents->isEmpty()) {
            echo "❌ لا يوجد أولياء أمور في النظام لربطهم!\n";
            return;
        }

        $sampleChildrenData = [
            [
                ['name' => 'علي {lastname}', 'birth' => '2016-04-10', 'gender' => 'male', 'grade' => 3, 'notes' => 'لا توجد ملاحظات طبية'],
                ['name' => 'سارة {lastname}', 'birth' => '2018-09-15', 'gender' => 'female', 'grade' => 1, 'notes' => 'حساسية بسيطة من الغبار']
            ],
            [
                ['name' => 'محمد {lastname}', 'birth' => '2015-03-12', 'gender' => 'male', 'grade' => 4, 'notes' => 'يرتدي نظارات طبية'],
                ['name' => 'فاطمة {lastname}', 'birth' => '2017-06-25', 'gender' => 'female', 'grade' => 2, 'notes' => 'لا توجد']
            ],
            [
                ['name' => 'أنس {lastname}', 'birth' => '2016-11-20', 'gender' => 'male', 'grade' => 3, 'notes' => 'لا توجد']
            ],
            [
                ['name' => 'يوسف {lastname}', 'birth' => '2015-08-05', 'gender' => 'male', 'grade' => 4, 'notes' => 'لا توجد'],
                ['name' => 'عائشة {lastname}', 'birth' => '2018-01-30', 'gender' => 'female', 'grade' => 1, 'notes' => 'لا توجد']
            ],
            [
                ['name' => 'عبد الله {lastname}', 'birth' => '2014-07-14', 'gender' => 'male', 'grade' => 5, 'notes' => 'لا توجد']
            ],
            [
                ['name' => 'طارق {lastname}', 'birth' => '2017-02-18', 'gender' => 'male', 'grade' => 2, 'notes' => 'لا توجد'],
                ['name' => 'ليلى {lastname}', 'birth' => '2019-05-10', 'gender' => 'female', 'grade' => 1, 'notes' => 'لا توجد']
            ]
        ];

        $counter = 300;

        foreach ($parents as $index => $parentModel) {
            $user = $parentModel->user;
            if (!$user) continue;

            $counter++;

            // التأكد من وجود عنوان سكن لولي الأمر
            $address = Address::where('parent_id', $user->id)->first();
            if (!$address) {
                $address = Address::create([
                    'parent_id'  => $user->id,
                    'label'      => 'منزل ' . ($user->full_name ?? $user->name),
                    'lat'        => 32.89000000 + ($index * 0.002),
                    'lng'        => 13.17000000 + ($index * 0.002),
                    'is_default' => true,
                    'zone_id'    => $zoneId
                ]);
            }

            // التأكد من وجود محفظة مالية
            $hasWallet = DB::table('wallets')->where('holder_id', $user->id)->exists();
            if (!$hasWallet) {
                DB::table('wallets')->insert([
                    'holder_type'    => 'App\Models\User',
                    'holder_id'      => $user->id,
                    'name'           => 'المحفظة الرئيسية',
                    'slug'           => 'default-' . $user->id,
                    'uuid'           => Str::uuid()->toString(),
                    'balance'        => rand(200, 600),
                    'decimal_places' => 2,
                    'created_at'     => now(),
                    'updated_at'     => now()
                ]);
            }

            // فحص الأطفال الحاليين لولي الأمر
            $existingChildren = Child::where('parent_id', $parentModel->id)->get();

            // اختار المدرسة والسائق بالتناوب
            $school = ($index % 2 === 0) ? $school1 : $school2;
            $driver = $drivers->get($index % $drivers->count());

            // إنشاء أطفال إذا لم يكن لديه أطفال
            if ($existingChildren->isEmpty()) {
                $nameParts = explode(' ', trim($user->full_name ?? $user->name));
                $lastName = count($nameParts) > 1 ? end($nameParts) : 'الترهوني';

                $childrenTemplate = $sampleChildrenData[$index % count($sampleChildrenData)];
                $createdChildren = [];

                foreach ($childrenTemplate as $cData) {
                    $cName = str_replace('{lastname}', $lastName, $cData['name']);
                    $child = Child::create([
                        'parent_id'     => $parentModel->id,
                        'school_id'     => $school->id,
                        'address_id'    => $address->id,
                        'full_name'     => $cName,
                        'birth_date'    => $cData['birth'],
                        'gender'        => $cData['gender'],
                        'grade'         => $cData['grade'],
                        'medical_notes' => $cData['notes']
                    ]);
                    $createdChildren[] = $child;
                }
            } else {
                $createdChildren = $existingChildren->all();
            }

            // فحص وجود اشتراك فعال أو طلب سابق لولي الأمر تجنباً للتكرار
            $hasActiveSub = ActiveSubscription::where('parent_id', $user->id)->exists();
            if ($hasActiveSub) {
                echo "ℹ️ ولي الأمر ({$user->full_name}) لديه اشتراك نشط سابقاً. تم تخطيه.\n";
                continue;
            }

            // تحديد حالة الاشتراك (غالبية مقبولة ونشطة، وبعضها قيد الانتظار)
            $isPending = ($index % 5 === 4); // كل 5 أسر، واحدة تكون pending
            $reqStatus = $isPending ? SubscriptionRequest::STATUS_PENDING : SubscriptionRequest::STATUS_ACCEPTED;

            // 1. إنشاء طلب الاشتراك
            $subRequest = SubscriptionRequest::create([
                'parent_id'         => $parentModel->id,
                'driver_id'         => $driver->id,
                'school_id'         => $school->id,
                'subscription_type' => 'monthly',
                'direction'         => 'two_way',
                'timing'            => 'morning',
                'start_date'        => Carbon::today()->toDateString(),
                'end_date'          => Carbon::today()->addDays(30)->toDateString(),
                'days_count'        => 22,
                'total_price'       => count($createdChildren) * 300.00,
                'pickup_time'       => '07:00:00',
                'dropoff_time'      => '14:00:00',
                'max_waiting_time'  => 15,
                'status'            => $reqStatus,
                'notes'             => 'يرجى توخي الحذر والالتزام بالمواعيد أمام المنزل',
                'children_count'    => count($createdChildren)
            ]);

            // 2. ربط الأطفال بطلب الاشتراك
            foreach ($createdChildren as $childObj) {
                DB::table('request_children')->insert([
                    'request_id'         => $subRequest->id,
                    'child_id'           => $childObj->id,
                    'pickup_address_id'  => $address->id,
                    'dropoff_address_id' => $address->id,
                    'home_lat'           => $address->lat ?? 32.89000000,
                    'home_lng'           => $address->lng ?? 13.17000000,
                    'home_label'         => $address->label ?? 'منزل ولي الأمر',
                    'school_lat'         => $school->lat ?? 32.89000000,
                    'school_lng'         => $school->lng ?? 13.17000000,
                    'school_label'       => $school->name,
                    'price_per_child'    => 300.00
                ]);
            }

            // 3. إذا كان طلب الاشتراك مقترناً بعقد نشط (STATUS_ACCEPTED)
            if ($reqStatus === SubscriptionRequest::STATUS_ACCEPTED) {
                $contractNum = 'CNT-2026-SUB-' . $counter;
                $contract = Contract::create([
                    'subscription_request_id' => $subRequest->id,
                    'parent_id'               => $user->id,
                    'driver_id'               => $driver->user_id ?? $driver->id,
                    'contract_number'         => $contractNum,
                    'subscription_type'       => 'monthly',
                    'direction'               => 'two_way',
                    'timing'                  => 'morning',
                    'pickup_time'             => '07:00:00',
                    'dropoff_time'            => '14:00:00',
                    'max_waiting_time'        => 15,
                    'start_date'              => Carbon::today()->toDateString(),
                    'end_date'                => Carbon::today()->addDays(30)->toDateString(),
                    'days_count'              => 22,
                    'total_price'             => count($createdChildren) * 300.00,
                    'status'                  => 'active',
                    'signed_at'               => now()
                ]);

                foreach ($createdChildren as $childObj) {
                    ActiveSubscription::create([
                        'contract_id'   => $contract->id,
                        'child_id'      => $childObj->id,
                        'driver_id'     => $driver->id,
                        'parent_id'     => $user->id,
                        'pickup_lat'    => $address->lat ?? 32.89000000,
                        'pickup_lng'    => $address->lng ?? 13.17000000,
                        'pickup_label'  => $address->label ?? 'منزل ولي الأمر',
                        'dropoff_lat'   => $school->lat ?? 32.89000000,
                        'dropoff_lng'   => $school->lng ?? 13.17000000,
                        'dropoff_label' => $school->name,
                        'pickup_time'   => '07:00:00',
                        'dropoff_time'  => '14:00:00',
                        'status'        => 'active'
                    ]);
                }

                // فاتورة مدفوعة
                Invoice::create([
                    'contract_id'     => $contract->id,
                    'parent_id'       => $user->id,
                    'driver_id'       => $driver->id,
                    'invoice_number'  => 'INV-2026-SUB-' . $counter,
                    'amount'          => count($createdChildren) * 300.00,
                    'status'          => 'paid',
                    'type'            => 'monthly',
                    'due_date'        => Carbon::today()->addDays(5)->toDateString(),
                    'paid_at'         => now()
                ]);

                // إضافة تقييم للسائق (parent_id هنا يشير لجدول parents.id)
                DriverReview::create([
                    'parent_id'   => $parentModel->id,
                    'driver_id'   => $driver->id,
                    'contract_id' => $contract->id,
                    'rating'      => rand(4, 5),
                    'comment'     => 'خدمة تتبع واشتراك ممتازة وسائق خلوق جداً.',
                    'status'      => 'active'
                ]);

                // شكوى تجريبية واقعية لبعض الحالات
                if ($index === 1) {
                    Complaint::create([
                        'submitted_by'   => $parentModel->id,
                        'against_type'   => 'DRIVER',
                        'against_id'     => $driver->id,
                        'driver_id'      => $driver->id,
                        'description'    => 'تأخر السائق 10 دقائق عن موعد الاستلام صباح اليوم.',
                        'status'         => 'pending',
                        'action_taken'   => 'none',
                        'action_details' => null
                    ]);
                }

                // غياب تجريبي لأحد الأطفال
                if ($index === 2 && !empty($createdChildren)) {
                    AbsenceLog::create([
                        'child_id'     => $createdChildren[0]->id,
                        'absence_date' => Carbon::tomorrow()->toDateString(),
                        'absence_type' => 'both'
                    ]);
                }
            }

            echo "✅ تم ربط ولي الأمر ({$user->full_name}) بالعديد من الأطفال والاشتراكات بنجاح!\n";
        }

        echo "🎉 اكتمل زرع الأطفال والاشتراكات لجميع أولياء الأمور الحالية بنجاح تام!\n";
    }
}
