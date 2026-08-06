<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();
            $table->enum('stop_type', ['home', 'school']);
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('label', 255)->nullable();
            $table->unsignedInteger('sequence_order')->default(0);
            $table->enum('status', ['pending', 'absent_pre', 'picked_up', 'skipped', 'dropped_off', 'completed'])
                  ->default('pending');
            $table->unsignedInteger('eta_minutes')->nullable();
            $table->time('eta')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'sequence_order'], 'idx_trip_stops_trip_seq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
