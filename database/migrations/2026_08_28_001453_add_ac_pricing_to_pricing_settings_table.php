<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_settings', function (Blueprint $table) {
            // سعر الكيلومتر للسيارة المكيفة
            $table->decimal('price_per_km_ac', 8, 2)->default(2.50)->after('platform_commission_rate');
            // سعر الكيلومتر للسيارة غير المكيفة
            $table->decimal('price_per_km_non_ac', 8, 2)->default(2.00)->after('price_per_km_ac');
        });

        // تحديث السجل الحالي بالقيم الافتراضية
        DB::table('pricing_settings')->update([
            'price_per_km_ac'     => 2.50,
            'price_per_km_non_ac' => 2.00,
            'updated_at'          => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->dropColumn(['price_per_km_ac', 'price_per_km_non_ac']);
        });
    }
};