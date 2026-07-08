<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'trip_date')) {
                $table->date('trip_date')->nullable()->after('driver_id');
            }
        });

        try {
            Schema::table('trips', function (Blueprint $table) {
                $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // قد يكون موجوداً بالفعل
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            try {
                $table->dropForeign(['driver_id']);
            } catch (\Exception $e) {
                //
            }
            if (Schema::hasColumn('trips', 'trip_date')) {
                $table->dropColumn('trip_date');
            }
        });
    }
};
