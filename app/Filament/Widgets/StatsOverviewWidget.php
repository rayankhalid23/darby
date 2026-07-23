<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class StatsOverviewWidget extends Widget
{
    // تم إزالة كلمة static من هنا
    protected string $view = 'filament.widgets.stats-overview-widget';

    protected int | string | array $columnSpan = 'full';
}