<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class RecentDriversWidget extends Widget
{
    // تم إزالة كلمة static من هنا
    protected string $view = 'filament.widgets.recent-drivers-widget';

    protected int | string | array $columnSpan = 'full';
}