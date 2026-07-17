<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('driver_attendance')->nullable()->after('actual_start_time')->comment('driver attended the trip');
        });

        Schema::create('trip_student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->enum('attendance_status', ['present', 'absent', 'late'])->default('present');
            $table->timestamps();

            $table->unique(['trip_id', 'child_id']);
            $table->index('attendance_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_student_attendance');
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('driver_attendance');
        });
    }
};
