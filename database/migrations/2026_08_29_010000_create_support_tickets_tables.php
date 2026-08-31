<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('creator_role', 10); // parent | driver

            $table->string('category', 20); // general | financial | party

            // ربط عام اختياري بالفاتورة/المعاملة المالية/الرحلة المعنية (morphTo)
            $table->nullableMorphs('referenceable');

            // الطرف المشتكى عليه في فئة "party" (سائق أو ولي أمر)
            $table->string('target_role', 10)->nullable();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('description');
            $table->json('attachments')->nullable();

            $table->string('status', 15)->default('open'); // open | closed
            $table->string('scope', 15)->default('operations'); // operations | financial

            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('transfer_note')->nullable();
            $table->string('penalty_action', 50)->nullable();
            $table->text('resolution_note')->nullable();

            $table->foreignId('closed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['scope', 'status']);
            $table->index(['creator_role', 'user_id']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->text('message');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
