<?php

namespace Tests;

use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function goalFor(User $user, array $attributes = []): SavingsGoal
    {
        return SavingsGoal::factory()->for($user)->create($attributes);
    }

    /** Write a ledger entry through the real service (same rules as the app). */
    protected function entry(User $user, ?SavingsGoal $goal, string $type, string $amount, ?string $date = null): SavingsTransaction
    {
        return app(SavingsLedger::class)->record($user, [
            'type' => $type,
            'goal_id' => $goal?->id,
            'amount' => $amount,
            'transaction_date' => $date ?? now()->toDateString(),
        ]);
    }

    protected function balanceOf(SavingsGoal $goal): string
    {
        return $goal->fresh()->freshBalance();
    }
}
