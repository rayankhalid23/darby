@props([
    'title',
    'value',
    'percentage' => null,
    'trend' => 'up',
    'icon'
])
<div class="bg-white rounded-lg p-4 border border-slate-200 shadow-sm flex items-center justify-between" dir="rtl">
    <div class="space-y-1 text-right">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $title }}</p>
        <h3 class="text-xl font-bold text-slate-800">{{ $value }}</h3>
        
        @if($percentage)
            <span class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded 
                {{ $trend === 'up' ? 'text-emerald-600 bg-emerald-50' : '' }}
                {{ $trend === 'down' ? 'text-rose-600 bg-rose-50' : '' }}
                {{ $trend === 'info' ? 'text-blue-600 bg-blue-50' : '' }}">
                
                @if($trend === 'up')
                    <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 12.285-4.577m-13.418 4.61a11.947 11.947 0 0 0 10 10.155M15 11.25h5.25V6" /></svg>
                @endif
                <span>{{ $percentage }}</span>
            </span>
        @endif
    </div>
    
    <div class="w-10 h-10 rounded-md bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-600">
        {{ $icon }}
    </div>
</div>