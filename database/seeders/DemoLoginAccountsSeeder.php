<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 🔑 حسابات دخول جاهزة للاختبار (مدير نظام + مشرف).
 *
 * التشغيل: php artisan db:seed --class=DemoLoginAccountsSeeder
 *
 * يضمن وجود حسابين بكلمة مرور معروفة ومُفعَّلين، ويعيد ضبط كلمة المرور
 * في كل تشغيل حتى لو تغيّرت. آمن وقابل لإعادة التشغيل.
 *
 * ⚠️ لا يمس أي حساب آخر في النظام — ينشئ/يحدّث هذين الحسابين فقط.
 */
class DemoLoginAccountsSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private const ACCOUNTS = [
        [
            'full_name'    => 'مدير النظام التجريبي',
            'email'        => 'superadmin@derbi.ly',
            'phone_number' => '0900111222',
            'role_id'      => 1, // مدير النظام
        ],
        [
            'full_name'    => 'مشرف تجريبي للدخول',
            'email'        => 'supervisor@derbi.ly',
            'phone_number' => '0900333444',
            'role_id'      => 2, // مشرف
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = null;

            foreach (self::ACCOUNTS as $account) {
                // الهاتف والبريد كلاهما فريد في قاعدة البيانات، فنتحقق أن الرقم
                // غير محجوز لحساب آخر (سائق أو ولي أمر) قبل محاولة الحفظ
                $phoneOwner = User::withTrashed()
                    ->where('phone_number', $account['phone_number'])
                    ->where('email', '!=', $account['email'])
                    ->first();

                if ($phoneOwner) {
                    $this->command?->warn(
                        "⚠️ تخطي {$account['full_name']}: الرقم {$account['phone_number']} محجوز لحساب \"{$phoneOwner->full_name}\"."
                    );
                    continue;
                }

                $user = User::withTrashed()->where('email', $account['email'])->first();

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }
                    $user->update([
                        'full_name'     => $account['full_name'],
                        'phone_number'  => $account['phone_number'],
                        'password_hash' => Hash::make(self::PASSWORD),
                        'role_id'       => $account['role_id'],
                        'is_active'     => 1,
                    ]);
                } else {
                    $user = User::create([
                        'full_name'     => $account['full_name'],
                        'email'         => $account['email'],
                        'phone_number'  => $account['phone_number'],
                        'password_hash' => Hash::make(self::PASSWORD),
                        'role_id'       => $account['role_id'],
                        'is_active'     => 1,
                    ]);
                }

                // مدير النظام هو منشئ نفسه، والمشرف ينسب إليه
                $rootId ??= $user->id;

                Admin::updateOrCreate(
                    ['user_id' => $user->id],
                    ['created_by' => $rootId]
                );

                $this->command?->info("✅ {$account['full_name']} — هاتف: {$account['phone_number']}");
            }

            $this->command?->info('🔑 كلمة المرور للحسابين: ' . self::PASSWORD);
        });
    }
}
