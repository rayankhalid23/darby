<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    // تم إزالة كلمة static من هنا
    protected ?string $heading = 'منحنى النمو اليومي للرحلات الإجمالية';
    
    protected int | string | array $columnSpan = [
        'lg' => 2,
    ];

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'الرحلات',
                    'data' => [12, 19, 25, 32, 48, 60, 75, 90, 110, 140, 184],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'الإيراد (د.ل)',
                    'data' => [200, 450, 800, 1200, 2100, 3400, 4800, 6200, 8100, 11000, 14200],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 5],
                    'tension' => 0.4,
                ],
            ],
            'labels' => ['8am', '9am', '10am', '11am', '12pm', '1pm', '2pm', '3pm', '4pm', '5pm', '6pm'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}