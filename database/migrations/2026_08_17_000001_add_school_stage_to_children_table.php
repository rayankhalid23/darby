<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            // grade يقبل الآن 0 (روضة) — لا تغيير في النوع، فقط الـ validation تغيّر
            // إضافة المرحلة الدراسية المشتقة تلقائياً من grade
            $table->string('school_stage')->nullable()->after('grade')
                  ->comment('kindergarten|primary|middle|secondary — مشتقة من grade');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('school_stage');
        });
    }
};
