<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_events', function (Blueprint $table) {
            // تم التعطيل بالكامل لأن SQLite لا يدعم DROP FOREIGN KEY
            // ولأننا نبني قاعدة البيانات من الصفر، لا نحتاج لتعديل القيد هنا محلياً
            
            // DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');
            
            // $table->foreign('subscription_id')
            //       ->references('id')
            //       ->on('active_subscriptions')
            //       ->onDelete('restrict')
            //       ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('trip_events', function (Blueprint $table) {
            // تم التعطيل
            // DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');

            // $table->foreign('subscription_id')
            //       ->references('id')
            //       ->on('subscriptions')
            //       ->onDelete('restrict')
            //       ->onUpdate('cascade');
        });
    }
};