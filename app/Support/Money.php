<?php

namespace App\Support;

/**
 * Exact money arithmetic on 2-decimal strings (bcmath). Never use floats for
 * balances. Database sums are passed through normalize() first because SQLite
 * returns SUM() of DECIMAL columns as a float (MySQL returns an exact decimal).
 */
final class Money
{
    public const SCALE = 2;

    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_float($value)) {
            $value = number_format(round($value, self::SCALE), self::SCALE, '.', '');
        }
        $value = trim((string) $value);
        if (! is_numeric($value)) {
            return '0.00';
        }

        // bcadd with scale 2 truncates; round half-up explicitly instead.
        $sign = str_starts_with($value, '-') ? '-' : '';
        $abs = ltrim($value, '-+');
        $rounded = bcadd($abs, '0.005', self::SCALE);

        return self::isZero($rounded) ? '0.00' : $sign.$rounded;
    }

    public static function add(string ...$values): string
    {
        $total = '0';
        foreach ($values as $v) {
            $total = bcadd($total, self::normalize($v), self::SCALE);
        }

        return self::normalize($total);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function isZero(string $a): bool
    {
        return bccomp($a, '0', self::SCALE) === 0;
    }

    public static function isNegative(string $a): bool
    {
        return bccomp(self::normalize($a), '0', self::SCALE) < 0;
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    /** $amount / $parts, rounded UP to the cent so the plan never falls short. */
    public static function divideUp(string $amount, int $parts): string
    {
        if ($parts <= 0) {
            return self::normalize($amount);
        }
        $q = bcdiv(self::normalize($amount), (string) $parts, 4);
        $cents = bcmul($q, '100', 4);
        $whole = bcadd($cents, '0', 0);
        if (bccomp($cents, $whole, 4) > 0) {
            $whole = bcadd($whole, '1', 0);
        }

        return bcdiv($whole, '100', self::SCALE);
    }

    /** Percentage of $part in $whole, 0–100 (capped), one decimal. */
    public static function percent(string $part, string $whole): float
    {
        if (bccomp(self::normalize($whole), '0', 2) <= 0) {
            return 0.0;
        }
        $pct = (float) bcdiv(bcmul(self::normalize($part), '100', 4), self::normalize($whole), 4);

        return round(max(0, min(100, $pct)), 1);
    }

    /** Display formatting, e.g. "Rs 12,500.00". Escaped by Blade when echoed. */
    public static function format(mixed $value, string $currency = 'PKR', bool $withSymbol = true): string
    {
        $value = self::normalize($value);
        $negative = str_starts_with($value, '-');
        [$int, $dec] = explode('.', ltrim($value, '-'));
        $formatted = number_format((int) $int).'.'.$dec;
        // number_format on huge ints is fine up to PHP_INT_MAX (well above DECIMAL(15,2)).

        $symbol = config("smartsave.currencies.$currency.symbol", $currency);
        $out = $withSymbol ? $symbol.' '.$formatted : $formatted;

        return $negative ? '-'.$out : $out;
    }
}
