<header style="height: 64px; background-color: #ffffff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; padding: 0 24px; width: 100%; position: sticky; top: 0; z-index: 20; direction: rtl; box-sizing: border-box;"
        x-data="{ showNotifications: false }">
        
    <!-- Tab Info & Date -->
    <div style="display: flex; flex-direction: column; gap: 2px; text-align: right;">
        <h1 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.2;">الرئيسية والمتابعة الحية</h1>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 10px; color: #94a3b8; font-weight: 500;">
            <svg style="width: 12px; height: 12px; color: #cbd5e1; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            <span>{{ Carbon\Carbon::now()->locale('ar-LY')->translatedFormat('l، j F Y') }}</span>
            <span style="color: #e2e8f0;">|</span>
            <svg style="width: 12px; height: 12px; color: #cbd5e1; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>التوقيت المحلي لمدينة طرابلس (ليبيا)</span>
        </div>
    </div>

    <!-- Utilities Section -->
    <div style="display: flex; align-items: center; gap: 16px;">
        <!-- Secure State Badge -->
        <div style="display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 9999px; background-color: #ecfdf5; color: #047857; font-size: 10px; font-weight: 700; border: 1px solid #d1fae5;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
            <span>اتصال مشفر بالإدارة العامة</span>
            <svg style="width: 12px; height: 12px; flex-shrink: 0;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
        </div>

        <!-- Notification Panel (Alpine.js Dropdown) -->
        <div style="position: relative;">
            <button @click="showNotifications = !showNotifications"
                    style="width: 36px; height: 36px; border-radius: 8px; background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative;">
                <svg style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                <span style="position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background-color: #ef4444; border-radius: 50%; border: 1px solid #ffffff;"></span>
            </button>

            <!-- Dropdown Container -->
            <div x-show="showNotifications"
                 @click.away="showNotifications = false"
                 style="position: absolute; left: 0; margin-top: 8px; width: 280px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden; z-index: 30; display: none;">
                <div style="padding: 10px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background-color: #f8fafc;">
                    <h3 style="font-weight: 700; color: #020617; font-size: 12px; margin: 0;">التنبيهات العاجلة</h3>
                    <span style="font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 9999px; background-color: #ef4444; color: #ffffff;">2 جديدة</span>
                </div>
                
                <div style="max-height: 280px; overflow-y: auto;">
                    <div style="padding: 12px; background-color: rgba(37, 99, 235, 0.03); border-bottom: 1px solid #f1f5f9; text-align: right;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px;">
                            <h4 style="font-size: 11px; font-weight: 700; color: #0f172a; margin: 0;">طلب سحب جديد معلق</h4>
                            <span style="font-size: 8px; color: #94a3b8;">منذ دقيقتين</span>
                        </div>
                        <p style="font-size: 11px; color: #64748b; margin: 0; line-height: 1.4;">قدم السائق مفتاح الزنتاني طلب سحب بقيمة 600 د.ل.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>