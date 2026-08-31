<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * السمات (Traits) التي تُعيد بناء قاعدة البيانات من الصفر وتمسح كل البيانات.
     * ممنوع استخدامها ما لم تكن قاعدة البيانات المتصلة قاعدة اختبار مخصصة.
     */
    private const DESTRUCTIVE_TRAITS = [
        \Illuminate\Foundation\Testing\RefreshDatabase::class,
        \Illuminate\Foundation\Testing\DatabaseMigrations::class,
        \Illuminate\Foundation\Testing\DatabaseTruncation::class,
    ];

    /**
     * أسماء قواعد البيانات المسموح تدميرها أثناء الاختبار (قواعد اختبار مخصصة فقط).
     */
    private const ALLOWED_DESTRUCTIVE_DATABASES = [
        'darbi_testing',
        'school_transport_db_test',
    ];

    /**
     * 🛡️ حارس أمان: يمنع أي اختبار من مسح قاعدة بيانات حقيقية.
     *
     * بيئة الاختبار هنا متصلة بنفس قاعدة بيانات التطوير (راجع phpunit.xml الذي لا
     * يحدد DB_DATABASE منفصلاً)، لذا أي اختبار يستخدم RefreshDatabase سينفّذ
     * migrate:fresh ويحذف كل الجداول والبيانات الحقيقية. هذا الفحص يوقف الاختبار
     * بخطأ واضح قبل أن يحدث ذلك بدل اكتشاف الكارثة بعد فوات الأوان.
     */
    protected function assertNotUsingDestructiveTraitsOnRealDatabase(): void
    {
        $usedTraits = class_uses_recursive(static::class);

        $destructive = array_intersect(self::DESTRUCTIVE_TRAITS, $usedTraits);
        if (empty($destructive)) {
            return;
        }

        $database = DB::connection()->getDatabaseName();

        if (in_array($database, self::ALLOWED_DESTRUCTIVE_DATABASES, true)) {
            return;
        }

        $traitNames = implode(', ', array_map('class_basename', $destructive));

        $this->fail(
            "🛑 تم إيقاف الاختبار لحماية بياناتك:\n" .
            "الاختبار [" . static::class . "] يستخدم [{$traitNames}] بينما الاتصال الحالي " .
            "بقاعدة البيانات [{$database}] وهي ليست قاعدة اختبار مخصصة.\n" .
            "تنفيذ هذه السمة كان سيمسح كل الجداول والبيانات.\n" .
            "الحل: استبدلها بـ DatabaseTransactions، أو وجّه الاختبارات لقاعدة اختبار منفصلة."
        );
    }

    /**
     * نقطة الاعتراض الصحيحة: Laravel يُنشئ التطبيق أولاً ثم يُشغّل سمات الاختبار من هنا.
     * لذا نفحص الأمان في هذه اللحظة بالضبط — بعد توفر اتصال قاعدة البيانات
     * وقبل أن تحصل السمة التدميرية على فرصة تنفيذ migrate:fresh.
     */
    protected function setUpTraits()
    {
        $this->assertNotUsingDestructiveTraitsOnRealDatabase();

        return parent::setUpTraits();
    }

    /**
     * أدوار النظام الأساسية (id ثابت يعتمد عليه عشرات الاختبارات وseeders الإنتاج).
     * تُدرَج هنا مركزياً بدل تكرارها يدوياً في كل ملف اختبار، وتُلغى تلقائياً
     * مع كل معاملة اختبار (DatabaseTransactions) فتُعاد بأمان في التالي.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            DB::table('roles')->insertOrIgnore([
                ['id' => 1, 'name' => 'admin',      'display_name' => 'مدير النظام'],
                ['id' => 2, 'name' => 'supervisor', 'display_name' => 'مشرف'],
                ['id' => 3, 'name' => 'parent',     'display_name' => 'ولي أمر'],
                ['id' => 4, 'name' => 'driver',     'display_name' => 'سائق'],
            ]);
        }
    }
}
