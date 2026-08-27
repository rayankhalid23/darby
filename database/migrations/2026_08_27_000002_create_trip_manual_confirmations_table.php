<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلب تأكيد يدوي من ولي الأمر لحالة استلام/تسليم طفل في رحلة سابقة لم يوثقها السائق
 * (تعطل تطبيق، نسيان مسح QR... إلخ). لا يغيّر حالة trip_stops إلا بعد موافقة ولي الأمر صراحة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_manual_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('trip_stop_id')->constrained('trip_stops')->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->enum('question_type', ['pickup', 'dropoff']);
            $table->string('target_status', 50);
            $table->enum('status', ['pending', 'confirmed', 'denied'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_stop_id', 'status'], 'uniq_pending_confirmation_per_stop');
            $table->index(['driver_id', 'status']);
            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_manual_confirmations');
    }
};
