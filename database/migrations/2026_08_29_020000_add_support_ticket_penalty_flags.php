<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول عقوبات تشغيلية خفيفة (أقل حدة من الإيقاف الكامل) تُطبَّق من داخل
 * تذاكر الدعم الفني: إخفاء سائق من نتائج البحث دون إيقاف كامل حسابه،
 * ومنع ولي أمر من إنشاء اشتراكات جديدة دون التأثير على اشتراكاته القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('hidden_from_search')->default(false)->after('status');
        });

        Schema::table('parents', function (Blueprint $table) {
            $table->boolean('booking_blocked')->default(false)->after('is_trusted');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('hidden_from_search');
        });

        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn('booking_blocked');
        });
    }
};
