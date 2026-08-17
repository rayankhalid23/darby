<?php

namespace App\Observers;

use App\Jobs\AnalyzeComplaintWithAi;
use App\Models\Shared\Complaint;

class ComplaintObserver
{
    /**
     * فور إنشاء أي شكوى (من أي نقطة دخول: ولي أمر، أدمن...) يُجدوَل تحليلها الآلي
     * في الخلفية (طابور) — لا يُنتظر داخل نفس طلب إنشاء الشكوى (كان يُبطئ استجابة
     * المستخدم حتى 3 ثوانٍ عند بطء/تعطل خدمة الذكاء الاصطناعي الخارجية).
     */
    public function created(Complaint $complaint): void
    {
        AnalyzeComplaintWithAi::dispatch($complaint->id);
    }
}
