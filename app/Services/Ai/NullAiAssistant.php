<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * The only AiAssistantInterface implementation in this application.
 *
 * It answers nothing. It returns an unanswered response carrying a plain status
 * message. It never generates scripture, never paraphrases scripture, and never
 * presents canned text as if an AI had produced it.
 */
class NullAiAssistant implements AiAssistantInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function answer(string $prompt, ?User $user = null): AiAssistantResponse
    {
        return AiAssistantResponse::notConfigured($this->name());
    }
}
