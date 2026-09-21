<?php

namespace App\Services\Payments;

// The result of asking a provider, server-to-server, about one transaction.
// Deliberately narrow — only what verification actually needs to check — so a
// provider adapter can never leak raw payload fields into it by accident.
final class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $status,
        public readonly string $reference,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $providerTransactionId,
        public readonly ?string $paymentMethod = null,
        public readonly array $safeMetadata = [],
    ) {
    }

    // The one check that actually matters for Step 11: the provider's own
    // numbers must agree with what we initialized, not just report "success".
    public function matches(string $reference, int $amount, string $currency): bool
    {
        return $this->reference === $reference
            && $this->amount === $amount
            && $this->currency === $currency;
    }
}
