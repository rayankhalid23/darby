<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مواءمة مخطط قاعدة البيانات مع المنطق المالي الموحّد:
 *
 * 1. أعمدة `payment_method` و `details` في `invoices` — كان مسار شحن المحفظة
 *    يمرّرهما لـ Invoice::create() وهما غير موجودين أصلاً في الجدول، فتُهمل
 *    بيانات بوابة الدفع بصمت ويتعذّر مطابقة أي إيصال بمعاملته عند النزاع.
 *
 * 2. حوض `pending_withdrawal_pool` في الخزينة المركزية — عند تقديم السائق
 *    طلب سحب يُخصم المبلغ من محفظته فوراً، فكان المال يبقى في فراغ محاسبي
 *    بين لحظة الطلب ولحظة موافقة الأدمن. الآن له حوض يحمله في تلك الفترة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('action_taken');
            }
            if (!Schema::hasColumn('invoices', 'details')) {
                $table->json('details')->nullable()->after('payment_method');
            }
        });

        Schema::table('master_escrow_vault', function (Blueprint $table) {
            if (!Schema::hasColumn('master_escrow_vault', 'pending_withdrawal_pool')) {
                $table->bigInteger('pending_withdrawal_pool')->default(0)->after('driver_available_pool');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            foreach (['payment_method', 'details'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('master_escrow_vault', function (Blueprint $table) {
            if (Schema::hasColumn('master_escrow_vault', 'pending_withdrawal_pool')) {
                $table->dropColumn('pending_withdrawal_pool');
            }
        });
    }
};
