<x-filament-widgets::widget>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Stat Card 1: Total Drivers -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">إجمالي السائقين</span>
                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-lg">
                    <x-heroicon-o-users class="w-5 h-5" />
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-2xl font-black text-slate-900">1,248</span>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                    +12.5%
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">مقارنة بالأسبوع الماضي</p>
        </div>

        <!-- Stat Card 2: Active Trips -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">الرحلات النشطة الآن</span>
                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg">
                    <x-heroicon-o-map-pin class="w-5 h-5" />
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-2xl font-black text-slate-900">184</span>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                    +8.2%
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">رحلة قيد التنفيذ حالياً</p>
        </div>

        <!-- Stat Card 3: Total Revenue -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">إجمالي الإيرادات اليوم</span>
                <div class="p-2.5 bg-amber-50 text-amber-600 rounded-lg">
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-2xl font-black text-slate-900">42,850 <span class="text-xs font-bold text-slate-500">د.ل</span></span>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">
                    +15.4%
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">صافي دخل المنصة</p>
        </div>

        <!-- Stat Card 4: Service Rating -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500">متوسط تقييم الخدمة</span>
                <div class="p-2.5 bg-purple-50 text-purple-600 rounded-lg">
                    <x-heroicon-o-star class="w-5 h-5" />
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <span class="text-2xl font-black text-slate-900">4.88 <span class="text-xs font-semibold text-slate-400">/ 5.0</span></span>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                    9,420 تقييم
                </span>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">نسبة رضا العملاء 96%</p>
        </div>

    </div>
</x-filament-widgets::widget>