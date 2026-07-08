<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('trips', function (Blueprint $table) {
        // إضافة العمود. إذا كان لديك سائقين بالفعل، 
        // تأكد من إضافة nullable() حتى لا يرفض النظام إضافة العمود بسبب البيانات القديمة
        $table->unsignedBigInteger('driver_id')->nullable()->after('id'); 
        
        // إذا كنت تريد ربطه بجدول الـ drivers كـ Foreign Key (اختياري)
        // $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('set null');
    });
}

public function down()
{
    Schema::table('trips', function (Blueprint $table) {
        $table->dropColumn('driver_id');
    });
}
};
