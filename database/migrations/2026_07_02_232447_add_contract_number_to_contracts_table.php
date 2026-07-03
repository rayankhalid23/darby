<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('contracts', function (Blueprint $table) {
        // إضافة العمود. نستخدم unique() إذا كنت تريد رقم عقد فريد لكل عقد
        $table->string('contract_number')->after('id'); 
    });
}

public function down(): void
{
    Schema::table('contracts', function (Blueprint $table) {
        $table->dropColumn('contract_number');
    });
}
};
