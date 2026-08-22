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
        if (Schema::hasColumn('addresses', 'is_default')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }

        if (Schema::hasTable('driver_addresses') && Schema::hasColumn('driver_addresses', 'is_default')) {
            Schema::table('driver_addresses', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('addresses', 'is_default')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('lng');
            });
        }

        if (Schema::hasTable('driver_addresses') && !Schema::hasColumn('driver_addresses', 'is_default')) {
            Schema::table('driver_addresses', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('lng');
            });
        }
    }
};
