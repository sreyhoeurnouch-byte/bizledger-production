<?php

namespace App\Jobs;

use App\Mail\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 120, 300]; // Retry after 1min, 2min, 5min

    public function __construct(
        public string $email,
        public string $resetUrl,
    ) {}

    public function handle(): void
    {
        Mail::to($this->email)->send(new PasswordResetMail($this->email, $this->resetUrl));
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Password reset email failed', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
