<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class TripoliLiveRadarWidget extends Widget
{
    protected string $view = 'filament.widgets.tripoli-live-radar-widget';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'md' => 2,
        'lg' => 2,
    ];
}