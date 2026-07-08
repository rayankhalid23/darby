<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasColumn('contracts', 'selected_clauses')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('selected_clauses');
            });
        }
    }
    
    public function down()
    {
        // يمكنك إعادة إضافته هنا في حال أردت التراجع عن الخطوة مستقبلاً
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('selected_clauses')->nullable();
        });
    }
};
