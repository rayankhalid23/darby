<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            // التحقق من عدم وجود العمود مسبقاً لتفادي الأخطاء، وإضافته بأمان
            if (!Schema::hasColumn('request_children', 'price_per_child')) {
                $table->decimal('price_per_child', 10, 2)->default(0.00)->after('school_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            if (Schema::hasColumn('request_children', 'price_per_child')) {
                $table->dropColumn('price_per_child');
            }
        });
    }
};