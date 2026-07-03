<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('request_children', function (Blueprint $table) {
            // 1. إعادة تسمية الأعمدة القديمة لتطابق الـ Service
            $table->renameColumn('pickup_location_id', 'pickup_address_id');
            $table->renameColumn('dropoff_location_id', 'dropoff_address_id');
            $table->renameColumn('notes', 'child_notes');

            // 2. إضافة الأعمدة الجديدة المطلوبة للخدمة
            $table->decimal('home_lat', 10, 8)->nullable();
            $table->decimal('home_lng', 11, 8)->nullable();
            $table->string('home_label')->nullable();
            
            $table->decimal('school_lat', 10, 8)->nullable();
            $table->decimal('school_lng', 11, 8)->nullable();
            $table->string('school_label')->nullable();
            
            $table->decimal('price_per_child', 10, 2)->default(0);
        });
    }

    public function down()
    {
        Schema::table('request_children', function (Blueprint $table) {
            // التراجع عن التعديلات (في حال أردت العودة للحالة القديمة)
            $table->renameColumn('pickup_address_id', 'pickup_location_id');
            $table->renameColumn('dropoff_address_id', 'dropoff_location_id');
            $table->renameColumn('child_notes', 'notes');

            $table->dropColumn([
                'home_lat', 'home_lng', 'home_label',
                'school_lat', 'school_lng', 'school_label',
                'price_per_child'
            ]);
        });
    }
};