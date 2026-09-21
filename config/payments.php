<?php

// Phase 15 giving/payments foundation. Provider secrets live only here (backed
// by env vars) — never in a database column, never sent to Blade/JSON.

return [

    'default_provider' => env('PAYMENT_PROVIDER', 'fake'),

    'currencies' => ['NGN', 'USD', 'GBP', 'EUR'],

    'providers' => [

        // The only provider actually wired up in Phase 15. It follows the same
        // shape a real adapter (Paystack/Flutterwave/Stripe) would: an
        // initialize step, a server-to-server verify step, and an
        // HMAC-signed webhook — so swapping in a real provider later is a new
        // class implementing PaymentProviderContract, not a domain rewrite.
        'fake' => [
            'webhook_secret' => env('FAKE_PAYMENTS_WEBHOOK_SECRET', 'fake-payments-test-secret'),
        ],

        // Example shape for a future real adapter — intentionally not
        // implemented or wired to `default_provider` in this phase:
        // 'paystack' => [
        //     'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        //     'secret_key' => env('PAYSTACK_SECRET_KEY'),
        //     'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        // ],
    ],

];
