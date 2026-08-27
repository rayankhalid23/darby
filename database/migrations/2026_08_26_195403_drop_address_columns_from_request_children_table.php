<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تعطيل فحص المفاتيح الأجنبية مؤقتاً في سياق قاعدة البيانات لتنفيذ الحذف بسلاسة مطلقة
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('request_children', function (Blueprint $table) {
            // حذف الأعمدة مباشرة دون الحاجة للبحث عن أسماء القيود المعقدة
            $columnsToDrop = [];
            
            if (Schema::hasColumn('request_children', 'pickup_address_id')) {
                $columnsToDrop[] = 'pickup_address_id';
            }
            if (Schema::hasColumn('request_children', 'dropoff_address_id')) {
                $columnsToDrop[] = 'dropoff_address_id';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            // إضافة حقل سعر الرحلة الواحدة إذا لم يكن موجوداً
            if (!Schema::hasColumn('request_children', 'trip_price')) {
                $table->decimal('trip_price', 10, 2)->default(0.00);
            }
        });

        // إعادة تفعيل فحص المفاتيح الأجنبية
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            if (Schema::hasColumn('request_children', 'trip_price')) {
                $table->dropColumn('trip_price');
            }
            if (!Schema::hasColumn('request_children', 'pickup_address_id')) {
                $table->unsignedBigInteger('pickup_address_id')->nullable();
            }
            if (!Schema::hasColumn('request_children', 'dropoff_address_id')) {
                $table->unsignedBigInteger('dropoff_address_id')->nullable();
            }
        });
    }
};