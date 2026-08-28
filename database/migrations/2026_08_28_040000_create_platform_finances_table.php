<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('active_subscription_id')->nullable()->constrained('active_subscriptions')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->unsignedBigInteger('parent_id')->index();
            $table->unsignedBigInteger('driver_id')->index();
            $table->decimal('total_amount', 10, 2); // المبلغ الإجمالي بالدينار
            $table->decimal('platform_commission_rate', 5, 2)->default(8.00); // نسبة العمولة %
            $table->decimal('platform_commission_amount', 10, 2)->default(0.00); // قيمة عمولة المنصة بالدينار
            $table->decimal('driver_net_amount', 10, 2)->default(0.00); // صافي مستحقات السائق بالدينار
            $table->decimal('refunded_amount', 10, 2)->default(0.00); // المبلغ المسترجع لولي الأمر بالدينار
            $table->decimal('compensation_fee', 10, 2)->default(0.00); // رسم تعويض المشوار والوقود عند الإلغاء بعد التحرك
            $table->string('status')->default('held')->index(); // held, completed, refunded, partially_refunded, disputed
            $table->timestamp('held_at')->useCurrent();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_finances');
    }
};
