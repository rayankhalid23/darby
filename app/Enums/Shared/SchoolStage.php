<?php

namespace App\Enums\Shared;

enum SchoolStage: string
{
    case KINDERGARTEN = 'kindergarten';
    case PRIMARY      = 'primary';
    case MIDDLE       = 'middle';
    case SECONDARY    = 'secondary';

    public function label(): string
    {
        return match($this) {
            self::KINDERGARTEN => 'روضة',
            self::PRIMARY      => 'ابتدائي',
            self::MIDDLE       => 'إعدادي',
            self::SECONDARY    => 'ثانوي',
        };
    }

    /** grade رقم الصف → المرحلة الدراسية */
    public static function fromGrade(int $grade): self
    {
        return match(true) {
            $grade === 0    => self::KINDERGARTEN,
            $grade <= 6     => self::PRIMARY,
            $grade <= 9     => self::MIDDLE,
            default         => self::SECONDARY,
        };
    }

    public static function gradeRanges(): array
    {
        return [
            ['value' => self::KINDERGARTEN->value, 'label' => self::KINDERGARTEN->label(), 'grade_range' => [0, 0]],
            ['value' => self::PRIMARY->value,      'label' => self::PRIMARY->label(),      'grade_range' => [1, 6]],
            ['value' => self::MIDDLE->value,       'label' => self::MIDDLE->label(),       'grade_range' => [7, 9]],
            ['value' => self::SECONDARY->value,    'label' => self::SECONDARY->label(),    'grade_range' => [10, 12]],
        ];
    }
}
