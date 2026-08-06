<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->enum('stop_type', ['home', 'school']);
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('label', 255)->nullable();
            $table->unsignedInteger('sequence_order')->default(0);
            $table->timestamps();

            $table->index(['route_id', 'sequence_order'], 'idx_route_stops_route_seq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
    }
};
