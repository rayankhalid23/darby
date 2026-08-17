<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // نتيجة التصنيف الآلي: suspend_driver, notify_driver, log_only, no_action
            $table->string('ai_action', 30)->nullable()->after('action_details');
            $table->decimal('ai_confidence', 5, 4)->nullable()->after('ai_action');
            $table->unsignedTinyInteger('ai_severity')->nullable()->after('ai_confidence');
            $table->text('ai_analysis_message')->nullable()->after('ai_severity');

            $table->index('ai_action');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['ai_action']);
            $table->dropColumn(['ai_action', 'ai_confidence', 'ai_severity', 'ai_analysis_message']);
        });
    }
};
