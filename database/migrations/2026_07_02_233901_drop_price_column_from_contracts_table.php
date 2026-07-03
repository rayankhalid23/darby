<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // حذف العمود غير الضروري
            $table->dropColumn('price');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // إعادة العمود في حال أردت التراجع (مع تحديد القيمة الافتراضية لتجنب الخطأ مستقبلاً)
            $table->decimal('price', 8, 2)->default(0);
        });
    }
};