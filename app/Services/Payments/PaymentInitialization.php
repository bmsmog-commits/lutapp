<?php

namespace App\Services\Payments;

// The only data a controller is allowed to hand back to the browser after
// initializing a payment — never the full provider response.
final class PaymentInitialization
{
    public function __construct(
        public readonly string $providerReference,
        public readonly string $checkoutUrl,
    ) {
    }
}
