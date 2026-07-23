<aside class="w-64 h-screen bg-[#0F172A] text-slate-300 flex flex-col justify-between fixed top-0 right-0 z-20 border-l border-slate-800 shadow-xl"
       x-data="{ activeTab: 'dashboard' }">
    
    <!-- Brand Header -->
    <div>
        <div class="h-16 flex items-center px-6 border-b border-slate-800 bg-[#020617]/20">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/20">
                    <x-heroicon-s-truck class="w-5 h-5 text-white" />
                </div>
                <div class="text-right">
                    <h2 class="text-base font-black text-white tracking-wide">دَربِي <span class="text-blue-500">Derbi</span></h2>
                    <p class="text-[10px] text-blue-500 font-semibold mt-0.5">منظومة الربط الآمن طرابلس</p>
                </div>
            </div>
        </div>

        <!-- Navigation Items -->
        <nav class="p-3 space-y-1 overflow-y-auto max-h-[calc(100vh-220px)]">
            <!-- الرئيسية والمتابعة الحية -->
            <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/10' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/50'"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group text-right cursor-pointer">
                <div class="flex items-center gap-2.5">
                    <x-heroicon-o-squares-2-x-2 :class="activeTab === 'dashboard' ? 'text-white' : 'text-slate-400 group-hover:text-slate-200'" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-xs font-semibold">الرئيسية والمتابعة الحية</span>
                </div>
            </button>

            <!-- طلبات تسجيل السائقين -->
            <button @click="activeTab = 'drivers'"
                    :class="activeTab === 'drivers' ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/10' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/50'"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group text-right cursor-pointer">
                <div class="flex items-center gap-2.5">
                    <x-heroicon-o-user-plus :class="activeTab === 'drivers' ? 'text-white' : 'text-slate-400 group-hover:text-slate-200'" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-xs font-semibold">طلبات تسجيل السائقين</span>
                </div>
                <span :class="activeTab === 'drivers' ? 'bg-blue-800 text-white' : 'bg-rose-500/10 text-rose-400'" 
                      class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-black rounded-full transition-colors">
                    4
                </span>
            </button>

            <!-- طلبات تعديل البيانات -->
            <button @click="activeTab = 'updates'"
                    :class="activeTab === 'updates' ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/10' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/50'"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group text-right cursor-pointer">
                <div class="flex items-center gap-2.5">
                    <x-heroicon-o-arrow-path :class="activeTab === 'updates' ? 'text-white' : 'text-slate-400 group-hover:text-slate-200'" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-xs font-semibold">طلبات تعديل البيانات</span>
                </div>
                <span :class="activeTab === 'updates' ? 'bg-blue-800 text-white' : 'bg-rose-500/10 text-rose-400'" 
                      class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-black rounded-full transition-colors">
                    2
                </span>
            </button>

            <!-- مركز الشكاوى والبلاغات -->
            <button @click="activeTab = 'complaints'"
                    :class="activeTab === 'complaints' ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/10' : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/50'"
                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group text-right cursor-pointer">
                <div class="flex items-center gap-2.5">
                    <x-heroicon-o-exclamation-triangle :class="activeTab === 'complaints' ? 'text-white' : 'text-slate-400 group-hover:text-slate-200'" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110" />
                    <span class="text-xs font-semibold">مركز الشكاوى والبلاغات</span>
                </div>
                <span :class="activeTab === 'complaints' ? 'bg-blue-800 text-white' : 'bg-rose-500/10 text-rose-400'" 
                      class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-black rounded-full transition-colors">
                    5
                </span>
            </button>
        </nav>
    </div>

    <!-- Logged-in User Profile & Logout -->
    <div class="p-3 border-t border-slate-800/80 bg-slate-950/40">
        <div class="flex items-center gap-2.5 p-2 rounded-lg bg-slate-800/20 border border-slate-800/40 mb-2 text-right">
            <div class="w-8 h-8 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500 font-bold text-sm shadow-inner">
                م
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-xs font-semibold text-white truncate">م. عبد المهيمن</h4>
                <p class="text-[10px] text-slate-500 truncate">مدير المنظومة العام</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg bg-rose-500/5 hover:bg-rose-500/10 text-rose-400 hover:text-rose-300 font-semibold border border-rose-500/10 hover:border-rose-500/20 transition-all cursor-pointer text-xs">
                <x-heroicon-o-arrow-left-on-rectangle class="w-3.5 h-3.5" />
                <span>تسجيل الخروج</span>
            </button>
        </form>
    </div>
</aside>