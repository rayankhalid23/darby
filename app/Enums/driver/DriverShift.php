<?php

namespace App\Enums\driver;

/**
 * DriverShift — الفترة الزمنية التقليدية للسائق (للتوافق مع الكود القديم)
 * الخيارات الأربع التفصيلية (morning_go, morning_return...)
 * مخزنة كأعمدة boolean مستقلة في جدول drivers
 */
enum DriverShift: int
{
    case MORNING = 1;
    case EVENING = 2;
    case BOTH    = 3;

    /**
     * الحصول على المسمى العربي للفترة لعرضه في الـ API ولوحة التحكم
     */
    public function label(): string
    {
        return match($this) {
            self::MORNING => 'صباحي فقط',
            self::EVENING => 'مسائي فقط',
            self::BOTH    => 'الفترتين (صباحي + مسائي)',
        };
    }

    /**
     * قائمة بالخيارات الأربعة التفصيلية (مستخدمة في preferences API)
     */
    public static function detailedSlots(): array
    {
        return [
            ['key' => 'morning_go',      'label' => 'صباحي - ذهاب'],
            ['key' => 'morning_return',  'label' => 'صباحي - إياب'],
            ['key' => 'afternoon_go',    'label' => 'مسائي - ذهاب'],
            ['key' => 'afternoon_return','label' => 'مسائي - إياب'],
        ];
    }
}