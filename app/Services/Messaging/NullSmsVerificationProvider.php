<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * The only SmsVerificationProvider implementation in this application.
 *
 * It sends nothing. It writes one log line and returns false, so every caller
 * can tell that delivery did not happen. It never pretends to have succeeded.
 */
class NullSmsVerificationProvider implements SmsVerificationProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function sendVerificationCode(string $phone, string $code): bool
    {
        Log::info('SMS verification not sent — no SMS provider is configured.', [
            'would_send_to' => $phone,
            'would_send_code' => $code,
            'provider' => $this->name(),
        ]);

        return false;
    }
}
