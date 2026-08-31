<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pricing_settings')) {
            return;
        }

        Schema::table('pricing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_settings', 'location_change_fee')) {
                $table->decimal('location_change_fee', 8, 2)->default(5.00)->after('platform_commission_rate');
            }
        });

        Schema::table('pricing_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_settings', 'location_change_fee_under_2km')) {
                $table->decimal('location_change_fee_under_2km', 8, 2)->default(5.00)->after('location_change_fee');
            }
            if (!Schema::hasColumn('pricing_settings', 'location_change_fee_2_to_6km')) {
                $table->decimal('location_change_fee_2_to_6km', 8, 2)->default(10.00)->after('location_change_fee_under_2km');
            }
            if (!Schema::hasColumn('pricing_settings', 'location_change_fee_6_to_10km')) {
                $table->decimal('location_change_fee_6_to_10km', 8, 2)->default(15.00)->after('location_change_fee_2_to_6km');
            }
        });

        // تعبئة السجل القائم بالقيم الافتراضية فقط إن كانت فارغة، دون المساس بأي قيمة مضبوطة مسبقاً.
        DB::table('pricing_settings')->whereNull('location_change_fee_under_2km')->update(['location_change_fee_under_2km' => 5.00]);
        DB::table('pricing_settings')->whereNull('location_change_fee_2_to_6km')->update(['location_change_fee_2_to_6km' => 10.00]);
        DB::table('pricing_settings')->whereNull('location_change_fee_6_to_10km')->update(['location_change_fee_6_to_10km' => 15.00]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('pricing_settings')) {
            return;
        }

        Schema::table('pricing_settings', function (Blueprint $table) {
            foreach (['location_change_fee_under_2km', 'location_change_fee_2_to_6km', 'location_change_fee_6_to_10km'] as $column) {
                if (Schema::hasColumn('pricing_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
