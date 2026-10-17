<?php

namespace App\Http\Requests;

/** Shared validation for money fields: positive, at most 2 decimals, bounded. */
final class AmountRules
{
    public static function required(): array
    {
        return ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.config('smartsave.max_amount')];
    }

    public static function optional(): array
    {
        return ['nullable', 'numeric', 'decimal:0,2', 'gte:0', 'max:'.config('smartsave.max_amount')];
    }

    /** Past or today only: future-dated savings would inflate today's balance. */
    public static function pastDate(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'];
    }
}
