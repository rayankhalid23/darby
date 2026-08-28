<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول طرق الدفع الديناميكية
        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->string('name_ar');
                $table->string('name_en')->nullable();
                $table->string('code', 50)->unique();
                $table->enum('target_audience', ['parent', 'driver', 'both'])->default('both');
                $table->enum('processing_type', ['instant_simulation', 'manual_proof'])->default('instant_simulation');
                $table->string('account_name')->nullable();
                $table->string('account_number', 100)->nullable();
                $table->string('iban', 100)->nullable();
                $table->string('wallet_number', 100)->nullable();
                $table->string('icon_url')->nullable();
                $table->decimal('min_amount', 10, 2)->default(1.00);
                $table->decimal('max_amount', 10, 2)->default(50000.00);
                $table->text('instructions_ar')->nullable();
                $table->text('instructions_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['target_audience', 'is_active']);
                $table->index('code');
            });
        }

        // 2. جدول طلبات شحن السائقين المحكومة بالإيصالات
        if (!Schema::hasTable('driver_recharge_requests')) {
            Schema::create('driver_recharge_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
                $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('proof_image_url')->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->string('status', 20)->default('pending'); // pending, approved, rejected
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();

                $table->index(['driver_id', 'status']);
                $table->index('status');
            });
        }

        // 3. تحديث جدول recharge_requests ليدعم الربط بطرق الدفع والمحاكاة الفورية
        if (Schema::hasTable('recharge_requests')) {
            Schema::table('recharge_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('recharge_requests', 'payment_method_id')) {
                    $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
                }
                if (!Schema::hasColumn('recharge_requests', 'transaction_ref')) {
                    $table->string('transaction_ref', 100)->nullable()->after('reference_number');
                }
                if (!Schema::hasColumn('recharge_requests', 'session_token')) {
                    $table->string('session_token', 120)->nullable()->after('transaction_ref');
                }
                if (!Schema::hasColumn('recharge_requests', 'gateway_payload')) {
                    $table->json('gateway_payload')->nullable()->after('session_token');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recharge_requests')) {
            Schema::table('recharge_requests', function (Blueprint $table) {
                if (Schema::hasColumn('recharge_requests', 'payment_method_id')) {
                    $table->dropForeign(['payment_method_id']);
                    $table->dropColumn(['payment_method_id', 'transaction_ref', 'session_token', 'gateway_payload']);
                }
            });
        }

        Schema::dropIfExists('driver_recharge_requests');
        Schema::dropIfExists('payment_methods');
    }
};
