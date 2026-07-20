<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. حذف الجدول القديم غير المتوافق (إذا كنت لا تريد حذفه وتود الاحتفاظ به كأرشيف، يمكنك استخدام Schema::rename بدلاً من dropIfExists)
        Schema::dropIfExists('notifications');

        // 2. إنشاء الجدول الجديد بالشكل القياسي الذي يعتمده لارافيل
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary(); // لارافيل يستخدم UUID كمعرف أساسي للإشعارات
            $table->string('type'); // اسم كلاس الإشعار
            $table->morphs('notifiable'); // هذا ينشئ عمودين: notifiable_id و notifiable_type
            $table->text('data'); // لحفظ تفاصيل الإشعار بصيغة JSON
            $table->timestamp('read_at')->nullable(); // وقت قراءة الإشعار
            $table->timestamps(); // ينشئ created_at و updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};