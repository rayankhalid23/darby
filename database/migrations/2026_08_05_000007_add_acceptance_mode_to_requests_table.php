<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->enum('children_acceptance_mode', ['all', 'individual'])
                  ->default('all')
                  ->after('children_count')
                  ->comment('all=يجب قبولهم معاً / individual=كل طفل يُقبل منفرداً');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('children_acceptance_mode');
        });
    }
};
