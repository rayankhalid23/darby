<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class FleetStatusWidget extends Widget
{
    // تم إزالة كلمة static من هنا
    protected string $view = 'filament.widgets.fleet-status-widget';

    protected int | string | array $columnSpan = [
        'lg' => 1,
    ];
}