<x-filament-widgets::widget>
    <x-filament::section>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                    رادار المتابعة المباشر (طرابلس الكبرى)
                </h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    رصد تحركات السائقين المستقلين ومواقع التوصيل المعتمدة
                </p>
            </div>
            <div>
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 0 0118 0z"></path></svg>
                    إيقاف المحاكاة
                </button>
            </div>
        </div>

        <!-- Simulated Map Dark Container -->
        <div class="relative w-full h-[380px] bg-[#070d1e] rounded-2xl overflow-hidden border border-slate-800 shadow-inner select-none">
            
            <!-- Water Background (البحر الأبيض المتوسط) -->
            <div class="absolute top-0 left-0 right-0 h-16 bg-[#040814] border-b border-blue-900/20 flex items-center justify-center">
                <span class="text-xs font-medium text-blue-300/30 tracking-widest">البحر الأبيض المتوسط</span>
            </div>

            <!-- Map Roads & Routes SVG -->
            <svg class="absolute inset-0 w-full h-full text-blue-500/20 stroke-current" fill="none" stroke-width="2" stroke-dasharray="6 6">
                <!-- Main Coastal Road -->
                <path d="M 0 120 Q 300 200 800 280" stroke-width="3" />
                <!-- Inner Ring Roads -->
                <path d="M 150 380 Q 250 250 500 220" />
                <path d="M 300 380 Q 400 300 700 350" />
            </svg>

            <!-- Region Labels (أسماء المناطق في طرابلس) -->
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 32%; left: 35%;">حي الأندلس</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 45%; left: 15%;">السياحية</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 55%; left: 28%;">قرقارش</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 40%; left: 55%;">بن عاشور</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 32%; left: 65%;">سوق الجمعة</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 50%; left: 78%;">تاجوراء</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 65%; left: 60%;">النوفليين</div>
            <div class="absolute text-slate-400/60 text-xs font-bold pointer-events-none" style="top: 78%; left: 45%;">السراج</div>

            <!-- Live Vehicle Markers -->
            <div class="absolute flex flex-col items-center animate-bounce" style="top: 28%; left: 48%;">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-blue-500/50 ring-2 ring-blue-300">
                    🚘
                </div>
            </div>

            <div class="absolute flex flex-col items-center animate-pulse" style="top: 48%; left: 24%;">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-blue-500/50 ring-2 ring-blue-300">
                    🚘
                </div>
            </div>

            <div class="absolute flex flex-col items-center" style="top: 35%; left: 70%;">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-blue-500/50 ring-2 ring-blue-300">
                    🚘
                </div>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>