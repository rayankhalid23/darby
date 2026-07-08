<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'pickup_time')) {
                $table->time('pickup_time')->nullable()->after('children_count');
            }
            if (!Schema::hasColumn('requests', 'dropoff_time')) {
                $table->time('dropoff_time')->nullable()->after('pickup_time');
            }
            if (!Schema::hasColumn('requests', 'max_waiting_time')) {
                $table->integer('max_waiting_time')->default(15)->after('dropoff_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['pickup_time', 'dropoff_time', 'max_waiting_time']);
        });
    }
};
