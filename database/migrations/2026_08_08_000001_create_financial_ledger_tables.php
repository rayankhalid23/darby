<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول السجل المالي غير القابل للمسح (Immutable Financial Ledger)
        Schema::create('financial_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id')->unique();
            $table->string('reference_number')->nullable()->index();
            $table->string('source_account')->index();
            $table->string('destination_account')->index();
            $table->bigInteger('amount'); // بالقروش / الفرشات
            $table->bigInteger('balance_before')->default(0);
            $table->bigInteger('balance_after')->default(0);
            $table->string('type')->index(); // recharge, trip_hold, trip_capture, driver_payout, withdrawal_cash, penalty, compensation, platform_commission, etc.
            $table->string('status')->default('completed')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 2. جدول خزينة النظام المركزية (Master Escrow Vault)
        Schema::create('master_escrow_vault', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('parents_escrow_pool')->default(0);
            $table->bigInteger('driver_pending_pool')->default(0);
            $table->bigInteger('driver_available_pool')->default(0);
            $table->bigInteger('platform_revenue_pool')->default(0);
            $table->bigInteger('penalty_pool')->default(0);
            $table->timestamps();
        });

        // 3. جدول الحجز والتسوية للرحلات (Trip Escrow Holds)
        Schema::create('trip_escrow_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->index();
            $table->unsignedBigInteger('driver_id')->index();
            $table->bigInteger('amount'); // بالقروش
            $table->string('hold_status')->default('held')->index(); // held, captured_pending, released_available, disputed, refunded, partially_refunded
            $table->timestamp('held_at')->useCurrent();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamps();
        });

        // 4. جدول النزاعات والشبهات المالية (Trip Disputes)
        Schema::create('trip_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->index();
            $table->unsignedBigInteger('driver_id')->index();
            $table->text('reason');
            $table->string('status')->default('open')->index(); // open, resolved_parent_refunded, resolved_driver_paid, rejected
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_disputes');
        Schema::dropIfExists('trip_escrow_holds');
        Schema::dropIfExists('master_escrow_vault');
        Schema::dropIfExists('financial_ledger');
    }
};
