<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تم التعطيل بالكامل لأن أوامر SET FOREIGN_KEY_CHECKS وتعديل القيود بهذه الطريقة 
        // غير مدعومة في SQLite وتسبب توقف البناء في بيئة التطوير
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // تم التعطيل
    }
};