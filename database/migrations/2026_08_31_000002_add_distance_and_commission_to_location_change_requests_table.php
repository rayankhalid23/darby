<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('location_change_requests')) {
            return;
        }

        Schema::table('location_change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('location_change_requests', 'distance_km')) {
                $table->decimal('distance_km', 8, 2)->nullable()->after('new_label');
            }
            if (!Schema::hasColumn('location_change_requests', 'fee_tier')) {
                $table->string('fee_tier', 20)->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('location_change_requests', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->default(0)->after('fee_amount');
            }
            if (!Schema::hasColumn('location_change_requests', 'platform_commission_amount')) {
                $table->decimal('platform_commission_amount', 8, 2)->default(0)->after('commission_rate');
            }
            if (!Schema::hasColumn('location_change_requests', 'driver_net_fee')) {
                $table->decimal('driver_net_fee', 8, 2)->default(0)->after('platform_commission_amount');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('location_change_requests')) {
            return;
        }

        Schema::table('location_change_requests', function (Blueprint $table) {
            foreach (['distance_km', 'fee_tier', 'commission_rate', 'platform_commission_amount', 'driver_net_fee'] as $column) {
                if (Schema::hasColumn('location_change_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
