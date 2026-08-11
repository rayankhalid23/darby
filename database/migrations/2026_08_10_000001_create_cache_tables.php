<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول الكاش القياسية في لارافيل.
 *
 * ملف الهجرة 0001_01_01_000001_create_cache_table.php يحمل هذا الاسم
 * لكنه في الواقع يبني السكيما الأساسية للمشروع ولا ينشئ جدول cache،
 * ولهذا كان إعداد CACHE_STORE=database في .env يفشل مع الخطأ:
 *   "Table 'laravel.cache' doesn't exist"
 *
 * وهذا كان يعطّل كل ما يعتمد على الكاش، ومنه تأكيد تغيير البريد الإلكتروني
 * للمشرفين ولأولياء الأمور.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
