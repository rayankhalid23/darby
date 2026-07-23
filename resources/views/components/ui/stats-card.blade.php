@props([
    'title',
    'value',
    'trend',
    'iconBg' => 'bg-slate-50',
    'iconColor' => 'text-slate-600',
    'borderCol' => 'border-slate-100'
])

<div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex items-center justify-between text-right">
    <div class="space-y-1">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $title }}</p>
        <h3 class="text-xl font-bold text-slate-800">{{ $value }}</h3>
        
        @if(isset($trend))
            <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                {{ $trend }}
            </span>
        @endif
    </div>
    
    <div class="w-10 h-10 rounded-md {{ $iconBg }} {{ $iconColor }} flex items-center justify-center border border-slate-100">
        {{ $slot }}
    </div>
</div>