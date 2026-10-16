<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * Phase 27.6 — the seam for an AI study assistant.
 *
 * THERE IS NO REAL AI PROVIDER IN THIS CODEBASE. No HTTP call is made to
 * OpenAI, Anthropic or any other vendor, and no API key exists in code, config
 * or .env. The only binding is NullAiAssistant, which returns a
 * "not yet configured" status and never fabricates an answer — and, critically,
 * never fabricates Bible verse text.
 *
 * A future real implementation must be bound in AppServiceProvider behind an
 * explicit config flag, with credentials read from env.
 */
interface AiAssistantInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    public function answer(string $prompt, ?User $user = null): AiAssistantResponse;
}
