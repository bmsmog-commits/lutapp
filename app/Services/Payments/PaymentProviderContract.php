<?php

namespace App\Services\Payments;

use App\Models\GivingTransaction;
use Illuminate\Http\Request;

// The application domain depends on this contract only, never on a specific
// provider's SDK/HTTP client — Paystack/Flutterwave/Stripe/etc. all become a
// class implementing this, with no changes needed to controllers/services
// that already work against GivingTransaction.
interface PaymentProviderContract
{
    public function name(): string;

    // Called once, right after the pending GivingTransaction row is created.
    // Must never mark anything successful — only hands back checkout details.
    public function initialize(GivingTransaction $transaction): PaymentInitialization;

    // Server-to-server confirmation, keyed by OUR reference (never a
    // client-supplied provider transaction id) — the only source of truth for
    // whether a payment actually succeeded.
    public function verify(string $reference): PaymentVerificationResult;

    // Validates the inbound webhook's signature/authenticity and extracts our
    // internal reference from it. Returns null if the signature is missing,
    // malformed, or does not match — callers must treat that as "reject",
    // never fall back to trusting the payload anyway.
    public function resolveWebhookReference(Request $request): ?string;
}
