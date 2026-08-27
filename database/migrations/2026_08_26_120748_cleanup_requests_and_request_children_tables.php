<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل التعديلات دون حذف البيانات
     */
    public function up(): void
    {
        // 1. حذف حقول الإحداثيات الزائدة من جدول request_children
        Schema::table('request_children', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('request_children', 'home_lat')) $columnsToDrop[] = 'home_lat';
            if (Schema::hasColumn('request_children', 'home_lng')) $columnsToDrop[] = 'home_lng';
            if (Schema::hasColumn('request_children', 'home_label')) $columnsToDrop[] = 'home_label';
            if (Schema::hasColumn('request_children', 'school_lat')) $columnsToDrop[] = 'school_lat';
            if (Schema::hasColumn('request_children', 'school_lng')) $columnsToDrop[] = 'school_lng';
            if (Schema::hasColumn('request_children', 'school_label')) $columnsToDrop[] = 'school_label';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // 2. حذف الحقول العامة الزائدة من جدول requests الرئيسي
        Schema::table('requests', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('requests', 'subscription_type')) $columnsToDrop[] = 'subscription_type';
            if (Schema::hasColumn('requests', 'direction')) $columnsToDrop[] = 'direction';
            if (Schema::hasColumn('requests', 'start_date')) $columnsToDrop[] = 'start_date';
            if (Schema::hasColumn('requests', 'end_date')) $columnsToDrop[] = 'end_date';
            if (Schema::hasColumn('requests', 'days_count')) $columnsToDrop[] = 'days_count';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * التراجع عن التعديلات في حال الحاجة
     */
    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            $table->decimal('home_lat', 10, 8)->nullable();
            $table->decimal('home_lng', 11, 8)->nullable();
            $table->string('home_label')->nullable();
            $table->decimal('school_lat', 10, 8)->nullable();
            $table->decimal('school_lng', 11, 8)->nullable();
            $table->string('school_label')->nullable();
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->string('subscription_type')->nullable();
            $table->string('direction')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('days_count')->nullable();
        });
    }
};