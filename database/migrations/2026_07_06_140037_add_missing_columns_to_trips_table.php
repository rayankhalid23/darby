<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            // الأعمدة السابقة (التي أضفناها)
            if (!Schema::hasColumn('trips', 'trip_type')) {
                $table->string('trip_type')->nullable();
            }
            if (!Schema::hasColumn('trips', 'status')) {
                $table->string('status')->default('pending');
            }
            if (!Schema::hasColumn('trips', 'scheduled_start_time')) {
                $table->timestamp('scheduled_start_time')->nullable();
            }
            if (!Schema::hasColumn('trips', 'actual_start_time')) {
                $table->timestamp('actual_start_time')->nullable();
            }

            // إضافة أعمدة التوقيت الخاصة بلارافل
            if (!Schema::hasColumn('trips', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('trips', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['trip_type', 'status', 'scheduled_start_time', 'actual_start_time']);
        });
    }
};