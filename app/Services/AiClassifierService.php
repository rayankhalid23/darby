<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiClassifierService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.fastapi.base_url', 'http://127.0.0.1:8000');
    }

    /**
     * إرسال التقييمات لاتخاذ القرار من الذكاء الاصطناعي عبر نقطة النهاية POST /api/v1/make-decision
     */
    public function makeDecision(int|string $driverId, float $currentRating, array $reviews): ?array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/v1/make-decision", [
                'driver_id'      => (string) $driverId,
                'current_rating' => (float) $currentRating,
                'reviews'        => $reviews,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    return $data;
                }
            }

            Log::error("FastAPI Error [{$response->status()}]: " . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error("FastAPI Connection Exception: " . $e->getMessage());
            return null;
        }
    }
}