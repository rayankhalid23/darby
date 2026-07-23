<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'دربي ليبيا - منصة النقل المدرسي' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Styles & Scripts (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Livewire Styles (إذا كنت تستخدم Livewire) -->
    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full bg-slate-50 font-['Cairo'] antialiased text-slate-900 selection:bg-blue-600 selection:text-white">

    <div x-data="{ sidebarOpen: false }" class="min-h-screen flex flex-col">
        
        <!-- استدعاء الشريط الجانبي -->
        @include('components.layouts.sidebar')

        <!-- المحتوى الرئيسي (مربوط بالهامش الأيمن lg:mr-64 ليناسب الـ RTL) -->
        <div class="flex-1 flex flex-col min-w-0 transition-all duration-200 lg:mr-64">
            
            <!-- استدعاء الشريط العلوي -->
            @include('components.layouts.header')

            <!-- الصفحة الأساسية -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                {{ $slot }}
            </main>

            <!-- التذييل -->
            <footer class="border-t border-slate-200 bg-white px-6 py-4 text-center text-xs font-semibold text-slate-500">
                <span>&copy; {{ date('Y') }} دربي ليبيا - جميع الحقوق محفوظة</span>
            </footer>
        </div>

        <!-- الخلفية المظلمة للهواتف عند فتح القائمة -->
        <div 
            x-cloak
            x-show="sidebarOpen" 
            x-transition:enter="transition-opacity ease-linear duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>
    </div>

    <!-- Livewire Scripts -->
    @livewireScripts
</body>
</html>