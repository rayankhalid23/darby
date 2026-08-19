<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل المايجريشن لإنشاء جدول سجل تدقيق إجراءات المشرفين والمدراء
     */
    public function up(): void
    {
        if (!Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id');
                $table->string('admin_name', 191);
                $table->string('admin_role', 100)->nullable();
                $table->string('action', 100);
                $table->string('entity_type', 100);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('entity_name', 191)->nullable();
                $table->string('result', 50)->nullable();
                $table->text('reason')->nullable();
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                // الفهارس المخصصة للبحث السريع والفلترة
                $table->index(['admin_id', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
                $table->index('action');
                $table->index('created_at');
            });
        }
    }

    /**
     * إلغاء المايجريشن
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
