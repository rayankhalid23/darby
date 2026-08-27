<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إضافة 3 أنواع مستندات جديدة للـ enum مع الحفاظ على كل القيم الحالية
        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM(
            'LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD',
            'BOOKLET_PERSONAL_PAGE','STAMP','TECHNICAL_INSPECTION'
        ) NOT NULL");

        // تاريخا انتهاء إضافيان (الدمغة + الفحص الفني) — بنفس نمط insurance_expiry_date الحالي
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->date('stamp_expiry_date')->nullable()->after('insurance_expiry_date')
                  ->comment('تاريخ انتهاء صلاحية الدمغة');
            $table->date('technical_inspection_expiry_date')->nullable()->after('stamp_expiry_date')
                  ->comment('تاريخ انتهاء صلاحية الفحص الفني');
        });
    }

    public function down(): void
    {
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->dropColumn(['stamp_expiry_date', 'technical_inspection_expiry_date']);
        });

        DB::table('driver_documents')->whereIn('doc_type', ['BOOKLET_PERSONAL_PAGE', 'STAMP', 'TECHNICAL_INSPECTION'])->delete();

        DB::statement("ALTER TABLE driver_documents MODIFY COLUMN doc_type ENUM('LICENSE','VEHICLE_LOGBOOK','INSURANCE','CRIMINAL_RECORD') NOT NULL");
    }
};
