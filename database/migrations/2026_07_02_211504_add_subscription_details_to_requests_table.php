<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('requests', function ($table) {
        // التحقق لكل عمود قبل إضافته
        if (!Schema::hasColumn('requests', 'subscription_type')) {
            $table->string('subscription_type')->nullable();
        }
        if (!Schema::hasColumn('requests', 'direction')) {
            $table->string('direction')->nullable();
        }
        if (!Schema::hasColumn('requests', 'timing')) {
            $table->string('timing')->nullable();
        }
        if (!Schema::hasColumn('requests', 'start_date')) {
            $table->date('start_date')->nullable();
        }
        if (!Schema::hasColumn('requests', 'end_date')) {
            $table->date('end_date')->nullable();
        }
        if (!Schema::hasColumn('requests', 'days_count')) {
            $table->integer('days_count')->nullable();
        }
        if (!Schema::hasColumn('requests', 'total_price')) {
            $table->decimal('total_price', 10, 2)->nullable();
        }
        if (!Schema::hasColumn('requests', 'status')) {
            $table->string('status')->default('pending');
        }
        if (!Schema::hasColumn('requests', 'notes')) {
            $table->text('notes')->nullable();
        }
        if (!Schema::hasColumn('requests', 'children_count')) {
            $table->integer('children_count')->nullable();
        }
        if (!Schema::hasColumn('requests', 'rejection_reason')) {
            $table->text('rejection_reason')->nullable();
        }
    });
}
    
    public function down()
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_type', 'direction', 'timing', 'start_date', 'end_date', 
                'days_count', 'total_price', 'status', 'notes', 'children_count', 'rejection_reason'
            ]);
        });
    }
};
