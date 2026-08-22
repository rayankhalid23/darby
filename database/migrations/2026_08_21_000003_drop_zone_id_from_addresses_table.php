<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('addresses', 'zone_id')) {
            Schema::table('addresses', function (Blueprint $table) {
                // محاولة حذف المفتاح الأجنبي إن وجد أولاً
                try {
                    $table->dropForeign(['zone_id']);
                } catch (\Throwable $e) {}
                
                $table->dropColumn('zone_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('addresses', 'zone_id')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            });
        }
    }
};
