<?php

namespace App\Jobs;

use App\Models\Shared\DriverReview;
use App\Services\DriverAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يشغّل تحليل الذكاء الاصطناعي لتعليق مراجعة السائق في الخلفية (طابور) بدل
 * تعطيل استجابة طلب إضافة التقييم بانتظار خدمة ai_service الخارجية.
 */
class AnalyzeReviewWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(protected int $reviewId)
    {
    }

    public function handle(DriverAiService $driverAiService): void
    {
        $review = DriverReview::find($this->reviewId);
        if (!$review) {
            return;
        }

        try {
            $driverAiService->analyzeReview($review);
        } catch (Throwable $e) {
            Log::error("AnalyzeReviewWithAi: فشل تحليل تعليق المراجعة رقم {$this->reviewId} - " . $e->getMessage());
        }
    }
}
