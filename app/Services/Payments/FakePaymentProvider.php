<?php

namespace App\Services\Payments;

use App\Models\GivingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The only concrete provider wired up in Phase 15 — no live payment
 * credentials exist for this environment. It follows the same three-step
 * shape (initialize / verify / signed webhook) a real Paystack/Flutterwave/
 * Stripe adapter would, so swapping one in later means adding a class, not
 * changing the giving domain.
 *
 * Its in-memory ledger stands in for the provider's own servers. It is
 * intentionally process-static (not persisted) — a real provider's state
 * lives on their infrastructure, not ours, and this fake only needs to
 * survive the lifetime of a single test/request cycle.
 */
class FakePaymentProvider implements PaymentProviderContract
{
    private static array $ledger = [];

    public function name(): string
    {
        return 'fake';
    }

    public function initialize(GivingTransaction $transaction): PaymentInitialization
    {
        self::$ledger[$transaction->reference] = [
            'status' => 'pending',
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'provider_transaction_id' => null,
        ];

        return new PaymentInitialization(
            providerReference: 'fake_ref_'.$transaction->reference,
            checkoutUrl: route('giving.checkout.show', ['reference' => $transaction->reference]),
        );
    }

    public function verify(string $reference): PaymentVerificationResult
    {
        $entry = self::$ledger[$reference] ?? null;

        if (! $entry) {
            return new PaymentVerificationResult(
                successful: false,
                status: 'not_found',
                reference: $reference,
                amount: 0,
                currency: '',
                providerTransactionId: null,
            );
        }

        return new PaymentVerificationResult(
            successful: $entry['status'] === 'successful',
            status: $entry['status'],
            reference: $reference,
            amount: $entry['amount'],
            currency: $entry['currency'],
            providerTransactionId: $entry['provider_transaction_id'],
            paymentMethod: 'card',
            safeMetadata: ['channel' => 'card'],
        );
    }

    // Mirrors a real HMAC-signed webhook (e.g. Paystack's x-paystack-signature):
    // reject outright — never fall back to trusting the body — on any missing
    // or mismatched signature.
    public function resolveWebhookReference(Request $request): ?string
    {
        $secret = (string) config('payments.providers.fake.webhook_secret');
        $signature = $request->header('X-Fake-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! is_string($signature) || $signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $reference = $request->input('reference');

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    // --- Test/dev-only simulation hooks. A real provider needs none of this —
    // its own dashboard/test cards are the "simulate" mechanism there. ---

    public static function simulateOutcome(
        string $reference,
        string $status,
        ?int $amountOverride = null,
        ?string $currencyOverride = null,
    ): string {
        if (! isset(self::$ledger[$reference])) {
            throw new \RuntimeException("No initialized fake transaction for reference [{$reference}].");
        }

        $providerTransactionId = 'fake_txn_'.Str::random(20);

        self::$ledger[$reference]['status'] = $status;
        self::$ledger[$reference]['provider_transaction_id'] = $providerTransactionId;

        if ($amountOverride !== null) {
            self::$ledger[$reference]['amount'] = $amountOverride;
        }

        if ($currencyOverride !== null) {
            self::$ledger[$reference]['currency'] = $currencyOverride;
        }

        return $providerTransactionId;
    }

    public static function resetLedger(): void
    {
        self::$ledger = [];
    }
}
