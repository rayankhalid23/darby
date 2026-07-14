<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('against_id')->constrained('drivers')->nullOnDelete();
            $table->string('action_taken', 50)->default('none')->after('resolution_note');
            $table->text('action_details')->nullable()->after('action_taken');
        });

        DB::statement("ALTER TABLE complaints MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE complaints MODIFY COLUMN action_taken VARCHAR(50) NOT NULL DEFAULT 'none'");

        Schema::table('complaints', function (Blueprint $table) {
            $table->index('driver_id');
            $table->index('action_taken');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['action_taken']);
            $table->dropColumn(['driver_id', 'action_taken', 'action_details']);
        });

        DB::statement("ALTER TABLE complaints MODIFY COLUMN status ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open'");
    }
};
