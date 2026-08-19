<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تم تعطيل استعلام SQL المباشر لتفادي خطأ SQLite في بيئة التطوير المحلية
        // DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'contract_offered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // تم التعطيل
        // DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'contract_offered') NOT NULL DEFAULT 'pending'");
    }
};