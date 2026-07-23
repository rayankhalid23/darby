<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'نظرة عامة على الإحصائيات';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\RevenueChartWidget::class,
            \App\Filament\Widgets\FleetStatusWidget::class,
            \App\Filament\Widgets\RecentDriversWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 3;
    }
}