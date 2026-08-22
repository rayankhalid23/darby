<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `requests` MODIFY COLUMN `status` ENUM('pending', 'acquired', 'accepted', 'rejected', 'contract_offered', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `requests` MODIFY COLUMN `status` ENUM('pending', 'accepted', 'rejected', 'contract_offered') NOT NULL DEFAULT 'pending'");
        }
    }
};
