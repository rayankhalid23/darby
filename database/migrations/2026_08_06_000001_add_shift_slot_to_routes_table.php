<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->enum('shift_slot', ['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'])
                  ->nullable()
                  ->after('route_type')
                  ->comment('الفترة/الاتجاه الدقيق للمسار الرئيسي (Master Route)');
            $table->index(['driver_id', 'shift_slot', 'status'], 'idx_routes_driver_shift_status');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex('idx_routes_driver_shift_status');
            $table->dropColumn('shift_slot');
        });
    }
};
