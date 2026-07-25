<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\Driver\Driver;

class DriverRegistrationRequests extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'طلبات تسجيل السائقين';
    protected static ?string $title = 'طلبات تسجيل السائقين والمراجعة';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.driver-registration-requests';

    public string $search = '';
    public string $statusFilter = 'all'; // all, pending, approved, rejected
    public int $selectedDriverId = 4;

    public function selectDriver(int $id): void
    {
        $this->selectedDriverId = $id;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    public function approveDriver(int $id): void
    {
        Notification::make()
            ->title('تم قبول الطلب وتفعيل حساب السائق بنجاح')
            ->success()
            ->send();
    }

    public function rejectDriver(int $id): void
    {
        Notification::make()
            ->title('تم رفض طلب تسجيل السائق')
            ->danger()
            ->send();
    }

    public function getViewData(): array
    {
        $driversList = [
            [
                'id' => 1,
                'code' => 'drv-1',
                'name' => 'عبد السلام المصراتي',
                'avatar' => 'https://i.pravatar.cc/150?img=11',
                'status' => 'approved',
                'status_label' => 'مقبول',
                'region' => 'حي الأندلس',
                'car' => 'هيونداي أفانتي 2018',
                'phone' => '091-3456789',
                'email' => 'abdelsalam@gmail.com',
                'plate' => '15-43928 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'verified', 'status_label' => 'موثق'],
                ]
            ],
            [
                'id' => 2,
                'code' => 'drv-2',
                'name' => 'مفتاح الزنتاني',
                'avatar' => 'https://i.pravatar.cc/150?img=12',
                'status' => 'approved',
                'status_label' => 'مقبول',
                'region' => 'السياحية',
                'car' => 'كيا سيراتو 2019',
                'phone' => '092-6549873',
                'email' => 'meftah.zantani@gmail.com',
                'plate' => '12-99211 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'verified', 'status_label' => 'موثق'],
                ]
            ],
            [
                'id' => 3,
                'code' => 'drv-3',
                'name' => 'محمد الورفلي',
                'avatar' => 'https://i.pravatar.cc/150?img=68',
                'status' => 'pending',
                'status_label' => 'معلق',
                'region' => 'سوق الجمعة',
                'car' => 'تويوتا كامري 2020',
                'phone' => '091-8884422',
                'email' => 'm.warfalli@gmail.com',
                'plate' => '10-55441 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'pending', 'status_label' => 'قيد المراجعة'],
                ]
            ],
            [
                'id' => 4,
                'code' => 'drv-4',
                'name' => 'خالد التاجوري',
                'avatar' => 'https://i.pravatar.cc/150?img=60',
                'status' => 'pending',
                'status_label' => 'معلق',
                'region' => 'تاجوراء',
                'car' => 'نيسان صني 2017',
                'phone' => '094-1112233',
                'email' => 'khaled.taj@gmail.com',
                'plate' => '11-38294 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'pending', 'status_label' => 'قيد المراجعة'],
                ]
            ],
            [
                'id' => 5,
                'code' => 'drv-5',
                'name' => 'صالح الترهوني',
                'avatar' => 'https://i.pravatar.cc/150?img=33',
                'status' => 'rejected',
                'status_label' => 'مرفوض',
                'region' => 'السراج',
                'car' => 'تويوتا كورولا 2019',
                'phone' => '091-9993322',
                'email' => 'saleh.tarhoni@gmail.com',
                'plate' => '9-77112 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'rejected', 'status_label' => 'غير مكتمل'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'rejected', 'status_label' => 'مرفوض'],
                ]
            ],
            [
                'id' => 6,
                'code' => 'drv-6',
                'name' => 'علي غومة',
                'avatar' => 'https://i.pravatar.cc/150?img=13',
                'status' => 'approved',
                'status_label' => 'مقبول',
                'region' => 'عين زارة',
                'car' => 'هيونداي إنترا 2020',
                'phone' => '092-2223344',
                'email' => 'ali.ghoma@gmail.com',
                'plate' => '14-88901 ليبيا',
                'documents' => [
                    ['name' => 'رخصة القيادة الرسمية', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'كتيب المركبة الرسمي', 'status' => 'verified', 'status_label' => 'موثق'],
                    ['name' => 'بطاقة الهوية / الرقم الوطني', 'status' => 'verified', 'status_label' => 'موثق'],
                ]
            ],
        ];

        // Counts
        $totalCount = count($driversList);
        $pendingCount = count(array_filter($driversList, fn($d) => $d['status'] === 'pending'));
        $approvedCount = count(array_filter($driversList, fn($d) => $d['status'] === 'approved'));
        $rejectedCount = count(array_filter($driversList, fn($d) => $d['status'] === 'rejected'));

        // Filtering
        $filteredDrivers = array_filter($driversList, function ($d) {
            if ($this->statusFilter !== 'all' && $d['status'] !== $this->statusFilter) {
                return false;
            }
            if (!empty($this->search)) {
                $q = mb_strtolower($this->search);
                $nameMatch = mb_strpos(mb_strtolower($d['name']), $q) !== false;
                $phoneMatch = mb_strpos($d['phone'], $q) !== false;
                $regionMatch = mb_strpos(mb_strtolower($d['region']), $q) !== false;
                return $nameMatch || $phoneMatch || $regionMatch;
            }
            return true;
        });

        // Selected driver
        $selectedDriver = collect($driversList)->firstWhere('id', $this->selectedDriverId) ?? $driversList[3];

        return [
            'drivers' => array_values($filteredDrivers),
            'selectedDriver' => $selectedDriver,
            'counts' => [
                'total' => $totalCount,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
            ]
        ];
    }
}
