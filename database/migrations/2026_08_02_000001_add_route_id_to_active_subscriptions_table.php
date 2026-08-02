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
        Schema::table('active_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('active_subscriptions', 'route_id')) {
                $table->foreignId('route_id')->nullable()->after('driver_id')->constrained('routes')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('active_subscriptions', 'route_id')) {
                $table->dropForeign(['route_id']);
                $table->dropColumn('route_id');
            }
        });
    }
};
