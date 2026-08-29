<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تحديث جميع نصوص الإشعارات الموجودة في قاعدة البيانات لتكون نصوصاً عربية صريحة ومقروءة بدلاً من \uXXXX
     */
    public function up(): void
    {
        $notifications = DB::table('notifications')->get(['id', 'data']);

        foreach ($notifications as $notification) {
            if (empty($notification->data)) {
                continue;
            }

            $decoded = json_decode($notification->data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $cleanJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                DB::table('notifications')
                    ->where('id', $notification->id)
                    ->update(['data' => $cleanJson]);
            }
        }
    }

    public function down(): void
    {
        // لا حاجة للتراجع
    }
};
