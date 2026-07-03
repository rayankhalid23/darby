<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (!Schema::hasColumn('contracts', 'subscription_type')) {
                $table->string('subscription_type')->after('contract_number')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'direction')) {
                $table->string('direction')->after('subscription_type')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'timing')) {
                $table->string('timing')->after('direction')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'start_date')) {
                $table->dateTime('start_date')->after('timing')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'end_date')) {
                $table->dateTime('end_date')->after('start_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'days_count')) {
                $table->integer('days_count')->after('end_date')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'total_price')) {
                $table->decimal('total_price', 10, 2)->after('days_count')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'clauses')) {
                $table->json('clauses')->after('total_price')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'status')) {
                $table->string('status')->after('clauses')->nullable();
            }
            if (!Schema::hasColumn('contracts', 'signed_at')) {
                $table->timestamp('signed_at')->nullable()->after('status');
            }
        });
    }

public function down(): void
{
    Schema::table('contracts', function (Blueprint $table) {
        $table->dropColumn([
            'subscription_type', 'direction', 'timing', 'start_date', 
            'end_date', 'days_count', 'total_price', 'clauses', 'status', 'signed_at'
        ]);
    });
}
};
