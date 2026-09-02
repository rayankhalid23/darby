<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_alerts')) {
            Schema::create('admin_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_id')->nullable()->index();
                $table->string('alert_type', 50)->default('info');
                $table->string('title');
                $table->text('message');
                $table->unsignedTinyInteger('severity')->default(1);
                $table->string('action_required')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamps();

                $table->foreign('driver_id')
                      ->references('id')
                      ->on('drivers')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
    }
};
