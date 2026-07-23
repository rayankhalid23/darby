<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // الشعار العلوي
            ->brandLogo(new HtmlString('
                <div class="flex items-center gap-3 p-1">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-600/30">
                        🚘
                    </div>
                    <div class="text-right">
                        <div class="text-base font-extrabold text-white leading-tight">دربي Derbi</div>
                        <div class="text-[11px] text-slate-400 font-medium">منظومة الربط الآمن طرابلس</div>
                    </div>
                </div>
            '))
            ->colors([
                'primary' => Color::Hex('#1d4ed8'),
                'gray' => Color::Slate,
            ])
            // ربط مجلدات الصفحات والـ Widgets تلقائياً
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // حقن تنسيقات CSS الخاصة بالتصميم
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '
                <style>
                    /* خلفية الشريط الجانبي الداكنة */
                    .fi-sidebar, .fi-sidebar-header {
                        background-color: #0b132a !important;
                        border-color: #1e293b !important;
                    }

                    /* تنسيق أزرار القائمة */
                    .fi-sidebar-item-button {
                        color: #94a3b8 !important;
                        border-radius: 0.75rem !important;
                        padding: 0.65rem 0.85rem !important;
                        margin: 0.2rem 0.5rem !important;
                        transition: all 0.2s ease-in-out !important;
                    }

                    .fi-sidebar-item-button .fi-sidebar-item-icon,
                    .fi-sidebar-item-button .fi-sidebar-item-label {
                        color: #94a3b8 !important;
                        transition: all 0.2s ease-in-out !important;
                    }

                    /* تأثير المرور Hover */
                    .fi-sidebar-item-button:hover {
                        background-color: #2563eb !important;
                        color: #ffffff !important;
                    }

                    .fi-sidebar-item-button:hover .fi-sidebar-item-icon,
                    .fi-sidebar-item-button:hover .fi-sidebar-item-label,
                    .fi-sidebar-item-button:hover svg {
                        color: #ffffff !important;
                    }

                    /* الزر النشط Active */
                    .fi-sidebar-item-active > .fi-sidebar-item-button,
                    .fi-sidebar-item-button[aria-current="page"] {
                        background-color: #1d4ed8 !important;
                        color: #ffffff !important;
                        box-shadow: 0 4px 12px rgba(29, 78, 216, 0.4) !important;
                    }

                    .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-icon,
                    .fi-sidebar-item-active > .fi-sidebar-item-button .fi-sidebar-item-label,
                    .fi-sidebar-item-active > .fi-sidebar-item-button svg {
                        color: #ffffff !important;
                        font-weight: 700 !important;
                    }

                    /* الشارات */
                    .fi-sidebar-item-badge {
                        background-color: #e11d48 !important;
                        color: #ffffff !important;
                        font-weight: bold !important;
                        font-size: 0.75rem !important;
                        border-radius: 9999px !important;
                        padding: 0.1rem 0.55rem !important;
                    }
                </style>
                '
            )
            // إضافة كارت المستخدم في الأسفل
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => '
                <div class="mt-auto p-4 space-y-3 border-t border-slate-800/80">
                    <div class="flex items-center justify-between p-3 bg-slate-900/90 rounded-2xl border border-slate-800/80">
                        <div class="text-right">
                            <div class="text-sm font-bold text-white">مسؤول النظام</div>
                            <div class="text-xs text-slate-400">مدير الإدارة العامة</div>
                        </div>
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-base shadow-md">
                            م
                        </div>
                    </div>
                    <form method="POST" action="'.route('filament.admin.auth.logout').'">
                        <input type="hidden" name="_token" value="'.csrf_token().'">
                        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-rose-950/20 hover:bg-rose-900/40 text-rose-300 border border-rose-900/40 rounded-xl text-xs font-bold transition">
                            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            تسجيل الخروج
                        </button>
                    </form>
                </div>
                '
            );
    }
}