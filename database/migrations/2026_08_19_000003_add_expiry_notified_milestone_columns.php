<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تتبّع آخر "نقطة تذكير" (15/7/3/0 يوم) أُرسلت للسائق عن انتهاء رخصته
        // لمنع تكرار نفس الإشعار عند كل تشغيل يومي للفحص
        Schema::table('drivers', function (Blueprint $table) {
            $table->unsignedTinyInteger('license_expiry_notified_milestone')->nullable()->after('license_expiry')
                  ->comment('آخر نقطة تذكير أُرسلت (15/7/3/0 يوم) قبل انتهاء الرخصة — تُصفّر عند التجديد');
        });

        // نفس الفكرة، لكن على مستوى كل مستند على حدة (تُستخدم حالياً لوثيقة التأمين)
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->unsignedTinyInteger('expiry_notified_milestone')->nullable()->after('insurance_expiry_date')
                  ->comment('آخر نقطة تذكير أُرسلت (15/7/3/0 يوم) قبل انتهاء المستند — تُصفّر عند التجديد');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('license_expiry_notified_milestone');
        });

        Schema::table('driver_documents', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_milestone');
        });
    }
};
