<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Double-submit protection. Each create form carries a one-time UUID
 * (`idempotency_key`). The first request claims it atomically; repeats are
 * ignored. If the action fails, the claim is released so the user can retry.
 * Ledger rows additionally store the key under a unique index.
 */
final class Idempotency
{
    public static function newKey(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Runs $action once per key. Returns null when the key was already used.
     */
    public static function once(Request $request, Closure $action): mixed
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            return $action();
        }

        $cacheKey = 'idem:'.$request->user()->id.':'.$key;
        if (! Cache::add($cacheKey, true, now()->addHours(12))) {
            return null;
        }

        try {
            return $action();
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }
    }
}
