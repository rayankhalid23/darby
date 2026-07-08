<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تشغيل الهجرة: تعديل قيد الأمان ليرتبط بالجدول الصحيح.
     */
    public function up(): void
    {
        Schema::table('trip_events', function (Blueprint $table) {
            // 1. حذف قيد الأمان القديم الذي يبحث عن جدول subscriptions
            // نستخدم الأسماء الصافية لقواعد البيانات لضمان الحذف الفوري
            DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');

            // 2. إعادة ربط الحقل بجدولك الفعلي والأساسي active_subscriptions
            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('active_subscriptions')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }

    /**
     * التراجع عن الهجرة: إعادة الوضع كما كان (في حال أردت التراجع مستقبلاً).
     */
    public function down(): void
    {
        Schema::table('trip_events', function (Blueprint $table) {
            DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');

            // إعادته للجدول القديم subscriptions لو تم التراجع
            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('subscriptions')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
        });
    }
};