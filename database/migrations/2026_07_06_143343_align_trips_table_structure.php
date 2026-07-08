<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('trips', function (Blueprint $table) {
        // 1. إصلاح الأعمدة المقطوعة (إعادة تسميتها أو إضافتها بشكل صحيح)
        // ملاحظة: إذا كانت الأعمدة القديمة موجودة فعلاً، يجب حذفها أولاً أو استخدام renameColumn
        $table->timestamp('scheduled_start_time')->nullable()->change();
        $table->timestamp('actual_start_time')->nullable()->change();
        
        // 2. التأكد من وجود الأعمدة الإجبارية
        $table->unsignedBigInteger('route_id')->nullable()->change();
        $table->timestamp('scheduled_at')->nullable()->change();
        
        // 3. توسيع حالة الرحلة
        $table->string('status', 50)->default('planned')->change();
        
        // 4. إضافة طوابع الوقت القياسية للارافل
        if (!Schema::hasColumn('trips', 'created_at')) {
            $table->timestamps();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            //
        });
    }
};
