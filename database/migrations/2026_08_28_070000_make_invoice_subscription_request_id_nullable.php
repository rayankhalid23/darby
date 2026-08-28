<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `invoices` MODIFY COLUMN `subscription_request_id` BIGINT UNSIGNED NULL");
                DB::statement("ALTER TABLE `invoices` MODIFY COLUMN `driver_id` BIGINT UNSIGNED NULL");
            } else {
                Schema::table('invoices', function (Blueprint $table) {
                    $table->unsignedBigInteger('subscription_request_id')->nullable()->change();
                    $table->unsignedBigInteger('driver_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // No down needed
    }
};
