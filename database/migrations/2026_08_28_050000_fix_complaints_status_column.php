<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('complaints')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `complaints` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
            } else {
                Schema::table('complaints', function (Blueprint $table) {
                    $table->string('status', 50)->default('pending')->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('complaints') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `complaints` MODIFY COLUMN `status` ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open'");
        }
    }
};
