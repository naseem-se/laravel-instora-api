<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function toCents(string|int|float $amount): int
    {
        $normalized = number_format((float) $amount, 2, '.', '');
        $isNegative = str_starts_with($normalized, '-');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));

        $cents = ((int) $whole * 100) + (int) $fraction;

        return $isNegative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $isNegative = $cents < 0;
        $cents = abs($cents);

        $formatted = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $isNegative ? "-{$formatted}" : $formatted;
    }

    public static function splitEvenly(int $totalCents, int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Cannot split an amount into fewer than 1 part.');
        }

        $base = intdiv($totalCents, $parts);
        $shares = array_fill(0, $parts, $base);
        $shares[$parts - 1] += $totalCents - ($base * $parts);

        return $shares;
    }

    public static function percentageOf(int $cents, string|float $percent): int
    {
        return (int) round($cents * ((float) $percent) / 100);
    }

    /** Formats a decimal-string amount for human-readable display, e.g. in a notification message. */
    public static function format(string $currency, string $amount): string
    {
        return $currency.' '.number_format((float) $amount, 2);
    }
}