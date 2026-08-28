<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('discount_one_child', 5, 2)->default(0.00);          // نسبة خصم طفل واحد (0%)
            $table->decimal('discount_two_children', 5, 2)->default(10.00);      // نسبة خصم طفلين (10%)
            $table->decimal('discount_three_plus_children', 5, 2)->default(15.00); // نسبة خصم 3 أطفال أو أكثر (15%)
            $table->decimal('platform_commission_rate', 5, 2)->default(8.00);    // نسبة عمولة المنصة (8%)
            $table->timestamps();
        });

        // إضافة الصف الافتراضي فور إنشاء الجدول لضمان وجود البيانات
        DB::table('pricing_settings')->insert([
            'discount_one_child'          => 0.00,
            'discount_two_children'      => 10.00,
            'discount_three_plus_children' => 15.00,
            'platform_commission_rate'    => 8.00,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};