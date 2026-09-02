<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_alerts', 'risk_level')) {
                $table->string('risk_level', 20)->default('NONE')->after('driver_id')->index();
            }
            if (!Schema::hasColumn('admin_alerts', 'actions_taken')) {
                $table->json('actions_taken')->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('admin_alerts', 'admin_message')) {
                $table->text('admin_message')->nullable()->after('actions_taken');
            }
            if (!Schema::hasColumn('admin_alerts', 'reasoning')) {
                $table->text('reasoning')->nullable()->after('admin_message');
            }
            if (!Schema::hasColumn('admin_alerts', 'ai_metrics')) {
                $table->json('ai_metrics')->nullable()->after('reasoning');
            }
            if (!Schema::hasColumn('admin_alerts', 'evaluated_reviews')) {
                $table->json('evaluated_reviews')->nullable()->after('ai_metrics');
            }
            if (!Schema::hasColumn('admin_alerts', 'is_resolved')) {
                $table->boolean('is_resolved')->default(false)->after('evaluated_reviews')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_alerts', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'risk_level',
                'actions_taken',
                'admin_message',
                'reasoning',
                'ai_metrics',
                'evaluated_reviews',
                'is_resolved',
            ], fn($col) => Schema::hasColumn('admin_alerts', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
