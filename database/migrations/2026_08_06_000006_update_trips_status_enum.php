<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // لا توجد بيانات حالية في الجدول (تحقق مسبق) — لا حاجة لخطوة ترحيل بيانات
        DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM(
            'pending',
            'in_progress',
            'completed',
            'suspended_breakdown'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trips MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'planned'");
    }
};
