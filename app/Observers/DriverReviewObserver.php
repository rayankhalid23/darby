<?php

namespace App\Observers;

use App\Jobs\AnalyzeReviewWithAi;
use App\Models\Shared\DriverReview;

class DriverReviewObserver
{
    /**
     * فور إضافة تقييم/تعليق من ولي أمر، يُجدوَل تحليل التعليق النصي آلياً في
     * الخلفية (طابور) — لا يُنتظر داخل نفس طلب إنشاء التقييم. التعليقات الفارغة
     * تُتجاهَل تلقائياً داخل DriverAiService::analyzeReview() دون استدعاء الخدمة.
     */
    public function created(DriverReview $review): void
    {
        AnalyzeReviewWithAi::dispatch($review->id);
    }
}
