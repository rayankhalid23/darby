<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلب تغيير موقع الاستلام (pickup) أو التسليم (dropoff) لطفل ضمن اشتراك نشط،
 * يُنشئه ولي الأمر وينتظر موافقة/رفض السائق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_subscription_id')->constrained('active_subscriptions')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->enum('point_type', ['pickup', 'dropoff']);
            $table->foreignId('new_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->decimal('new_lat', 10, 8);
            $table->decimal('new_lng', 11, 8);
            $table->string('new_label', 255)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_change_requests');
    }
};
