<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('active_subscriptions', 'pickup_label')) {
                $table->string('pickup_label')->nullable()->after('pickup_lng');
            }
            if (!Schema::hasColumn('active_subscriptions', 'dropoff_label')) {
                $table->string('dropoff_label')->nullable()->after('dropoff_lng');
            }
            if (!Schema::hasColumn('active_subscriptions', 'pickup_time')) {
                $table->time('pickup_time')->nullable()->after('dropoff_label');
            }
            if (!Schema::hasColumn('active_subscriptions', 'dropoff_time')) {
                $table->time('dropoff_time')->nullable()->after('pickup_time');
            }
        });

        DB::statement("ALTER TABLE active_subscriptions MODIFY pickup_lat DECIMAL(10, 8) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY pickup_lng DECIMAL(11, 8) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY dropoff_lat DECIMAL(10, 8) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY dropoff_lng DECIMAL(11, 8) NULL");
    }

    public function down(): void
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['pickup_label', 'dropoff_label', 'pickup_time', 'dropoff_time']);
        });

        DB::statement("ALTER TABLE active_subscriptions MODIFY pickup_lat VARCHAR(255) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY pickup_lng VARCHAR(255) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY dropoff_lat VARCHAR(255) NULL");
        DB::statement("ALTER TABLE active_subscriptions MODIFY dropoff_lng VARCHAR(255) NULL");
    }
};
