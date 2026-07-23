<div class="mt-auto p-4 space-y-3 border-t border-slate-800/80">
    <!-- بطاقة مسؤول النظام -->
    <div class="flex items-center justify-between p-3 bg-slate-900/90 rounded-2xl border border-slate-800/80">
        <div class="text-right">
            <div class="text-sm font-bold text-white">مسؤول النظام</div>
            <div class="text-xs text-slate-400">مدير الإدارة العامة</div>
        </div>
        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-base shadow-md">
            م
        </div>
    </div>

    <!-- زر تسجيل الخروج -->
    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf
        <button type="submit" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-rose-950/20 hover:bg-rose-900/40 text-rose-300 border border-rose-900/40 rounded-xl text-xs font-bold transition">
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            تسجيل الخروج
        </button>
    </form>
</div>