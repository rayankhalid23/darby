<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // نستخدم Schema::table للتعديل على الجدول الحالي
        Schema::table('active_subscriptions', function (Blueprint $table) {
            // نستخدم دالة لنتأكد أن الأعمدة غير موجودة لتجنب أي خطأ
            if (!Schema::hasColumn('active_subscriptions', 'contract_id')) {
                $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            }
            if (!Schema::hasColumn('active_subscriptions', 'status')) {
                $table->string('status')->default('active');
            }
        });
    }

    public function down()
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropColumn(['contract_id', 'status']);
        });
    }
};