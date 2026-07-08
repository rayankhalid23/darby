<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_logs', function (Blueprint $table) {
            $table->id();
            // ربط مع جدول الأطفال (تأكد من اسم جدول الأطفال عندك، إذا كان children)
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->date('absence_date'); // التاريخ المحدد للغياب
            $table->timestamps();

            // منع تكرار نفس اليوم لنفس الطفل
            $table->unique(['child_id', 'absence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_logs');
    }
};