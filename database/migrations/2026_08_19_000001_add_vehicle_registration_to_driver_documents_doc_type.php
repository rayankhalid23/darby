<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add VEHICLE_REGISTRATION to the enum while keeping all existing values intact
        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM('LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD','VEHICLE_REGISTRATION') NOT NULL");
    }

    public function down(): void
    {
        // Revert to original enum (rows with VEHICLE_REGISTRATION must be removed first or will error)
        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM('LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD') NOT NULL");
    }
};
