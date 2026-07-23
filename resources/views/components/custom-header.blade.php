<header style="height: 4rem; width: 100%; background-color: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid #e2e8f0; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 30; direction: rtl; user-select: none;">
    
    <!-- العناوين (يمين) -->
    <div style="text-align: right;">
        <span style="font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">طرابلس الكبرى</span>
        <h2 style="font-size: 0.75rem; font-weight: 700; color: #1e293b; line-height: 1; margin-top: 0.25rem; margin-bottom: 0;">بوابة التحكم والعمليات المباشرة</h2>
    </div>

    <!-- الإشعارات والرادار (يسار) -->
    <div style="display: flex; align-items: center; gap: 1rem;">
        
        <!-- رادار النظام -->
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; color: #334155; border-radius: 0.5rem; padding: 0.25rem 0.625rem; font-size: 10px; font-weight: 700; display: flex; align-items: center; gap: 0.375rem;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981; animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></span>
            <span>رادار النظام: نشط ومستقر</span>
        </div>

        <!-- زر الإشعارات -->
        <button style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background-color: transparent; display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; color: #475569; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
            <svg style="width: 1rem; height: 1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <span style="position: absolute; top: 0.375rem; left: 0.375rem; width: 0.5rem; height: 0.5rem; border-radius: 50%; background-color: #ef4444; border: 1px solid #ffffff;"></span>
        </button>
    </div>
</header>
<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}
</style>