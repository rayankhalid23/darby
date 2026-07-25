<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class DriverDataUpdateRequests extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'طلبات تعديل البيانات';
    protected static ?string $title = 'طلبات تعديل بيانات السائقين';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.driver-data-update-requests';

    public int $selectedChangeId = 1;

    public function approveChange(int $id): void
    {
        Notification::make()
            ->title('تمت الموافقة على التعديلات وتحديث ملف السائق والمركبة بنجاح')
            ->success()
            ->send();
    }

    public function rejectChange(int $id): void
    {
        Notification::make()
            ->title('تم رفض طلب تعديل البيانات')
            ->danger()
            ->send();
    }

    public function getViewData(): array
    {
        $pendingChanges = [
            [
                'id' => 1,
                'req_code' => 'req-1',
                'driver_name' => 'عبد السلام المصراتي',
                'driver_avatar' => 'https://i.pravatar.cc/150?img=11',
                'date' => '19-07-2026',
                'old_data' => [
                    'phone' => '091-3456789',
                    'car' => 'هيونداي أفانتي 2018',
                    'plate' => '15-43928 ليبيا',
                    'zone' => 'حي الأندلس',
                ],
                'new_data' => [
                    'phone' => '091-3456789',
                    'car' => 'هيونداي سنتافي 2022',
                    'plate' => '8-99002 ليبيا',
                    'zone' => 'السراج',
                ]
            ]
        ];

        $historyChanges = [
            [
                'id' => 101,
                'req_code' => 'req-89',
                'driver_name' => 'مفتاح الزنتاني',
                'driver_avatar' => 'https://i.pravatar.cc/150?img=12',
                'date' => '15-07-2026',
                'status' => 'approved',
                'status_label' => 'تم القبول',
                'summary' => 'تحديث موديل المركبة إلى كيا سيراتو 2019',
            ],
            [
                'id' => 102,
                'req_code' => 'req-85',
                'driver_name' => 'علي غومة',
                'driver_avatar' => 'https://i.pravatar.cc/150?img=13',
                'date' => '10-07-2026',
                'status' => 'rejected',
                'status_label' => 'تم الرفض',
                'summary' => 'طلب تغيير منطقة التغطية إلى عين زارة (غير مستوفِ للوثائق)',
            ],
        ];

        return [
            'pendingChanges' => $pendingChanges,
            'historyChanges' => $historyChanges,
            'pendingCount' => count($pendingChanges),
        ];
    }
}
