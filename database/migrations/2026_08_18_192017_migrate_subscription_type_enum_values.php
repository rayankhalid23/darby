<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── drivers ─────────────────────────────────────────────
        DB::statement("UPDATE drivers SET subscription_type = 'single_day' WHERE subscription_type = 'daily'");
        DB::statement("UPDATE drivers SET subscription_type = 'multi_day'  WHERE subscription_type = 'monthly'");
        DB::statement("ALTER TABLE drivers MODIFY COLUMN subscription_type ENUM('single_day','multi_day','both') NOT NULL DEFAULT 'both'");

        // ── child_logistics ──────────────────────────────────────
        DB::statement("UPDATE child_logistics SET subscription_type = 'single_day' WHERE subscription_type = 'daily'");
        DB::statement("UPDATE child_logistics SET subscription_type = 'multi_day'  WHERE subscription_type = 'monthly'");
        DB::statement("ALTER TABLE child_logistics MODIFY COLUMN subscription_type ENUM('single_day','multi_day') NOT NULL DEFAULT 'multi_day'");
    }

    public function down(): void
    {
        // ── drivers ─────────────────────────────────────────────
        DB::statement("UPDATE drivers SET subscription_type = 'daily'   WHERE subscription_type = 'single_day'");
        DB::statement("UPDATE drivers SET subscription_type = 'monthly' WHERE subscription_type = 'multi_day'");
        DB::statement("ALTER TABLE drivers MODIFY COLUMN subscription_type ENUM('daily','monthly','both') NOT NULL DEFAULT 'both'");

        // ── child_logistics ──────────────────────────────────────
        DB::statement("UPDATE child_logistics SET subscription_type = 'daily'   WHERE subscription_type = 'single_day'");
        DB::statement("UPDATE child_logistics SET subscription_type = 'monthly' WHERE subscription_type = 'multi_day'");
        DB::statement("ALTER TABLE child_logistics MODIFY COLUMN subscription_type ENUM('daily','monthly') NOT NULL DEFAULT 'monthly'");
    }
};
