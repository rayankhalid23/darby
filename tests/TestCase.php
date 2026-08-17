<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * أدوار النظام الأساسية (id ثابت يعتمد عليه عشرات الاختبارات وseeders الإنتاج).
     * تُدرَج هنا مركزياً بدل تكرارها يدوياً في كل ملف اختبار، وتُلغى تلقائياً
     * مع كل معاملة اختبار (DatabaseTransactions/RefreshDatabase) فتُعاد بأمان في التالي.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            DB::table('roles')->insertOrIgnore([
                ['id' => 1, 'name' => 'Admin',  'display_name' => 'مدير'],
                ['id' => 2, 'name' => 'Driver', 'display_name' => 'سائق'],
                ['id' => 3, 'name' => 'Parent', 'display_name' => 'ولي أمر'],
            ]);
        }
    }
}
