<?php

namespace App\Services\Ai;

/**
 * Phase 27.6 — the shape every AI assistant implementation must return.
 *
 * `answered` is the honesty flag: when it is false the UI must present the
 * response as a status message, never as an answer from an AI.
 */
final class AiAssistantResponse
{
    public function __construct(
        public readonly bool $answered,
        public readonly string $message,
        public readonly string $provider,
    ) {}

    public static function notConfigured(string $provider = 'null'): self
    {
        return new self(
            answered: false,
            message: __('messages.ai_not_configured'),
            provider: $provider,
        );
    }
}
