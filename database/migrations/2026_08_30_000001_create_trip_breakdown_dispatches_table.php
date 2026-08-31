<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_breakdown_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('original_driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('substitute_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('substitute_trip_id')->nullable()->constrained('trips')->nullOnDelete();
            
            $table->enum('status', [
                'pending',
                'broadcasted',
                'accepted',
                'declined_all',
                'expired',
                'unresolved',
                'completed',
                'cancelled'
            ])->default('pending');

            $table->decimal('breakdown_lat', 10, 7)->nullable();
            $table->decimal('breakdown_lng', 10, 7)->nullable();
            $table->string('reason')->nullable();

            $table->json('stranded_children_ids')->nullable();
            $table->unsignedSmallInteger('stranded_children_count')->default(0);
            $table->json('candidate_driver_ids')->nullable();
            $table->json('rejected_driver_ids')->nullable();

            $table->decimal('trip_fare_amount', 10, 2)->default(0.00);
            $table->boolean('financial_settled')->default(false);
            $table->timestamp('settled_at')->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_breakdown_dispatches');
    }
};
