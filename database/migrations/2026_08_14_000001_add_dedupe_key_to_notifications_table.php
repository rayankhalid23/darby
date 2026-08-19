<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يضيف عمود dedupe_key حقيقياً مفهرساً UNIQUE، بدل الاعتماد فقط على idempotency_key
 * المدفون داخل عمود data (JSON) الذي لا يوفر أي منع تكرار فعلي على مستوى قاعدة
 * البيانات (لا قيد UNIQUE يمكن وضعه مباشرة على مسار JSON بهذا الشكل، والتحقق منه في
 * التطبيق قبل الإدراج عرضة لـ race condition بين الفحص والإدراج تحت طلبات متزامنة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // md5() hash (32 hex chars) — VARCHAR(64) للسماح بهامش أمان بسيط.
            $table->string('dedupe_key', 64)->nullable()->after('data');
            $table->unique('dedupe_key', 'notifications_dedupe_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_dedupe_key_unique');
            $table->dropColumn('dedupe_key');
        });
    }
};
