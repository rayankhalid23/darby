<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');
            } catch (\Throwable $e) {
                // Already dropped or different name
            }

            try {
                Schema::table('trip_events', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                          ->references('id')
                          ->on('active_subscriptions')
                          ->nullOnDelete()
                          ->cascadeOnUpdate();
                });
            } catch (\Throwable $e) {
                //
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE `trip_events` DROP FOREIGN KEY `trip_events_subscription_id_foreign`');
            } catch (\Throwable $e) {
                //
            }
        }
    }
};
