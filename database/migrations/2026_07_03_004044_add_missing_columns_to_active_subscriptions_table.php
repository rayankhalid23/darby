<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            // إضافة الأعمدة بأمان تام يتوافق مع SQLite
            if (!Schema::hasColumn('active_subscriptions', 'child_id')) {
                $table->foreignId('child_id')->nullable()->constrained('children')->onDelete('cascade');
            }
            if (!Schema::hasColumn('active_subscriptions', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('cascade');
            }
            if (!Schema::hasColumn('active_subscriptions', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('cascade');
            }
            if (!Schema::hasColumn('active_subscriptions', 'pickup_lat')) {
                $table->string('pickup_lat')->nullable();
            }
            if (!Schema::hasColumn('active_subscriptions', 'pickup_lng')) {
                $table->string('pickup_lng')->nullable();
            }
            if (!Schema::hasColumn('active_subscriptions', 'dropoff_lat')) {
                $table->string('dropoff_lat')->nullable();
            }
            if (!Schema::hasColumn('active_subscriptions', 'dropoff_lng')) {
                $table->string('dropoff_lng')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('active_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'child_id', 'driver_id', 'parent_id', 
                'pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng'
            ]);
        });
    }
};