<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('invoice_number', 50)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('type', 20)->default('proforma'); // proforma, final
            $table->string('status', 20)->default('pending'); // pending, paid, overdue
            $table->date('due_date');
            $table->string('subscription_type', 20)->nullable(); // snapshot
            $table->decimal('total_trips', 5, 0)->default(0);
            $table->decimal('completed_trips', 5, 0)->default(0);
            $table->decimal('driver_absences', 5, 0)->default(0);
            $table->decimal('student_absences', 5, 0)->default(0);
            $table->decimal('calculated_amount', 10, 2)->nullable();
            $table->string('action_taken', 50)->default('none');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('parent_id');
            $table->index('driver_id');
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
