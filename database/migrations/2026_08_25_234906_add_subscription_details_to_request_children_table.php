<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            $table->string('subscription_type')->nullable()->after('price_per_child');
            $table->string('trip_direction')->nullable()->after('subscription_type'); // two_way, go, return
            $table->date('start_date')->nullable()->after('trip_direction');
            $table->date('end_date')->nullable()->after('start_date');
            $table->integer('working_days_count')->default(1)->after('end_date');
            $table->decimal('distance_km', 8, 2)->nullable()->after('working_days_count');
        });
    }

    public function down(): void
    {
        Schema::table('request_children', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_type', 
                'trip_direction', 
                'start_date', 
                'end_date', 
                'working_days_count',
                'distance_km'
            ]);
        });
    }
};