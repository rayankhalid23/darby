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
        // 1. إزالة المفتاح الأجنبي والعمودين من جدول requests
        Schema::table('requests', function (Blueprint $table) {
            // حذف المفتاح الأجنبي أولاً لتفادي أخطاء MySQL Foreign Key Constraint
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'timing']);
        });

        // 2. إضافة عمود timing إلى جدول request_children (أو الجدول المسمى لديك)
        // ملاحظة: تأكد من اسم الجدول في قاعدة البيانات إذا كان request_children أو غيره
        Schema::table('request_children', function (Blueprint $table) {
            $table->string('timing', 50)->nullable()->after('trip_direction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('timing', 50)->nullable();
        });

        Schema::table('request_children', function (Blueprint $table) {
            $table->dropColumn('timing');
        });
    }
};