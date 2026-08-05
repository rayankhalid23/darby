<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('morning_go')->default(false)->after('shift')->comment('يعمل في رحلة الذهاب الصباحية');
            $table->boolean('morning_return')->default(false)->after('morning_go')->comment('يعمل في رحلة الإياب الصباحية');
            $table->boolean('afternoon_go')->default(false)->after('morning_return')->comment('يعمل في رحلة الذهاب المسائية');
            $table->boolean('afternoon_return')->default(false)->after('afternoon_go')->comment('يعمل في رحلة الإياب المسائية');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->index(
                ['status', 'morning_go', 'morning_return', 'afternoon_go', 'afternoon_return'],
                'idx_driver_shifts'
            );
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('idx_driver_shifts');
            $table->dropColumn(['morning_go', 'morning_return', 'afternoon_go', 'afternoon_return']);
        });
    }
};
