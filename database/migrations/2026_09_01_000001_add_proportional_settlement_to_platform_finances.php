<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحويل تسوية الأمانات من "صرف كامل مبلغ الاشتراك على أول رحلة مكتملة"
 * إلى "تسوية تناسبية لكل رحلة مُنفّذة فعلياً".
 *
 * - expected_trips_count : عدد الرحلات التي يغطيها الاشتراك (أيام العمل × اتجاهات اليوم)
 * - settled_trips_count  : كم رحلة صُرفت حصتها حتى الآن
 * - settled_amount       : إجمالي ما صُرف فعلياً (لضمان عدم تجاوز مبلغ الاشتراك)
 *
 * وجدول platform_finance_trip_settlements يمنع صرف حصة نفس الرحلة مرتين
 * عبر قيد فريد على (platform_finance_id, trip_id) — حماية على مستوى قاعدة البيانات
 * لا يمكن تجاوزها بأي تسابق أو إعادة إرسال من التطبيق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_finances', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_finances', 'expected_trips_count')) {
                $table->unsignedInteger('expected_trips_count')->default(1)->after('driver_net_amount');
            }
            if (!Schema::hasColumn('platform_finances', 'settled_trips_count')) {
                $table->unsignedInteger('settled_trips_count')->default(0)->after('expected_trips_count');
            }
            if (!Schema::hasColumn('platform_finances', 'settled_amount')) {
                $table->decimal('settled_amount', 10, 2)->default(0)->after('settled_trips_count');
            }
        });

        if (!Schema::hasTable('platform_finance_trip_settlements')) {
            Schema::create('platform_finance_trip_settlements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('platform_finance_id');
                $table->unsignedBigInteger('trip_id');
                $table->decimal('gross_amount', 10, 2)->default(0);
                $table->decimal('commission_amount', 10, 2)->default(0);
                $table->decimal('driver_net_amount', 10, 2)->default(0);
                $table->timestamps();

                $table->unique(['platform_finance_id', 'trip_id'], 'pf_trip_settlement_unique');
                $table->index('trip_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_finance_trip_settlements');

        Schema::table('platform_finances', function (Blueprint $table) {
            foreach (['expected_trips_count', 'settled_trips_count', 'settled_amount'] as $col) {
                if (Schema::hasColumn('platform_finances', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
