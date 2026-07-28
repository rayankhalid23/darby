<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. تفريغ جدول الأطفال لتجنب أية أخطاء تعارض بيانات قديمة
        DB::table('children')->truncate();

        // 2. محاولة حذف القيد القديم (إن وجد) في بلوك مستقل
        try {
            Schema::table('children', function (Blueprint $table) {
                $table->dropForeign('children_parent_id_foreign');
            });
        } catch (\Throwable $e) {
            // القيد محذوف مسبقاً، يتجاوز النظام الخطأ ويستكمل
        }

        // 3. إضافة القيد الجديد الموجه لجدول parents
        Schema::table('children', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('parents')
                  ->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('children', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
            });
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('children', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }
};