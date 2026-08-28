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
        Schema::table('request_children', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('price_per_child');
            $table->decimal('total_amount_after_discount', 10, 2)->default(0.00)->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'total_amount_after_discount']);
        });
    }
};