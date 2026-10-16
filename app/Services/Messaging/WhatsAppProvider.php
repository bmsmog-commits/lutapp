<?php

namespace App\Services\Messaging;

/**
 * Phase 27.6 — the seam for WhatsApp delivery (weekly digests, verification).
 *
 * NO REAL WHATSAPP MESSAGE IS SENT ANYWHERE IN THIS CODEBASE. The single
 * implementation (NullWhatsAppProvider) only writes to the log.
 */
interface WhatsAppProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Returns false when nothing was actually delivered.
     */
    public function sendMessage(string $phone, string $message): bool;
}
