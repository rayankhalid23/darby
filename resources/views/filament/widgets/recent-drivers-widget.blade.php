<x-filament-widgets::widget>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">أحدث السائقين والرحلات المضافة</h3>
            <a href="#" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors">عرض كافة السائقين ←</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5 px-4">السائق</th>
                        <th class="p-3.5 px-4">المدينة / المنطقة</th>
                        <th class="p-3.5 px-4">نوع المركبة</th>
                        <th class="p-3.5 px-4">الحالة الحالية</th>
                        <th class="p-3.5 px-4">إجمالي الرحلات</th>
                        <th class="p-3.5 px-4 text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="p-3.5 px-4 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs">
                                مـ
                            </div>
                            <div>
                                <div class="font-bold text-slate-900">محمد الترهوني</div>
                                <div class="text-[10px] text-slate-400">091-2345678</div>
                            </div>
                        </td>
                        <td class="p-3.5 px-4">طرابلس (وسط المدينة)</td>
                        <td class="p-3.5 px-4">تويوتا كورولا 2022</td>
                        <td class="p-3.5 px-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> متصل
                            </span>
                        </td>
                        <td class="p-3.5 px-4 font-bold text-slate-900">1,420 رحلة</td>
                        <td class="p-3.5 px-4 text-center">
                            <button class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100">
                                <x-heroicon-o-ellipsis-vertical class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-filament-widgets::widget>