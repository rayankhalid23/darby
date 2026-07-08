<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_absences', function (Blueprint $table) {
            $table->id();
            // ربط مع جدول السائقين (تأكد من اسم جدول السائقين عندك، إذا كان drivers)
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->date('absence_date'); // التاريخ المحدد لغياب السائق
            $table->timestamps();

            // منع تكرار نفس اليوم لنفس السائق
            $table->unique(['driver_id', 'absence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_absences');
    }
};