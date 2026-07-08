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
            if (!Schema::hasColumn('active_subscriptions', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('status');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'driver_waiting_minutes')) {
                $table->integer('driver_waiting_minutes')->default(5)->after('license_expiry'); // تأكد أن license_expiry موجود أو غيره لـ id
            }
        });

        Schema::table('trip_events', function (Blueprint $table) {
            $table->string('action_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('driver_waiting_minutes');
        });

        Schema::table('trip_events', function (Blueprint $table) {
            // أرجعها للافتراضي القديم لو احتجت تدير rollback
            $table->enum('action_type', ['picked_up', 'dropped_off', 'absent'])->change();
        });
    }
};