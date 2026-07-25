<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Derbi | لوحة التحكم')</title>
    <!-- Tailwind CSS CDN أو الملف المحزّن لديك -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap');
        body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
        /* تخصيص شريط التمرير للسوداوية والاحترافية */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0b1329; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 3px; }
    </style>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

    <!-- ================= 1. الشريط الجانبي (Sidebar) ================= -->
    <aside class="w-72 bg-[#0b1329] text-gray-300 flex flex-col justify-between h-full shadow-2xl transition-all duration-300 z-30">
        <div>
            <!-- هيدر الشريط الجانبي (الشعار) -->
            <div class="p-5 flex items-center gap-3 border-b border-gray-800/60">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-600/30">
                    <i class="fa-solid fa-car text-lg"></i>
                </div>
                <div>
                    <h1 class="text-white font-bold text-lg tracking-wide">دربي Derbi</h1>
                    <p class="text-xs text-blue-400 font-medium">منظومة الربط الآمن طرابلس</p>
                </div>
            </div>

            <!-- قائمة الروابط (Navigation Links) -->
            <nav class="p-4 space-y-1.5 overflow-y-auto max-h-[calc(100vh-200px)]">
                
                <!-- عنصر رئيسي (نشط حالياً) -->
                <a href="#" class="flex items-center justify-between px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow-lg shadow-blue-600/25 transition-all duration-200">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-house-laptop text-base"></i>
                        <span>الرئيسية والمتابعة الحية</span>
                    </div>
                </a>

                <!-- عناصر عادية مع تفاعل Hover ناعم -->
                <a href="#" class="flex items-center justify-between px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-user-clock text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                        <span>طلبات تسجيل السائقين</span>
                    </div>
                    <span class="bg-rose-500/20 text-rose-400 text-xs px-2 py-0.5 rounded-full font-bold">2</span>
                </a>

                <a href="#" class="flex items-center justify-between px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-rotate text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                        <span>طلبات تعديل البيانات</span>
                    </div>
                    <span class="bg-rose-500/20 text-rose-400 text-xs px-2 py-0.5 rounded-full font-bold">1</span>
                </a>

                <a href="#" class="flex items-center justify-between px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                        <span>مركز الشكاوى والبلاغات</span>
                    </div>
                    <span class="bg-rose-500/20 text-rose-400 text-xs px-2 py-0.5 rounded-full font-bold">2</span>
                </a>

                <a href="#" class="flex items-center justify-between px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-wallet text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                        <span>الإدارة المالية والسحب</span>
                    </div>
                    <span class="bg-rose-500/20 text-rose-400 text-xs px-2 py-0.5 rounded-full font-bold">2</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <i class="fa-solid fa-user-shield text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                    <span>إدارة موظفي الإدارة</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <i class="fa-solid fa-chart-pie text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                    <span>التقارير والإحصائيات العامة</span>
                </a>

                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-400 hover:bg-gray-800/50 hover:text-white transition-all duration-200 group">
                    <i class="fa-solid fa-user-gear text-gray-500 group-hover:text-blue-400 transition-colors"></i>
                    <span>الملف الشخصي وتعديل البيانات</span>
                </a>

            </nav>
        </div>

        <!-- أسفل الشريط الجانبي (بيانات المستخدم وزر الخروج) -->
        <div class="p-4 border-t border-gray-800/60 bg-[#080e1f]">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-600/30 border border-blue-500/30 flex items-center justify-center text-blue-400 font-bold">
                        م
                    </div>
                    <div>
                        <h4 class="text-white text-sm font-bold">مسؤول النظام</h4>
                        <p class="text-[11px] text-gray-400">مدير الإدارة العامة</p>
                    </div>
                </div>
            </div>
            <button onclick="if(confirm('هل تريد تسجيل الخروج؟')) { /* route logout */ }" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border border-rose-500/30 text-rose-400 hover:bg-rose-500/10 transition-all text-xs font-semibold">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>تسجيل الخروج</span>
            </button>
        </div>
    </aside>

    <!-- ================= 2. محتوى الصفحة والشريط العلوي ================= -->
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        
        <!-- الشريط العلوي (Topbar) -->
        <header class="h-20 bg-white border-b border-gray-100 flex items-center justify-between px-8 shadow-sm z-20">
            <!-- العنوان والتاريخ -->
            <div>
                <h2 class="text-xl font-bold text-gray-800">@yield('page_title', 'الرئيسية والمتابعة الحية')</h2>
                <div class="flex items-center gap-4 text-xs text-gray-500 mt-0.5">
                    <span class="flex items-center gap-1.5"><i class="fa-regular fa-calendar-days text-gray-400"></i> الأربعاء، 22 يوليو 2026</span>
                    <span class="flex items-center gap-1.5"><i class="fa-regular fa-clock text-gray-400"></i> التوقيت المحلي لمدينة طرابلس (ليبيا)</span>
                </div>
            </div>

            <!-- الجهة اليمنى من الـ Topbar (تنبيهات وحالة الاتصال) -->
            <div class="flex items-center gap-4">
                <!-- حالة الاتصال -->
                <div class="hidden md:flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-3.5 py-1.5 rounded-full text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>اتصال مشفر بالإدارة العامة</span>
                </div>

                <!-- زر الإشعارات مع تفاعل -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="w-10 h-10 rounded-xl bg-gray-50 hover:bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-600 transition relative">
                        <i class="fa-regular fa-bell text-lg"></i>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full"></span>
                    </button>
                </div>

                <!-- بروفايل مصغر أو شارة المسؤول -->
                <div class="flex items-center gap-3 pr-4 border-r border-gray-200">
                    <div class="text-left hidden sm:block">
                        <span class="block text-xs font-bold text-gray-800">مسؤول النظام</span>
                        <span class="block text-[10px] text-emerald-600 font-semibold">مسؤول متصل بالكامل</span>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-blue-600/20">
                        م
                    </div>
                </div>
            </div>
        </header>

        <!-- المحتوى المتغير لكل واجهة (Main Content Area) -->
        <main class="flex-1 overflow-y-auto p-8 bg-[#f8fafc]">
            @yield('content')
        </main>
    </div>

</body>
</html>