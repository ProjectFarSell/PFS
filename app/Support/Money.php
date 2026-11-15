<?php

namespace App\Support;

use InvalidArgumentException;

/** Exact decimal-peso boundary; all financial calculations use integer centavos. */
class Money
{
    public static function cents(string $amount): int
    {
        if (! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException('Enter a nonnegative peso amount with at most two decimal places.');
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        $absolute = abs($cents);

        return ($cents < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
