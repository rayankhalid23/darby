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
        if (Schema::hasTable('trip_events') && !Schema::hasColumn('trip_events', 'reason')) {
            Schema::table('trip_events', function (Blueprint $table) {
                $table->string('reason')->nullable()->after('trip_cost');
            });
        }

        if (Schema::hasTable('trip_stops') && !Schema::hasColumn('trip_stops', 'reason')) {
            Schema::table('trip_stops', function (Blueprint $table) {
                $table->string('reason')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('trip_events') && Schema::hasColumn('trip_events', 'reason')) {
            Schema::table('trip_events', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }

        if (Schema::hasTable('trip_stops') && Schema::hasColumn('trip_stops', 'reason')) {
            Schema::table('trip_stops', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }
    }
};
