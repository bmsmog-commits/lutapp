<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiAssistantInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 27.6 — the AI entry point.
 *
 * This controller does not call any AI vendor. It resolves whatever
 * AiAssistantInterface implementation is bound (today: NullAiAssistant only)
 * and returns its response verbatim, including the `answered` honesty flag.
 * When `answered` is false the UI renders the message as a status, never as an
 * answer, so nothing is ever presented as AI-generated when it is not.
 */
class AiAssistantController extends Controller
{
    public function status(AiAssistantInterface $assistant): JsonResponse
    {
        return response()->json([
            'configured' => $assistant->isConfigured(),
            'provider' => $assistant->name(),
            'message' => $assistant->isConfigured()
                ? __('messages.ai_ready')
                : __('messages.ai_not_configured'),
        ]);
    }

    public function ask(Request $request, AiAssistantInterface $assistant): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $response = $assistant->answer($data['prompt'], $request->user());

        return response()->json([
            'answered' => $response->answered,
            'configured' => $assistant->isConfigured(),
            'provider' => $response->provider,
            'message' => $response->message,
        ], $response->answered ? 200 : 503);
    }
}
