<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. الجدول الرئيسي: إضافة قيمة الخصم والسعر الإجمالي بعد الخصم
        Schema::table('requests', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount_after_discount', 10, 2)->default(0);
        });

        // 2. جدول تفاصيل الأطفال: إضافة صافي سعر السائق بعد خصم عمولة المنصة
        Schema::table('request_children', function (Blueprint $table) {
            $table->decimal('driver_net_price', 10, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'total_amount_after_discount']);
        });

        Schema::table('request_children', function (Blueprint $table) {
            $table->dropColumn(['driver_net_price']);
        });
    }
};