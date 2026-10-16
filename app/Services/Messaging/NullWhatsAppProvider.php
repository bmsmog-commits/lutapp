<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;

class NullWhatsAppProvider implements WhatsAppProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        Log::info('WhatsApp message not sent — no WhatsApp provider is configured.', [
            'would_send_to' => $phone,
            'would_send_message' => $message,
            'provider' => $this->name(),
        ]);

        return false;
    }
}
