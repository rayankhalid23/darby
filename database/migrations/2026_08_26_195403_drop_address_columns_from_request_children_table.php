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
        Schema::disableForeignKeyConstraints();
        try {
            DB::statement("ALTER TABLE `request_children` DROP FOREIGN KEY `request_children_dropoff_location_id_foreign`");
        } catch (\Throwable $e) {}
        try {
            DB::statement("ALTER TABLE `request_children` DROP FOREIGN KEY `request_children_pickup_location_id_foreign`");
        } catch (\Throwable $e) {}
        try {
            DB::statement("ALTER TABLE `request_children` DROP FOREIGN KEY `request_children_dropoff_address_id_foreign`");
        } catch (\Throwable $e) {}
        try {
            DB::statement("ALTER TABLE `request_children` DROP FOREIGN KEY `request_children_pickup_address_id_foreign`");
        } catch (\Throwable $e) {}

        Schema::table('request_children', function (Blueprint $table) {
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

            if (!Schema::hasColumn('request_children', 'trip_price')) {
                $table->decimal('trip_price', 10, 2)->default(0.00);
            }
        });

        Schema::enableForeignKeyConstraints();
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