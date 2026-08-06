<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // لا توجد بيانات حالية في الجدول (تحقق مسبق) — لا حاجة لخطوة ترحيل بيانات
        DB::statement("ALTER TABLE trip_stops MODIFY COLUMN status ENUM(
            'pending',
            'absent_pre',
            'absent_late',
            'boarded',
            'dropped_off_school',
            'delivered_home',
            'skipped_unresponsive',
            'dropoff_failed',
            'direct_parent_handling'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trip_stops MODIFY COLUMN status ENUM(
            'pending',
            'absent_pre',
            'picked_up',
            'skipped',
            'dropped_off',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
