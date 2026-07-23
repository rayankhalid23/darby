<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ActiveTripsWidget extends Widget
{
    protected string $view = 'filament.widgets.active-trips-widget';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'lg' => 1,
    ];
}