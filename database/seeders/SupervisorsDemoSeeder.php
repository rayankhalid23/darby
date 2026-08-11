<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 🌱 بيانات وهمية لقسم "إدارة المشرفين" في لوحة التحكم
 *
 * التشغيل: php artisan db:seed --class=SupervisorsDemoSeeder
 * الغرض: تعبئة الجدول ببيانات واقعية حتى يتمكن مطور الواجهة من ربط
 *        شاشات (عرض الكل / عرض مشرف / تعديل / حذف) ورؤية نتائج فعلية.
 */
class SupervisorsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 1. التأكد من وجود مدير النظام الأساسي ليكون هو منشئ بقية المشرفين
            $rootAdminUser = User::withTrashed()->firstOrCreate(
                ['email' => 'admin@derbi.ly'],
                [
                    'full_name'     => 'أحمد المدير',
                    'phone_number'  => '0900000000',
                    'password_hash' => Hash::make('password123'),
                    'role_id'       => 1,
                    'is_active'     => 1,
                ]
            );

            Admin::firstOrCreate(
                ['user_id' => $rootAdminUser->id],
                ['created_by' => $rootAdminUser->id]
            );

            // 2. قائمة المشرفين الوهميين المطلوب إظهارهم للواجهة
            $supervisors = [
                [
                    'full_name'    => 'علي عمر المشرف',
                    'email'        => 'ali.supervisor@derbi.ly',
                    'phone_number' => '0911002200',
                    'is_active'    => 1,
                ],
                [
                    'full_name'    => 'فاطمة محمد الترهوني',
                    'email'        => 'fatima.supervisor@derbi.ly',
                    'phone_number' => '0922003300',
                    'is_active'    => 1,
                ],
                [
                    'full_name'    => 'خالد سالم الزنتاني',
                    'email'        => 'khaled.supervisor@derbi.ly',
                    'phone_number' => '0913114455',
                    'is_active'    => 1,
                ],
                [
                    'full_name'    => 'مريم عبدالله المصراتي',
                    'email'        => 'mariam.supervisor@derbi.ly',
                    'phone_number' => '0924225566',
                    'is_active'    => 1,
                ],
                [
                    'full_name'    => 'يوسف مصطفى بن سعيد',
                    'email'        => 'youssef.supervisor@derbi.ly',
                    'phone_number' => '0915336677',
                    'is_active'    => 0, // مشرف موقوف لاختبار عرض الحالة في الواجهة
                ],
                [
                    'full_name'    => 'هدى إبراهيم القذافي',
                    'email'        => 'huda.supervisor@derbi.ly',
                    'phone_number' => '0926447788',
                    'is_active'    => 1,
                ],
                [
                    'full_name'    => 'عمر ناصر الورفلي',
                    'email'        => 'omar.supervisor@derbi.ly',
                    'phone_number' => '0917558899',
                    'is_active'    => 0,
                ],
                [
                    'full_name'    => 'سارة توفيق العجيلي',
                    'email'        => 'sara.supervisor@derbi.ly',
                    'phone_number' => '0928669900',
                    'is_active'    => 1,
                ],
            ];

            foreach ($supervisors as $row) {
                $user = User::withTrashed()->firstOrCreate(
                    ['email' => $row['email']],
                    [
                        'full_name'     => $row['full_name'],
                        'phone_number'  => $row['phone_number'],
                        'password_hash' => Hash::make('password123'),
                        'role_id'       => 2, // مشرف
                        'is_active'     => $row['is_active'],
                        'avatar_url'    => null,
                    ]
                );

                // إعادة تفعيل الحساب لو كان محذوفاً سابقاً بالحذف الناعم
                if ($user->trashed()) {
                    $user->restore();
                }

                Admin::firstOrCreate(
                    ['user_id' => $user->id],
                    ['created_by' => $rootAdminUser->id]
                );
            }

            $this->command?->info('✅ تمت إضافة ' . count($supervisors) . ' مشرف وهمي بنجاح. كلمة المرور للجميع: password123');
        });
    }
}
