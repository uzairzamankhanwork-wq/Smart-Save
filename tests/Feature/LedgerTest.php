<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\FinanceSummary;
use App\Services\SavingsLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function post_tx(array $data)
    {
        return $this->post('/transactions', array_merge(['transaction_date' => now()->toDateString()], $data));
    }

    public function test_contributions_and_withdrawals_update_balance(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);

        $this->actingAs($user);
        $this->post_tx(['type' => 'initial', 'goal_id' => $goal->id, 'amount' => '1000.10'])->assertSessionHasNoErrors();
        $this->post_tx(['type' => 'deposit', 'goal_id' => $goal->id, 'amount' => '0.20'])->assertSessionHasNoErrors();
        $this->post_tx(['type' => 'withdrawal', 'goal_id' => $goal->id, 'amount' => '500.05'])->assertSessionHasNoErrors();

        // 1000.10 + 0.20 - 500.05, exact (no float drift)
        $this->assertSame('500.25', $this->balanceOf($goal));
        $this->assertSame('500.25', app(SavingsLedger::class)->totalBalance($user));
        $this->assertSame(3, SavingsTransaction::count());
        $this->assertSame(3, ActivityLog::where('action', 'transaction.created')->count());
    }

    public function test_withdrawal_cannot_make_goal_negative(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, $goal, 'deposit', '100');

        $this->actingAs($user)->post_tx(['type' => 'withdrawal', 'goal_id' => $goal->id, 'amount' => '100.01'])
            ->assertSessionHasErrors('amount');

        $this->assertSame('100.00', $this->balanceOf($goal));
        $this->assertSame(1, SavingsTransaction::count(), 'Rejected withdrawal must be rolled back');
    }

    public function test_unallocated_savings_also_cannot_go_negative(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post_tx(['type' => 'withdrawal', 'goal_id' => null, 'amount' => '1'])->assertSessionHasErrors('amount');
        $this->assertSame(0, SavingsTransaction::count());
    }

    public function test_invalid_amounts_are_rejected_server_side(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->actingAs($user);

        foreach (['0', '-10', '10.555', 'abc', '1e3', '', '9999999999999'] as $bad) {
            $this->post_tx(['type' => 'deposit', 'goal_id' => $goal->id, 'amount' => $bad])->assertSessionHasErrors('amount');
        }
        $this->post_tx(['type' => 'refund', 'goal_id' => $goal->id, 'amount' => '5'])->assertSessionHasErrors('type');
        $this->post_tx(['type' => 'deposit', 'goal_id' => $goal->id, 'amount' => '5', 'transaction_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('transaction_date');

        $this->assertSame(0, SavingsTransaction::count());
    }

    public function test_duplicate_submission_is_recorded_once(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $data = ['type' => 'deposit', 'goal_id' => $goal->id, 'amount' => '250', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($user)->post_tx($data)->assertSessionHas('success');
        $this->post_tx($data)->assertSessionHas('info');
        $this->post_tx($data)->assertSessionHas('info');

        $this->assertSame(1, SavingsTransaction::count());
        $this->assertSame('250.00', $this->balanceOf($goal));
    }

    public function test_same_idempotency_key_from_different_users_is_independent(): void
    {
        $key = (string) Str::uuid();
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAs($a)->post_tx(['type' => 'deposit', 'goal_id' => null, 'amount' => '1', 'idempotency_key' => $key])->assertSessionHas('success');
        $this->actingAs($b)->post_tx(['type' => 'deposit', 'goal_id' => null, 'amount' => '1', 'idempotency_key' => $key])->assertSessionHas('success');
        $this->assertSame(2, SavingsTransaction::count());
    }

    public function test_transfer_moves_money_without_changing_total(): void
    {
        $user = User::factory()->create();
        $a = $this->goalFor($user, ['name' => 'A']);
        $b = $this->goalFor($user, ['name' => 'B']);
        $this->entry($user, $a, 'deposit', '1000');

        $this->actingAs($user)->post('/transfers', [
            'from_goal_id' => $a->id, 'to_goal_id' => $b->id, 'amount' => '400', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHas('success');

        $this->assertSame('600.00', $this->balanceOf($a));
        $this->assertSame('400.00', $this->balanceOf($b));
        $this->assertSame('1000.00', app(SavingsLedger::class)->totalBalance($user), 'Transfers must not inflate savings');

        $legs = SavingsTransaction::whereNotNull('transfer_group')->get();
        $this->assertCount(2, $legs);
        $this->assertSame(1, $legs->pluck('transfer_group')->unique()->count());

        // Transfers are not "saved this month".
        $month = app(FinanceSummary::class)->savingsBetween($user, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame('1000.00', $month['contributed']);
        $this->assertSame('1000.00', $month['net']);
    }

    public function test_transfer_to_and_from_unallocated(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $this->entry($user, null, 'deposit', '300');

        $ledger = app(SavingsLedger::class);
        $ledger->transfer($user, null, $goal->id, '300', now()->toDateString());
        $this->assertSame('0.00', $ledger->bucketBalance($user, null));
        $this->assertSame('300.00', $this->balanceOf($goal));
        $this->assertSame('300.00', $ledger->totalBalance($user));
    }

    public function test_failed_transfer_rolls_back_both_legs(): void
    {
        $user = User::factory()->create();
        $a = $this->goalFor($user);
        $b = $this->goalFor($user);
        $this->entry($user, $a, 'deposit', '50');

        $this->actingAs($user)->post('/transfers', [
            'from_goal_id' => $a->id, 'to_goal_id' => $b->id, 'amount' => '75', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, SavingsTransaction::whereNotNull('transfer_group')->count(), 'Neither leg may survive');
        $this->assertSame('50.00', $this->balanceOf($a));
        $this->assertSame('0.00', $this->balanceOf($b));
    }

    public function test_transfer_to_same_goal_is_rejected(): void
    {
        $user = User::factory()->create();
        $a = $this->goalFor($user);
        $this->expectException(ValidationException::class);
        app(SavingsLedger::class)->transfer($user, $a->id, $a->id, '1', now()->toDateString());
    }

    public function test_voiding_a_transfer_reverses_both_legs(): void
    {
        $user = User::factory()->create();
        $a = $this->goalFor($user);
        $b = $this->goalFor($user);
        $this->entry($user, $a, 'deposit', '100');
        [$out] = app(SavingsLedger::class)->transfer($user, $a->id, $b->id, '60', now()->toDateString());

        $this->actingAs($user)->post("/transactions/{$out->id}/void", ['reason' => 'Wrong goal'])->assertSessionHas('success');

        $this->assertSame('100.00', $this->balanceOf($a));
        $this->assertSame('0.00', $this->balanceOf($b));
        $this->assertSame(2, SavingsTransaction::whereNotNull('voided_at')->count());
    }

    public function test_voiding_a_deposit_that_was_already_spent_is_blocked(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $deposit = $this->entry($user, $goal, 'deposit', '100');
        $this->entry($user, $goal, 'withdrawal', '80');

        $this->actingAs($user)->post("/transactions/{$deposit->id}/void", ['reason' => 'oops'])->assertSessionHasErrors('reason');
        $this->assertNull($deposit->fresh()->voided_at);
        $this->assertSame('20.00', $this->balanceOf($goal));
    }

    public function test_correcting_an_entry_is_audited_and_rebalanced(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $tx = $this->entry($user, $goal, 'deposit', '100');

        $this->actingAs($user)->put("/transactions/{$tx->id}", [
            'goal_id' => $goal->id, 'amount' => '120.50', 'transaction_date' => now()->subDay()->toDateString(), 'note' => 'fixed',
        ])->assertSessionHas('success');

        $this->assertSame('120.50', $this->balanceOf($goal));
        $log = ActivityLog::where('action', 'transaction.updated')->firstOrFail();
        $this->assertSame('100.00', $log->properties['old']['amount']);
        $this->assertSame('120.50', $log->properties['new']['amount']);
    }

    public function test_correction_that_would_go_negative_is_rejected(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user);
        $tx = $this->entry($user, $goal, 'deposit', '100');
        $this->entry($user, $goal, 'withdrawal', '90');

        $this->actingAs($user)->put("/transactions/{$tx->id}", [
            'goal_id' => $goal->id, 'amount' => '50', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
        $this->assertSame('100.00', (string) $tx->fresh()->amount);
    }

    public function test_type_cannot_be_changed_by_editing(): void
    {
        $user = User::factory()->create();
        $tx = $this->entry($user, null, 'deposit', '100');

        $this->actingAs($user)->put("/transactions/{$tx->id}", [
            'type' => 'withdrawal', 'goal_id' => null, 'amount' => '100', 'transaction_date' => now()->toDateString(),
        ])->assertSessionHasErrors('type');
        $this->assertSame('deposit', $tx->fresh()->type);
    }

    public function test_transaction_index_filters_and_paginates(): void
    {
        $user = User::factory()->create();
        $goal = $this->goalFor($user, ['name' => 'Laptop']);
        for ($i = 0; $i < 25; $i++) {
            $this->entry($user, $goal, 'deposit', '10', now()->subDays($i)->toDateString());
        }
        $this->entry($user, null, 'deposit', '7', now()->subDays(100)->toDateString());

        $this->actingAs($user)->get('/transactions')->assertOk()->assertViewHas('transactions', fn ($p) => $p->total() === 26 && $p->count() === 20);
        $this->get('/transactions?goal=none')->assertViewHas('transactions', fn ($p) => $p->total() === 1);
        $this->get('/transactions?from='.now()->subDays(4)->toDateString())->assertViewHas('transactions', fn ($p) => $p->total() === 5);
        $this->get('/transactions?type=withdrawal')->assertViewHas('transactions', fn ($p) => $p->total() === 0);
        $this->get('/transactions?from=not-a-date')->assertSessionHasErrors('from');
    }
}
