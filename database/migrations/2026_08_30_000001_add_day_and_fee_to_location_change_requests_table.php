<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_change_requests', function (Blueprint $table) {
            $table->date('change_date')->nullable()->after('point_type');
            $table->boolean('is_single_day')->default(true)->after('change_date');
            $table->decimal('fee_amount', 8, 2)->default(5.00)->after('new_label');
            $table->boolean('is_settled')->default(false)->after('responded_at');

            $table->index(['active_subscription_id', 'change_date', 'status'], 'lcr_sub_date_status_idx');
            $table->index(['child_id', 'change_date', 'status'], 'lcr_child_date_status_idx');
        });

        if (Schema::hasTable('pricing_settings')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('pricing_settings', 'location_change_fee')) {
                    $table->decimal('location_change_fee', 8, 2)->default(5.00)->after('platform_commission_rate');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('location_change_requests', function (Blueprint $table) {
            $table->dropIndex('lcr_sub_date_status_idx');
            $table->dropIndex('lcr_child_date_status_idx');
            $table->dropColumn(['change_date', 'is_single_day', 'fee_amount', 'is_settled']);
        });

        if (Schema::hasTable('pricing_settings') && Schema::hasColumn('pricing_settings', 'location_change_fee')) {
            Schema::table('pricing_settings', function (Blueprint $table) {
                $table->dropColumn('location_change_fee');
            });
        }
    }
};
