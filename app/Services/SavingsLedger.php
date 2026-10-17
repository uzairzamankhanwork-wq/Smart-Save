<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every write that changes a savings balance goes through this class.
 *
 * Rules:
 *  - Each operation runs in a DB transaction and first locks the user's row,
 *    so concurrent requests from the same user are serialized.
 *  - No "bucket" (a goal, or the unallocated pool where goal_id is null) may end
 *    with a negative balance.
 *  - Money can only go into active/paused goals; it can come out of any
 *    non-archived goal. Archived goals are read-only.
 *  - Rows are never deleted. Corrections are audited edits or voids.
 *  - A repeated idempotency key returns the original row instead of a duplicate.
 */
class SavingsLedger
{
    public function __construct(private GoalAlerts $alerts) {}

    /** Record an opening balance, contribution or withdrawal. */
    public function record(User $user, array $data): SavingsTransaction
    {
        $before = null;

        $tx = DB::transaction(function () use ($user, $data, &$before) {
            $this->lockUser($user);
            if ($existing = $this->findByKey($user, $data['idempotency_key'] ?? null)) {
                return $existing;
            }

            $goal = $this->ownedGoal($user, $data['goal_id'] ?? null);
            $type = $data['type'];
            $before = $goal?->percent();

            if ($goal) {
                $this->assertGoalAccepts($goal, $type);
            }

            $tx = new SavingsTransaction([
                'goal_id' => $goal?->id,
                'type' => $type,
                'amount' => Money::normalize($data['amount']),
                'transaction_date' => $data['transaction_date'],
                'note' => $data['note'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
            $tx->user_id = $user->id;
            $tx->save();

            $this->assertNotNegative($user, $goal?->id);

            ActivityLog::record('transaction.created', $tx, [
                'type' => $type, 'amount' => $tx->amount, 'goal_id' => $tx->goal_id, 'date' => $tx->transaction_date->toDateString(),
            ], $user->id);

            return $tx;
        });

        if ($tx->wasRecentlyCreated && $tx->goal) {
            $this->alerts->afterBalanceChange($tx->goal->fresh(), $before ?? 0.0);
        }

        return $tx;
    }

    /**
     * Move money between two goals (or between a goal and unallocated savings).
     * Written as a balanced pair so total savings is unchanged.
     *
     * @return array{0: SavingsTransaction, 1: SavingsTransaction}
     */
    public function transfer(User $user, ?int $fromGoalId, ?int $toGoalId, string $amount, string $date, ?string $note = null, ?string $key = null): array
    {
        if ($fromGoalId === $toGoalId) {
            throw ValidationException::withMessages(['to_goal_id' => 'Choose two different places to move money between.']);
        }

        $toBefore = null;

        $pair = DB::transaction(function () use ($user, $fromGoalId, $toGoalId, $amount, $date, $note, $key, &$toBefore) {
            $this->lockUser($user);
            if ($existing = $this->findByKey($user, $key)) {
                return SavingsTransaction::where('transfer_group', $existing->transfer_group)->orderBy('id')->get()->all();
            }

            $from = $this->ownedGoal($user, $fromGoalId, 'from_goal_id');
            $to = $this->ownedGoal($user, $toGoalId, 'to_goal_id');
            if ($from) {
                $this->assertGoalAccepts($from, SavingsTransaction::TRANSFER_OUT, 'from_goal_id');
            }
            if ($to) {
                $this->assertGoalAccepts($to, SavingsTransaction::TRANSFER_IN, 'to_goal_id');
                $toBefore = $to->percent();
            }

            $group = (string) Str::uuid();
            $amount = Money::normalize($amount);

            $out = new SavingsTransaction([
                'goal_id' => $from?->id, 'type' => SavingsTransaction::TRANSFER_OUT, 'amount' => $amount,
                'transaction_date' => $date, 'note' => $note, 'transfer_group' => $group, 'idempotency_key' => $key,
            ]);
            $out->user_id = $user->id;
            $out->save();

            $in = new SavingsTransaction([
                'goal_id' => $to?->id, 'type' => SavingsTransaction::TRANSFER_IN, 'amount' => $amount,
                'transaction_date' => $date, 'note' => $note, 'transfer_group' => $group,
            ]);
            $in->user_id = $user->id;
            $in->save();

            $this->assertNotNegative($user, $from?->id, 'amount');

            ActivityLog::record('transfer.created', $out, [
                'amount' => $amount, 'from_goal_id' => $from?->id, 'to_goal_id' => $to?->id, 'transfer_group' => $group,
            ], $user->id);

            return [$out, $in];
        });

        if ($pair[1]->wasRecentlyCreated && $pair[1]->goal) {
            $this->alerts->afterBalanceChange($pair[1]->goal->fresh(), $toBefore ?? 0.0);
        }

        return $pair;
    }

    /**
     * Correct a non-transfer entry. Old and new values go to the audit log.
     * Transfers can only have their note/date changed (both legs together).
     */
    public function update(SavingsTransaction $tx, array $data): SavingsTransaction
    {
        return DB::transaction(function () use ($tx, $data) {
            $user = $tx->user;
            $this->lockUser($user);
            $tx->refresh();

            if ($tx->isVoided()) {
                throw ValidationException::withMessages(['amount' => 'A voided entry cannot be edited.']);
            }

            $old = $tx->only(['goal_id', 'type', 'amount', 'transaction_date', 'note']);

            if ($tx->isTransfer()) {
                SavingsTransaction::where('transfer_group', $tx->transfer_group)->each(function ($leg) use ($data) {
                    $leg->fill(['note' => $data['note'] ?? null, 'transaction_date' => $data['transaction_date']])->save();
                });
                $tx->refresh();
            } else {
                $oldGoalId = $tx->goal_id;
                if ($oldGoalId && $tx->goal->status === SavingsGoal::ARCHIVED) {
                    throw ValidationException::withMessages(['goal_id' => 'Entries on an archived goal cannot be changed. Restore the goal first.']);
                }
                $newGoal = $this->ownedGoal($user, $data['goal_id'] ?? null);
                if ($newGoal && $newGoal->id !== $oldGoalId) {
                    $this->assertGoalAccepts($newGoal, $tx->type);
                }

                $tx->fill([
                    'goal_id' => $newGoal?->id,
                    'amount' => Money::normalize($data['amount']),
                    'transaction_date' => $data['transaction_date'],
                    'note' => $data['note'] ?? null,
                ])->save();

                $this->assertNotNegative($user, $oldGoalId, 'amount');
                if ($newGoal?->id !== $oldGoalId) {
                    $this->assertNotNegative($user, $newGoal?->id, 'amount');
                }
            }

            ActivityLog::record('transaction.updated', $tx, [
                'old' => $this->stringify($old),
                'new' => $this->stringify($tx->only(['goal_id', 'type', 'amount', 'transaction_date', 'note'])),
            ], $user->id);

            return $tx;
        });
    }

    /** Reverse an entry (and its partner for transfers) without deleting it. */
    public function void(SavingsTransaction $tx, string $reason): void
    {
        DB::transaction(function () use ($tx, $reason) {
            $user = $tx->user;
            $this->lockUser($user);
            $tx->refresh();

            if ($tx->isVoided()) {
                return;
            }

            $legs = $tx->isTransfer()
                ? SavingsTransaction::where('transfer_group', $tx->transfer_group)->get()
                : collect([$tx]);

            foreach ($legs as $leg) {
                if ($leg->goal && $leg->goal->status === SavingsGoal::ARCHIVED) {
                    throw ValidationException::withMessages(['reason' => 'Entries on an archived goal cannot be reversed. Restore the goal first.']);
                }
                $leg->voided_at = now();
                $leg->void_reason = $reason;
                $leg->save();
            }

            foreach ($legs->pluck('goal_id')->unique() as $goalId) {
                $this->assertNotNegative($user, $goalId, 'reason');
            }

            ActivityLog::record('transaction.voided', $tx, [
                'reason' => $reason, 'amount' => (string) $tx->amount, 'type' => $tx->type, 'transfer_group' => $tx->transfer_group,
            ], $user->id);
        });
    }

    // ---- Queries --------------------------------------------------------------

    /** Total savings across goals + unallocated (transfers net to zero). */
    public function totalBalance(User $user): string
    {
        return Money::normalize(
            $user->savingsTransactions()->whereNull('voided_at')
                ->selectRaw('COALESCE(SUM('.SavingsTransaction::SIGNED_AMOUNT_SQL.'), 0) as t')->value('t')
        );
    }

    public function bucketBalance(User $user, ?int $goalId): string
    {
        $q = $user->savingsTransactions()->whereNull('voided_at');
        $goalId === null ? $q->whereNull('goal_id') : $q->where('goal_id', $goalId);

        return Money::normalize($q->selectRaw('COALESCE(SUM('.SavingsTransaction::SIGNED_AMOUNT_SQL.'), 0) as t')->value('t'));
    }

    // ---- Guards -------------------------------------------------------------

    private function lockUser(User $user): void
    {
        User::whereKey($user->id)->lockForUpdate()->first();
    }

    private function findByKey(User $user, ?string $key): ?SavingsTransaction
    {
        return $key ? SavingsTransaction::where('user_id', $user->id)->where('idempotency_key', $key)->first() : null;
    }

    /** Resolve a goal id strictly within the user's own goals (IDOR guard). */
    private function ownedGoal(User $user, mixed $goalId, string $field = 'goal_id'): ?SavingsGoal
    {
        if ($goalId === null || $goalId === '') {
            return null;
        }
        $goal = SavingsGoal::where('user_id', $user->id)->whereKey($goalId)->first();
        if (! $goal) {
            throw ValidationException::withMessages([$field => 'The selected goal is invalid.']);
        }

        return $goal;
    }

    private function assertGoalAccepts(SavingsGoal $goal, string $type, string $field = 'goal_id'): void
    {
        $negative = in_array($type, SavingsTransaction::NEGATIVE_TYPES, true);
        if (! $negative && ! $goal->canReceiveMoney()) {
            throw ValidationException::withMessages([$field => "“{$goal->name}” is {$goal->status}. Reopen it before adding money."]);
        }
        if ($negative && ! $goal->canReleaseMoney()) {
            throw ValidationException::withMessages([$field => "“{$goal->name}” is archived. Restore it before taking money out."]);
        }
    }

    private function assertNotNegative(User $user, ?int $goalId, string $field = 'amount'): void
    {
        $balance = $this->bucketBalance($user, $goalId);
        if (Money::isNegative($balance)) {
            $where = $goalId ? 'this goal' : 'your unallocated savings';
            // Throwing inside DB::transaction rolls back every write made above.
            throw ValidationException::withMessages([$field => "This would make the balance of {$where} negative ({$user->money($balance)})."]);
        }
    }

    private function stringify(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? null : (string) $v), $values);
    }
}
