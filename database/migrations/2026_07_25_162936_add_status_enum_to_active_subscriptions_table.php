<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            // إذا كان العمود غير موجود، نقوم بإنشائه بالقيم المدعومة
            if (!Schema::hasColumn('active_subscriptions', 'status')) {
                $table->enum('status', ['active', 'pending', 'completed', 'cancelled'])
                      ->default('active')
                      ->after('id');
            } else {
                // إذا كان العمود موجوداً، نقوم بتعديله ليقبل القيم المحددة (نستخدم change للإصدارات التي تدعم DBAL أو نحافظ عليه كـ string مع تحديد القيم برمجياً)
                // بما أننا نريد تجنب أي تعقيد، سنضمن تعديله كـ enum أو string مدعوم:
                $table->enum('status', ['active', 'pending', 'completed', 'cancelled'])
                      ->default('active')
                      ->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            // العودة إلى الحالة السابقة عند التراجع إن أمكن
            $table->string('status')->default('active')->change();
        });
    }
};