<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('driver_reviews', function (Blueprint $table) {
            // 1. إسقاط المفتاح الأجنبي المؤثر (إن وجد مرتبطاً بالـ parent_id أو بصيغة مركبة)
            try {
                $table->dropForeign(['parent_id']);
            } catch (\Exception $e) {}

            // 2. إسقاط الفهرس الفريد الآن بكل سهولة بعد فك ارتباطه
            try {
                $table->dropUnique('driver_reviews_parent_id_driver_id_unique');
            } catch (\Exception $e) {}

            // 3. إعادة إنشاء المفتاح الأجنبي لـ parent_id كعلاقة عادية بدون قيد Unique
            try {
                $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
            } catch (\Exception $e) {}
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('driver_reviews', function (Blueprint $table) {
            $table->unique(['parent_id', 'driver_id'], 'driver_reviews_parent_id_driver_id_unique');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};