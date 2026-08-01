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
            TripoliGeographySeeder::class,
            ZoneSeeder::class,
            SchoolSeeder::class,
            ClauseSeeder::class,
            SystemInitialSeeder::class,

            // 2. البنية الشاملة لكافة بيانات واشتراكات ورجلات النظام
            FullSystemSeeder::class,
        ]);
    }
}