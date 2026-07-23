<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. بناء الهيكل الجغرافي (البلديات والمناطق) أولاً
            TripoliGeographySeeder::class,

            // 2. بناء المدارس بعد توفر الـ Zones في قاعدة البيانات
            SchoolSeeder::class,

            // 3. بناء الشروط والأحكام
            ClauseSeeder::class,

            // 4. بناء البيانات الأساسية للنظام (الصلاحيات، الأدوار، المستخدمين الافتراضيين)
            SystemInitialSeeder::class,

            // 5. بيانات وهمية شاملة لاختبار دالة البحث والفلترة والتسعير
            DriverSearchTestSeeder::class,

            // 6. بيانات اختبار E2E للمسارات والرحلات
            E2eTestSeeder::class,

            // 7. بيانات اختبار النظام المالي (فواتير, محافظ, شحن, سحب)
            FinancialTestSeeder::class,
        ]);

        public function run(): void
{
    // استدعاء ملف السيدر الجديد
    $this->call([
        ComplaintAndReviewSeeder::class,
    ]);
}
    }

}