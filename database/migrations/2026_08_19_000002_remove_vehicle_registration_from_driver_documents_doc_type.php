<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // إزالة قيمة VEHICLE_REGISTRATION من الـ enum بعد التأكد من عدم استخدامها في أي سجل
        DB::table('driver_documents')->where('doc_type', 'VEHICLE_REGISTRATION')->delete();

        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM('LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM('LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD','VEHICLE_REGISTRATION') NOT NULL");
    }
};
