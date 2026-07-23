<x-filament-panels::page>
    <div dir="rtl" class="space-y-6 font-sans">

        <!-- Header Banner -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div>
                <h1 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                    <x-heroicon-o-arrow-path class="w-6 h-6 text-amber-500" />
                    طلبات تحديث بيانات السائقين والمستندات
                </h1>
                <p class="text-xs text-slate-500 mt-1">مراجعة والتحقق من رخص القيادة والأوراق المرفوعة من السائقين</p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 text-xs font-bold px-3 py-1.5 rounded-lg border border-amber-200">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                    14 طلب معلق ينتظر المراجعة
                </span>
            </div>
        </div>

        <!-- Filter Tabs Bar -->
        <div class="flex items-center gap-2 border-b border-slate-200 pb-3 overflow-x-auto">
            <button wire:click="$set('activeTab', 'all')" class="{{ $activeTab === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200' }} text-xs font-bold px-4 py-2 rounded-lg transition-colors cursor-pointer">
                الكل (28)
            </button>
            <button wire:click="$set('activeTab', 'pending')" class="{{ $activeTab === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }} text-xs font-bold px-4 py-2 rounded-lg transition-colors cursor-pointer flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span> المعلقة (14)
            </button>
            <button wire:click="$set('activeTab', 'approved')" class="{{ $activeTab === 'approved' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 border border-slate-200' }} text-xs font-bold px-4 py-2 rounded-lg transition-colors cursor-pointer flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> المعتمدة (10)
            </button>
        </div>

        <!-- Main Split Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Request Item List -->
            <div class="space-y-3">
                @foreach($drivers as $driver)
                    <div wire:click="selectDriver({{ $driver['id'] }})" 
                         class="bg-white p-4 rounded-xl shadow-sm cursor-pointer relative transition-all border-2 {{ $selectedDriverId === $driver['id'] ? 'border-blue-600' : 'border-slate-200 hover:border-slate-300' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-sm border border-slate-200">
                                    {{ mb_substr($driver['name'], 0, 1) }}ـ
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">{{ $driver['name'] }}</h4>
                                    <p class="text-[10px] text-slate-400">{{ $driver['type'] }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $driver['status'] === 'pending' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                {{ $driver['status_label'] }}
                            </span>
                        </div>

                        <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                            <span>تاريخ الطلب: {{ $driver['date'] }}</span>
                            <span class="font-bold text-blue-600">معاينة المستندات ←</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Detailed Review Panel -->
            @php $current = $drivers[$selectedDriverId] ?? $drivers[1]; @endphp
            <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-6">
                
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">تفاصيل مستندات: {{ $current['name'] }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">رقم السائق التعريفية: {{ $current['code'] }} • هاتف: {{ $current['phone'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors cursor-pointer">
                            قبول وتوثيق التحديث
                        </button>
                        <button wire:click="reject" class="bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold px-4 py-2 rounded-lg transition-colors cursor-pointer">
                            رفض الطلب
                        </button>
                    </div>
                </div>

                <!-- Document Images Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span>صورة رخصة القيادة الجديدة</span>
                            <span class="text-[10px] text-emerald-600 font-semibold">واضحة ومقروءة</span>
                        </div>
                        <div class="h-44 bg-slate-200 rounded-md overflow-hidden relative border border-slate-300 flex items-center justify-center text-slate-400 text-xs">
                            <img src="{{ $current['license_img'] }}" alt="License" class="w-full h-full object-cover">
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                            <span>كتيب المركبة (الكتيب الرمادي)</span>
                            <span class="text-[10px] text-amber-600 font-semibold">ينتهي قريباً</span>
                        </div>
                        <div class="h-44 bg-slate-200 rounded-md overflow-hidden relative border border-slate-300 flex items-center justify-center text-slate-400 text-xs">
                            <img src="{{ $current['vehicle_img'] }}" alt="Vehicle" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>

                <!-- Notes Input -->
                <div class="space-y-2 pt-2">
                    <label class="block text-xs font-bold text-slate-700">ملاحظات التدقيق (تُرسل للسائق في حال الرفض):</label>
                    <textarea wire:model="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" placeholder="اكتب سبب الرفض أو أي ملاحظات إضافية هنا..."></textarea>
                </div>

            </div>

        </div>
    </div>
</x-filament-panels::page>