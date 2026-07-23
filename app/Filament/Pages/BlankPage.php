<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class BlankPage extends Page
{
    // ✅ إزالة كلمة static من هنا
    protected string $view = 'filament.pages.blank-page';

    // إلغاء الـ Widgets لضمان عدم تداخل أي إحصائيات قديمة
    public function getHeaderWidgets(): array
    {
        return [];
    }

    public function getFooterWidgets(): array
    {
        return [];
    }
}