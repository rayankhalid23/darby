<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تعديل عمود status ليقبل أي نص (VARCHAR) بدلاً من ENUM المقيد
        DB::statement("ALTER TABLE contracts MODIFY status VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        // في حال أردت التراجع عن التعديل (إعادة الـ ENUM)
        DB::statement("ALTER TABLE contracts MODIFY status ENUM('pending_parent_approval','activated','rejected') NOT NULL");
    }
};