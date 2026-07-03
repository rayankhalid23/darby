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
    Schema::table('request_children', function ($table) {
        if (!Schema::hasColumn('request_children', 'created_at')) {
            $table->timestamps();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            //
        });
    }
};
