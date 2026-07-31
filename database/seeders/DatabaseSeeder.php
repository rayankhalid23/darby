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
            // 1. البنية التحتية، الجغرافيا، والبيانات الأساسية
            ZoneSeeder::class,
            TripoliGeographySeeder::class,
            SchoolSeeder::class,
            ClauseSeeder::class,
            SystemInitialSeeder::class,

            // 2. حسابات أولياء الأمور، الأطفال، والاشتراكات
            TenParentsSeeder::class,
            AddChildrenAndSubscriptionsSeeder::class,

            // 3. الرحلات النشطة، التقييمات، والشكاوى
            ActiveTripsSeeder::class,
            Children123TripsSeeder::class,
            ComplaintAndReviewSeeder::class,

            // 4. بيانات الاختبارات الشاملة والنظام المالي
            DriverSearchTestSeeder::class,
            FinancialTestSeeder::class,
            TestingDataSeeder::class,
            E2eTestSeeder::class,
            FullSystemSeeder::class,
        ]);
    }
}