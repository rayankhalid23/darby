<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('shift_slot', ['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'])
                  ->nullable()
                  ->after('trip_type')
                  ->comment('الفترة/الاتجاه الدقيق للرحلة اليومية');
            $table->decimal('start_lat', 10, 8)->nullable()->comment('موقع السائق الحي عند الضغط على بدء الرحلة');
            $table->decimal('start_lng', 11, 8)->nullable();
            $table->index(['driver_id', 'shift_slot', 'trip_date'], 'idx_trips_driver_shift_date');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('idx_trips_driver_shift_date');
            $table->dropColumn(['shift_slot', 'start_lat', 'start_lng']);
        });
    }
};
