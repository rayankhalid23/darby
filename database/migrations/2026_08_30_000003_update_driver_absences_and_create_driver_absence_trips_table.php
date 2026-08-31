<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_absences', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_absences', 'reason')) {
                $table->string('reason', 1000)->nullable()->after('absence_date');
            }
            if (!Schema::hasColumn('driver_absences', 'status')) {
                $table->string('status', 30)->default('pending')->after('reason'); // pending, approved, rejected
            }
            if (!Schema::hasColumn('driver_absences', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('driver_absences', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('driver_absences', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('reviewed_at');
            }
        });

        // إنشاء جدول الربط بين الغيابات والرحلات (Many-to-Many)
        if (!Schema::hasTable('driver_absence_trips')) {
            Schema::create('driver_absence_trips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_absence_id')->constrained('driver_absences')->onDelete('cascade');
                $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['driver_absence_id', 'trip_id'], 'driver_absence_trip_unique');
            });
        }

        // إتاحة تكرار التواريخ للسائق في حال رغبته في تسجيل طلبات متعددة لرحلات مختلفة بنفس اليوم
        try {
            Schema::table('driver_absences', function (Blueprint $table) {
                $table->dropUnique(['driver_id', 'absence_date']);
            });
        } catch (\Throwable $e) {
            // تجاهل إن كان قد تم تعديله مسبقاً
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_absence_trips');

        Schema::table('driver_absences', function (Blueprint $table) {
            if (Schema::hasColumn('driver_absences', 'admin_notes')) {
                $table->dropColumn('admin_notes');
            }
            if (Schema::hasColumn('driver_absences', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('driver_absences', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('driver_absences', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('driver_absences', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
