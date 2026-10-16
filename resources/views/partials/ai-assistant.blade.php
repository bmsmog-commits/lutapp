@php
    // Phase 27.6 — AI entry point.
    // Resolved from the container; today the only binding is NullAiAssistant,
    // so $aiConfigured is always false and the panel shows a status message.
    // No vendor is contacted and no answer is ever fabricated.
    $aiAssistant = app(\App\Services\Ai\AiAssistantInterface::class);
    $aiConfigured = $aiAssistant->isConfigured();
@endphp

<button type="button"
        class="ai-fab"
        id="ai-fab"
        aria-label="{{ __('messages.ai_open') }}"
        aria-expanded="false"
        aria-controls="ai-panel"
        aria-haspopup="dialog">
    {{-- Phase 27.7 — a labelled pill, deliberately not a second circle, so the
         corner reads as "main nav" vs "assistant". --}}
    <span class="ai-fab-mark" aria-hidden="true">&#10022;</span>
    <span class="ai-fab-label">Lutapp AI</span>
</button>

<div class="ai-panel" id="ai-panel" data-open="false" role="dialog" aria-modal="false" aria-labelledby="ai-panel-title" data-configured="{{ $aiConfigured ? 'true' : 'false' }}">
    <div class="ai-panel-head">
        <strong id="ai-panel-title"><span class="ai-fab-mark" aria-hidden="true">&#10022;</span>{{ __('messages.ai_title') }}</strong>
        <button type="button" class="ai-panel-close" id="ai-panel-close" aria-label="{{ __('messages.ai_close') }}">&times;</button>
    </div>

    <div class="ai-panel-body">
        @if ($aiConfigured)
            <label class="sr-only" for="ai-prompt">{{ __('messages.ai_prompt_label') }}</label>
            <textarea id="ai-prompt" class="input" rows="3" placeholder="{{ __('messages.ai_prompt_label') }}"></textarea>
            <button type="button" class="btn btn-primary" id="ai-send">{{ __('messages.ai_send') }}</button>
        @else
            <div class="ai-status" role="status">
                <p class="ai-status-title">{{ __('messages.ai_not_configured') }}</p>
                <p class="muted">{{ __('messages.ai_not_configured_detail') }}</p>
            </div>
        @endif
        <p id="ai-result" class="ai-result" hidden></p>
    </div>
</div>
