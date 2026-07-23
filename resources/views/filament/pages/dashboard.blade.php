<x-filament-panels::page>
    <div class="space-y-5 font-['Cairo'] dir-rtl -mt-3">
        
        <!-- 1️⃣ الشريط العلوي (Header) -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white/60 backdrop-blur-md p-2 rounded-2xl border border-slate-100">
            <!-- جهة اليمين: العنوان والتاريخ -->
            <div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight">الرئيسية والمتابعة الحية</h1>
                <p class="text-[11px] font-semibold text-slate-400 mt-0.5 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    الثلاثاء، 21 يوليو 2026 
                    <span class="text-slate-300">|</span> 
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    التوقيت المحلي لمدينة طرابلس (ليبيا)
                </p>
            </div>
            
            <!-- جهة اليسار: التنبيهات وحالة الاتصال والشخصية -->
            <div class="flex items-center gap-2.5">
                <!-- شارة الاتصال المشفر -->
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold bg-emerald-50/80 text-emerald-600 border border-emerald-200/60 shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    اتصال مشفر بالإدارة العامة
                </span>

                <!-- زر الإشعارات -->
                <button class="relative p-2 rounded-xl bg-white border border-slate-200/80 text-slate-600 hover:bg-slate-50 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 ring-2 ring-white"></span>
                </button>

                <!-- بطاقة المستخدم -->
                <div class="flex items-center gap-2.5 px-3 py-1.5 bg-blue-600 text-white rounded-xl text-xs font-bold shadow-xs">
                    <div class="w-6 h-6 rounded-lg bg-blue-700 border border-blue-500/50 flex items-center justify-center text-[11px] font-black">م</div>
                    <div class="flex flex-col leading-tight">
                        <span class="text-[12px] font-extrabold">مسؤول النظام</span>
                        <span class="text-[9px] text-blue-200 font-normal">مسؤول متصل بالكامل</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2️⃣ البطاقات الإحصائية الأربع (Stat Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            
            <!-- البطاقة 1: إجمالي مستخدمي المنصة (اليمين) -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400">إجمالي مستخدمي المنصة</span>
                    <div class="text-2xl font-black text-slate-900 mt-1 tracking-tight">1,248</div>
                    <span class="inline-flex items-center gap-1 mt-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                        ↗ +12% الأسبوع الماضي
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>

            <!-- البطاقة 2: السائقين المستقلين المفعلين -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400">السائقين المستقلين المفعلين</span>
                    <div class="text-2xl font-black text-slate-900 mt-1 tracking-tight">3</div>
                    <span class="inline-flex items-center gap-1 mt-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                        ↗ +8 سائقين جدد
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50/80 border border-blue-100 text-blue-500 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
            </div>

            <!-- البطاقة 3: الاشتراكات الشهرية النشطة -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400">الاشتراكات الشهرية النشطة</span>
                    <div class="text-2xl font-black text-slate-900 mt-1 tracking-tight">412</div>
                    <span class="inline-flex items-center gap-1 mt-2 text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                        نشطة حالياً
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
            </div>

            <!-- البطاقة 4: الرحلات النشطة حالياً (اليسار) -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex items-start justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400">الرحلات النشطة حالياً</span>
                    <div class="text-2xl font-black text-blue-600 mt-1 tracking-tight">3 رحلات</div>
                    <span class="inline-flex items-center gap-1 mt-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100">
                        ● متابعة مباشرة في طرابلس
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50/80 border border-emerald-100 text-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
            </div>

        </div>

        <!-- 3️⃣ القسم الرئيسي: قائمة الرحلات (يمين) والرادار المباشر (يسار) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            
            <!-- العمود الأيمن: قائمة الرحلات النشطة الآن (تستحوذ على 4 أعمدة بجوار القائمة) -->
            <div class="lg:col-span-4 bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3.5 pb-2 border-b border-slate-100">
                        <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            الرحلات النشطة الآن (3)
                        </h2>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100 animate-pulse"></span>
                    </div>

                    <div class="space-y-3">
                        <!-- السائق 1 -->
                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/40 hover:bg-white hover:border-blue-200 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img src="https://ui-avatars.com/api/?name=عبد+السلام+المصراتي&background=2563eb&color=fff" class="w-9 h-9 rounded-full ring-2 ring-slate-100">
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-900">عبد السلام المصراتي</h3>
                                        <p class="text-[10px] text-slate-400 font-mono dir-ltr text-right">091-3456789</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                    في الطريق للمدرسة
                                </span>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-1 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">الأطفال:</span>
                                    <span class="font-bold text-slate-800">علي ومروة</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">الوجهة:</span>
                                    <span class="font-medium text-slate-700 truncate max-w-[150px]">مدرسة الجيل الجديد الدولي...</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">مدة الرحلة:</span>
                                    <span class="font-bold text-blue-600">⏱ 12 دقيقة</span>
                                </div>
                            </div>
                        </div>

                        <!-- السائق 2 -->
                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/40 hover:bg-white hover:border-blue-200 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img src="https://ui-avatars.com/api/?name=مفتاح+الزنتاني&background=059669&color=fff" class="w-9 h-9 rounded-full ring-2 ring-slate-100">
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-900">مفتاح الزنتاني</h3>
                                        <p class="text-[10px] text-slate-400 font-mono dir-ltr text-right">092-6549873</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-sky-50 text-sky-600 border border-sky-100">
                                    في الطريق للاستلام
                                </span>
                            </div>

                            <div class="mt-2.5 pt-2 border-t border-slate-100 space-y-1 text-[11px]">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">الأطفال:</span>
                                    <span class="font-bold text-slate-800">أحمد وسند</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">الوجهة:</span>
                                    <span class="font-medium text-slate-700 truncate max-w-[150px]">مدرسة الشروق الأهلية، الس...</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">مدة الرحلة:</span>
                                    <span class="font-bold text-blue-600">⏱ 4 دقائق</span>
                                </div>
                            </div>
                        </div>

                        <!-- السائق 3 -->
                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/40 hover:bg-white hover:border-blue-200 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img src="https://ui-avatars.com/api/?name=علي+غومة&background=334155&color=fff" class="w-9 h-9 rounded-full ring-2 ring-slate-100">
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-900">علي غومة</h3>
                                        <p class="text-[10px] text-slate-400 font-mono dir-ltr text-right">092-2223344</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    تم استلام الطفل
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- العمود الأيسر: رادار المتابعة المباشر (تستحوذ على 8 أعمدة) -->
            <div class="lg:col-span-8 bg-white p-4 rounded-2xl border border-slate-200/70 shadow-2xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-sm font-extrabold text-slate-900">رادار المتابعة المباشر (طرابلس الكبرى)</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">رصد تحركات السائقين المستقلين ومواقع التوصيل المعتمدة</p>
                        </div>
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>إيقاف المحاكاة</span>
                        </button>
                    </div>

                    <!-- اللوحة الكحلية المظلمة لسيارات الرادار والمناطق -->
                    <div class="relative w-full h-[410px] bg-[#080e1e] rounded-xl overflow-hidden border border-slate-800/80 p-4">
                        <!-- تضاريس وخطوط الساحل المائية SVG -->
                        <svg class="absolute inset-0 w-full h-full" fill="none">
                            <path d="M0 110 C 250 160, 550 90, 1000 130" stroke="#1e293b" stroke-width="2.5"/>
                            <path d="M0 220 C 350 280, 650 180, 1000 240" stroke="#1e293b" stroke-width="1.5" stroke-dasharray="6 6"/>
                            <path d="M220 0 L 220 500" stroke="#0f172a" stroke-width="1"/>
                            <path d="M520 0 L 520 500" stroke="#0f172a" stroke-width="1"/>
                            <path d="M780 0 L 780 500" stroke="#0f172a" stroke-width="1"/>
                        </svg>

                        <!-- أسماء المناطق الموزعة كما في الصورة تماماً -->
                        <div class="absolute top-6 left-1/3 text-slate-500/70 text-[11px] font-medium tracking-widest">البحر الأبيض المتوسط</div>
                        
                        <div class="absolute top-28 left-1/3 text-slate-300 text-xs font-bold">حي الأندلس</div>
                        <div class="absolute top-32 left-10 text-slate-300 text-xs font-bold">السياحية</div>
                        <div class="absolute top-48 left-1/3 text-slate-300 text-xs font-bold">قرقارش</div>
                        <div class="absolute bottom-6 left-1/3 text-slate-400 text-xs font-semibold">السراج</div>

                        <div class="absolute top-36 right-1/3 text-slate-300 text-xs font-bold">بن عاشور</div>
                        <div class="absolute top-28 right-1/4 text-slate-300 text-xs font-bold">سوق الجمعة</div>
                        <div class="absolute top-52 right-1/3 text-slate-400 text-xs font-semibold">النوفليين</div>
                        <div class="absolute bottom-16 right-12 text-slate-300 text-xs font-bold">تاجوراء</div>

                        <!-- نقاط السيارات المتحركة مع تأثير Pulsing -->
                        <!-- السيارة 1 -->
                        <div class="absolute top-[32%] right-[38%] transition-all duration-700">
                            <div class="relative flex items-center justify-center">
                                <span class="animate-ping absolute inline-flex h-7 w-7 rounded-full bg-blue-500 opacity-75"></span>
                                <div class="relative bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] shadow-lg border border-white">🚗</div>
                            </div>
                        </div>

                        <!-- السيارة 2 -->
                        <div class="absolute top-[42%] left-[28%] transition-all duration-700">
                            <div class="relative flex items-center justify-center">
                                <span class="animate-ping absolute inline-flex h-7 w-7 rounded-full bg-blue-500 opacity-75"></span>
                                <div class="relative bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] shadow-lg border border-white">🚗</div>
                            </div>
                        </div>

                        <!-- السيارة 3 -->
                        <div class="absolute top-[58%] right-[22%] transition-all duration-700">
                            <div class="relative flex items-center justify-center">
                                <span class="animate-ping absolute inline-flex h-7 w-7 rounded-full bg-blue-500 opacity-75"></span>
                                <div class="relative bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px] shadow-lg border border-white">🚗</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</x-filament-panels::page>