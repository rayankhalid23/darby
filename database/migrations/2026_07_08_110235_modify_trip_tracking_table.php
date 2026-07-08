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
        Schema::table('trip_tracking', function (Blueprint $table) {
            // 1. تغيير اسم العمود من speed_kmh إلى speed ليطابق الـ Service والـ Controller لديك
            if (Schema::hasColumn('trip_tracking', 'speed_kmh')) {
                $table->renameColumn('speed_kmh', 'speed');
            } else {
                // في حال لم يكن موجوداً أصلاً، نقوم بإنشائه مباشرة
                $table->decimal('speed', 5, 2)->nullable()->after('longitude')->comment('Driver speed');
            }

            // 2. التأكد من أن الحقول الباقية تأخذ قيم افتراضية أو Nullable لمنع أي خطأ مستقبلي
            $table->decimal('accuracy', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_tracking', function (Blueprint $table) {
            // إرجاع الاسم القديم في حال تراجعنا عن الهجرة
            if (Schema::hasColumn('trip_tracking', 'speed')) {
                $table->renameColumn('speed', 'speed_kmh');
            }
        });
    }
};