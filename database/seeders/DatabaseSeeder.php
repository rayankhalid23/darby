<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * الترتيب الصحيح للـ Seeder:
     *   1. MainSystemSeeder  ← البيانات الأساسية (أدوار، جغرافيا، مدارس، مستخدمون)
     *   2. ClauseSeeder      ← بنود العقود
     *
     * لتشغيل السيدر الشامل فقط:
     *   php artisan db:seed --class=MainSystemSeeder
     *
     * لتشغيل جميع السيدرات:
     *   php artisan db:seed
     */
    public function run(): void
    {
        $this->call([
            MainSystemSeeder::class,
            ClauseSeeder::class,
        ]);
    }
}