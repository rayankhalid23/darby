<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // المراحل الدراسية التي يخدمها السائق — مصفوفة JSON
            $table->json('school_stages')->nullable()->after('subscription_type')
                  ->comment('["primary","middle","secondary"] — المراحل التي يقبلها السائق');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('school_stages');
        });
    }
};
