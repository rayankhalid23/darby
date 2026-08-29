<?php

namespace App\Models\Shared;

use Illuminate\Notifications\DatabaseNotification as BaseNotification;

class DatabaseNotification extends BaseNotification
{
    /**
     * تشفير الـ JSON مع الاحتفاظ بالأحرف العربية والرموز التعبيرية كنصوص حقيقية مقروءة في قاعدة البيانات
     */
    protected function asJson($value, $flags = 0)
    {
        return json_encode($value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
