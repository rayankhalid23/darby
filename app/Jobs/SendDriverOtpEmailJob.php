<?php

namespace App\Jobs;

use App\Services\Shared\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDriverOtpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(
        public string $email,
        public string $fullName,
        public string $otp,
        public int $roleId,
        public ?string $gender = null,
        public string $purpose = 'REGISTER'
    ) {}

    public function handle(EmailService $emailService): void
    {
        try {
            $emailService->sendOtp(
                $this->email,
                $this->fullName,
                $this->otp,
                $this->roleId,
                $this->gender,
                $this->purpose
            );
        } catch (\Throwable $e) {
            Log::error("Failed to send Driver OTP Job: " . $e->getMessage());
            throw $e;
        }
    }
}
