<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class LiveMonitor extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'طلبات تعديل البيانات';
    protected static ?string $title = 'طلبات تحديث بيانات السائقين والمستندات';

    // تم إزالة كلمة static من هنا
    protected string $view = 'filament.pages.live-monitor';

    public string $activeTab = 'all';
    public int $selectedDriverId = 1;
    public string $notes = '';

    public array $drivers = [
        1 => [
            'id' => 1,
            'name' => 'علي أحمد القمودي',
            'code' => '#DRV-8842',
            'phone' => '092-8877665',
            'type' => 'تحديث رخصة القيادة والمركبة',
            'date' => '12 يوليو 2026',
            'status' => 'pending',
            'status_label' => 'قيد الانتظار',
            'license_img' => 'https://via.placeholder.com/400x220?text=Driving+License',
            'vehicle_img' => 'https://via.placeholder.com/400x220?text=Vehicle+Doc',
        ],
        2 => [
            'id' => 2,
            'name' => 'سالم عبدالسلام الساعدي',
            'code' => '#DRV-9011',
            'phone' => '091-7766554',
            'type' => 'تجديد تأمين السيارة',
            'date' => '10 يوليو 2026',
            'status' => 'approved',
            'status_label' => 'تمت الموافقة',
            'license_img' => 'https://via.placeholder.com/400x220?text=Insurance+Doc',
            'vehicle_img' => 'https://via.placeholder.com/400x220?text=Vehicle+Doc',
        ],
    ];

    public function selectDriver(int $id): void
    {
        $this->selectedDriverId = $id;
    }

    public function approve(): void
    {
        Notification::make()
            ->title('تم توثيق التحديث بنجاح')
            ->success()
            ->send();
    }

    public function reject(): void
    {
        if (empty($this->notes)) {
            Notification::make()
                ->title('يرجى إضافة سبب الرفض في الملاحظات')
                ->warning()
                ->send();
            return;
        }

        Notification::make()
            ->title('تم رفض الطلب وإرسال الملاحظات للسائق')
            ->danger()
            ->send();
    }
}