<?php

namespace App\Jobs;

use App\Models\Shared\Complaint;
use App\Services\DriverAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يشغّل تحليل الذكاء الاصطناعي للشكوى في الخلفية (طابور) بدل تعطيل استجابة
 * طلب إنشاء الشكوى بانتظار خدمة ai_service الخارجية (حتى 3 ثوانٍ سابقاً).
 */
class AnalyzeComplaintWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(protected int $complaintId)
    {
    }

    public function handle(DriverAiService $driverAiService): void
    {
        $complaint = Complaint::find($this->complaintId);
        if (!$complaint) {
            return;
        }

        try {
            $driverAiService->analyzeComplaint($complaint);
        } catch (Throwable $e) {
            Log::error("AnalyzeComplaintWithAi: فشل تحليل الشكوى رقم {$this->complaintId} - " . $e->getMessage());
        }
    }
}
