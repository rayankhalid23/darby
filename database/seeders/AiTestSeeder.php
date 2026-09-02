<?php

namespace Database\Seeders;

use App\Models\Driver\Driver;
use App\Models\Shared\DriverReview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء حسابات المستخدمين المرجعية للسائقين وأولياء الأمور
        $p1 = User::firstOrCreate(['phone_number' => '0920000001'], [
            'full_name'     => 'ولي الأمر الأول (P_01)',
            'email'         => 'parent01@example.com',
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => true,
        ]);

        $p2 = User::firstOrCreate(['phone_number' => '0920000002'], [
            'full_name'     => 'ولي الأمر الثاني (P_02)',
            'email'         => 'parent02@example.com',
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => true,
        ]);

        $p3 = User::firstOrCreate(['phone_number' => '0920000003'], [
            'full_name'     => 'ولي الأمر الثالث (P_03)',
            'email'         => 'parent03@example.com',
            'password_hash' => bcrypt('password123'),
            'role_id'       => 3,
            'is_active'     => true,
        ]);

        // السائقين الخمسة: 101 إلى 105
        $scenariosData = [
            101 => [
                'name' => 'سائق سيناريو 1 - سرعة وتلفظ',
                'rating' => 4.8,
                'reviews' => [
                    ['parent_id' => $p1->id, 'rating' => 1, 'comment' => 'السائق يقود بسرعة جنونية وتلفظ بعبارات غير لائقة أمام الأطفال.', 'days_ago' => 2],
                ],
            ],
            102 => [
                'name' => 'سائق سيناريو 2 - تأخير وتكييف 3 أولياء أمر',
                'rating' => 4.8,
                'reviews' => [
                    ['parent_id' => $p1->id, 'rating' => 2, 'comment' => 'السائق تأخر 20 دقيقة عن الموعد.', 'days_ago' => 4],
                    ['parent_id' => $p2->id, 'rating' => 2, 'comment' => 'التكييف عطلان في الحافلة والأطفال ينزعجون.', 'days_ago' => 2],
                    ['parent_id' => $p3->id, 'rating' => 2, 'comment' => 'تأخر مرة أخرى ولم يشغل التكييف.', 'days_ago' => 1],
                ],
            ],
            103 => [
                'name' => 'سائق سيناريو 3 - 3 تقييمات من نفس ولي الأمر',
                'rating' => 4.8,
                'reviews' => [
                    ['parent_id' => $p1->id, 'rating' => 2, 'comment' => 'السائق تأخر عن الموعد.', 'days_ago' => 5],
                    ['parent_id' => $p1->id, 'rating' => 2, 'comment' => 'السيارة لم تكن نظيفة اليوم.', 'days_ago' => 3],
                    ['parent_id' => $p1->id, 'rating' => 2, 'comment' => 'تأخر مرة أخرى 10 دقائق.', 'days_ago' => 1],
                ],
            ],
            104 => [
                'name' => 'سائق سيناريو 4 - تأخير بسبب الزحمة والطقس',
                'rating' => 4.8,
                'reviews' => [
                    ['parent_id' => $p1->id, 'rating' => 3, 'comment' => 'السائق تأخر نصف ساعة بسبب زحمة السير الخانقة وركود المرور.', 'days_ago' => 3],
                    ['parent_id' => $p2->id, 'rating' => 3, 'comment' => 'تأخير بسبب الأحوال الجوية وسقوط الأمطار اليوم.', 'days_ago' => 1],
                ],
            ],
            105 => [
                'name' => 'سائق سيناريو 5 - سائق ممتاز',
                'rating' => 4.2,
                'reviews' => [
                    ['parent_id' => $p1->id, 'rating' => 5, 'comment' => 'سائق ممتاز ومنتظم جداً في مواعيده.', 'days_ago' => 3],
                    ['parent_id' => $p2->id, 'rating' => 5, 'comment' => 'تعامل راقي والسيارة نظيفة ومكيفة.', 'days_ago' => 1],
                ],
            ],
        ];

        foreach ($scenariosData as $driverId => $data) {
            $user = User::firstOrCreate(['phone_number' => "0910000" . $driverId], [
                'full_name'     => $data['name'],
                'email'         => "driver{$driverId}@example.com",
                'password_hash' => bcrypt('password123'),
                'role_id'       => 2,
                'is_active'     => true,
            ]);

            // إنشاء/تحديث السائق بالـ ID المحدد 101-105 بأمان
            $driver = Driver::find($driverId);
            if (!$driver) {
                // إدراج صريح للمعّرف
                DB::table('drivers')->insert([
                    'id'            => $driverId,
                    'user_id'       => $user->id,
                    'status'        => 'Approved',
                    'is_searchable' => true,
                    'rating_avg'    => $data['rating'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $driver = Driver::find($driverId);
            } else {
                $driver->update([
                    'user_id'       => $user->id,
                    'status'        => 'Approved',
                    'is_searchable' => true,
                    'rating_avg'    => $data['rating'],
                ]);
            }

            // مسح أي تقييمات تجريبية سابقة لهذا السائق لإتاحة الفحص النظيف
            DriverReview::where('driver_id', $driverId)->forceDelete();

            foreach ($data['reviews'] as $rev) {
                $review = DriverReview::create([
                    'driver_id' => $driverId,
                    'parent_id' => $rev['parent_id'],
                    'rating'    => $rev['rating'],
                    'comment'   => $rev['comment'],
                    'status'    => 'active',
                ]);
                $review->created_at = now()->subDays($rev['days_ago']);
                $review->save();
            }
        }
    }
}
