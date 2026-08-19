<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('device_id', 191)->nullable()->after('user_id');
            $table->string('app_version', 30)->nullable()->after('platform');
            $table->boolean('is_active')->default(true)->after('app_version');
            $table->timestamp('created_at')->nullable()->after('last_active_at');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        DB::table('user_devices')->whereNull('created_at')->update([
            'created_at' => DB::raw('last_active_at'),
            'updated_at' => DB::raw('last_active_at'),
        ]);

        Schema::table('user_devices', function (Blueprint $table) {
            $table->index(['user_id', 'device_id'], 'user_devices_user_id_device_id_index');
        });

        // fcm_token was originally a TEXT column. A UNIQUE index on TEXT/BLOB requires a
        // key-length prefix in MySQL, and a prefix index only compares the first N bytes —
        // two distinct tokens sharing the same prefix beyond that length would falsely
        // collide as duplicates. Real FCM registration tokens are ~140-180 chars; we widen
        // to VARCHAR(500) as headroom and put a UNIQUE index on the FULL column value, so
        // uniqueness is enforced by MySQL on the entire token, not a truncated prefix.
        DB::statement('ALTER TABLE user_devices MODIFY COLUMN fcm_token VARCHAR(500) NOT NULL');
        DB::statement('ALTER TABLE user_devices ADD UNIQUE INDEX user_devices_fcm_token_unique (fcm_token)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_devices DROP INDEX user_devices_fcm_token_unique');
        DB::statement('ALTER TABLE user_devices MODIFY COLUMN fcm_token TEXT NOT NULL');

        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropIndex('user_devices_user_id_device_id_index');
            $table->dropColumn(['device_id', 'app_version', 'is_active', 'created_at', 'updated_at']);
        });
    }
};
