<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentProviderFactory
{
    public static function make(?string $name = null): PaymentProviderContract
    {
        $name ??= (string) config('payments.default_provider');

        return match ($name) {
            'fake' => new FakePaymentProvider(),
            default => throw new InvalidArgumentException("Unknown payment provider: {$name}"),
        };
    }
}
