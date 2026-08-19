<?php

namespace App\Enums\Shared;

enum SubscriptionDuration: string
{
    case SINGLE_DAY = 'single_day';
    case MULTI_DAY  = 'multi_day';
    case BOTH       = 'both';

    public function label(): string
    {
        return match($this) {
            self::SINGLE_DAY => 'يوم واحد',
            self::MULTI_DAY  => 'عدة أيام',
            self::BOTH       => 'كلاهما (يوم واحد وعدة أيام)',
        };
    }

    public static function childValues(): array
    {
        return [self::SINGLE_DAY->value, self::MULTI_DAY->value];
    }

    public static function driverValues(): array
    {
        return [self::SINGLE_DAY->value, self::MULTI_DAY->value, self::BOTH->value];
    }

    public static function driverOptions(): array
    {
        return [
            ['value' => self::SINGLE_DAY->value, 'label' => self::SINGLE_DAY->label()],
            ['value' => self::MULTI_DAY->value,  'label' => self::MULTI_DAY->label()],
            ['value' => self::BOTH->value,        'label' => self::BOTH->label()],
        ];
    }
}
