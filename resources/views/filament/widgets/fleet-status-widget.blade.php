<x-filament-widgets::widget>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">حالة الأسطول والسائقين الآن</h3>
        
        <div class="space-y-3">
            <!-- Status 1: Active Drivers -->
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-xs font-bold text-slate-700">سائق متصل ومتاح</span>
                </div>
                <span class="text-sm font-black text-slate-900">642</span>
            </div>

            <!-- Status 2: In Trip -->
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span class="text-xs font-bold text-slate-700">سائق في رحلة حالياً</span>
                </div>
                <span class="text-sm font-black text-slate-900">184</span>
            </div>

            <!-- Status 3: Break -->
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span class="text-xs font-bold text-slate-700">في استراحة / مؤقت</span>
                </div>
                <span class="text-sm font-black text-slate-900">92</span>
            </div>

            <!-- Status 4: Offline -->
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    <span class="text-xs font-bold text-slate-700">غير متصل</span>
                </div>
                <span class="text-sm font-black text-slate-900">330</span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>