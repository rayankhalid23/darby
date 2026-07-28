<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('absence_logs', 'absence_type')) {
            Schema::table('absence_logs', function (Blueprint $table) {
                $table->enum('absence_type', ['pickup', 'dropoff', 'both'])
                      ->default('both')
                      ->after('absence_date')
                      ->comment('نوع الغياب: pickup=ذهاب فقط، dropoff=عودة فقط، both=ذهاب وعودة');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('absence_logs', 'absence_type')) {
            Schema::table('absence_logs', function (Blueprint $table) {
                $table->dropColumn('absence_type');
            });
        }
    }
};