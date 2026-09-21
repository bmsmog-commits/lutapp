<?php

namespace App\Support;

// All four Phase 15 currencies (NGN/USD/GBP/EUR) use 2 decimal places, so a
// single fixed-precision conversion is correct for all of them without a
// per-currency decimal-places table. No currency conversion happens here —
// this only moves between a currency's own display and minor-unit forms.
class Money
{
    private const DECIMAL_PLACES = 2;

    // "100.00" -> 10000. Deliberately string-based (not float multiplication)
    // to avoid binary floating-point rounding artifacts on monetary values.
    public static function toMinorUnits(string|int|float $decimalAmount): int
    {
        $normalized = number_format((float) $decimalAmount, self::DECIMAL_PLACES, '.', '');
        [$whole, $fraction] = explode('.', $normalized);

        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = ltrim($whole, '-');

        return $sign * ((int) $whole * (10 ** self::DECIMAL_PLACES) + (int) $fraction);
    }

    public static function toDecimalString(int $minorUnits): string
    {
        return number_format($minorUnits / (10 ** self::DECIMAL_PLACES), self::DECIMAL_PLACES, '.', '');
    }
}
