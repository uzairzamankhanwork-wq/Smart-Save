<?php

namespace App\Services;

use App\Models\MonthlyBudget;
use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Read-only figures for the dashboard, budgets and reports. All derived from the
 * ledger with the same definitions everywhere:
 *
 *  - contributed  = deposits                      (new money saved)
 *  - withdrawn    = withdrawals                   (money taken out of savings)
 *  - net saved    = contributed - withdrawn
 *  - opening balances and transfers are excluded from period figures: opening
 *    balances are not "saved this month", and transfers only move money.
 *  - total savings = signed sum of every non-voided entry (all goals + unallocated).
 *
 * Month grouping is done in PHP so the SQL works on both MySQL and SQLite.
 */
class FinanceSummary
{
    public function __construct(private SavingsLedger $ledger) {}

    /** @return array{contributed: string, withdrawn: string, net: string, initial: string} */
    public function savingsBetween(User $user, Carbon $from, Carbon $to): array
    {
        $rows = $user->savingsTransactions()->whereNull('voided_at')
            ->whereIn('type', [SavingsTransaction::DEPOSIT, SavingsTransaction::WITHDRAWAL, SavingsTransaction::INITIAL])
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $in = Money::normalize($rows[SavingsTransaction::DEPOSIT] ?? 0);
        $out = Money::normalize($rows[SavingsTransaction::WITHDRAWAL] ?? 0);

        return [
            'contributed' => $in,
            'withdrawn' => $out,
            'net' => Money::sub($in, $out),
            'initial' => Money::normalize($rows[SavingsTransaction::INITIAL] ?? 0),
        ];
    }

    public function expensesBetween(User $user, Carbon $from, Carbon $to): string
    {
        return Money::normalize($user->expenses()
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->sum('amount'));
    }

    /** @return array<string, string> category => total, largest first */
    public function expensesByCategory(User $user, Carbon $from, Carbon $to): array
    {
        return $user->expenses()
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn ($t) => Money::normalize($t))
            ->all();
    }

    /**
     * Month-by-month figures, oldest first.
     *
     * @return array<int, array{month: Carbon, label: string, contributed: string, withdrawn: string, net: string, expenses: string, balance: string}>
     */
    public function monthlySeries(User $user, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfMonth();
        $to = $to->copy()->endOfMonth();

        $series = [];
        for ($m = $from->copy(); $m->lte($to); $m->addMonthNoOverflow()) {
            $series[$m->format('Y-m')] = [
                'month' => $m->copy(), 'label' => $m->format('M Y'),
                'contributed' => '0.00', 'withdrawn' => '0.00', 'net' => '0.00', 'expenses' => '0.00', 'balance' => '0.00',
            ];
        }

        $txs = $user->savingsTransactions()->whereNull('voided_at')
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->get(['type', 'amount', 'transaction_date']);

        // Running balance starts with everything before the window.
        $running = '0.00';
        foreach ($txs as $tx) {
            if ($tx->transaction_date->lt($from)) {
                $running = Money::add($running, $tx->signedAmount());

                continue;
            }
            $key = $tx->transaction_date->format('Y-m');
            if ($tx->type === SavingsTransaction::DEPOSIT) {
                $series[$key]['contributed'] = Money::add($series[$key]['contributed'], (string) $tx->amount);
            } elseif ($tx->type === SavingsTransaction::WITHDRAWAL) {
                $series[$key]['withdrawn'] = Money::add($series[$key]['withdrawn'], (string) $tx->amount);
            }
            $series[$key]['delta'] = Money::add($series[$key]['delta'] ?? '0', $tx->signedAmount());
        }

        $expenses = $user->expenses()
            ->whereDate('expense_date', '>=', $from->toDateString())
            ->whereDate('expense_date', '<=', $to->toDateString())
            ->get(['amount', 'expense_date']);
        foreach ($expenses as $e) {
            $key = $e->expense_date->format('Y-m');
            $series[$key]['expenses'] = Money::add($series[$key]['expenses'], (string) $e->amount);
        }

        foreach ($series as $key => $row) {
            $running = Money::add($running, $row['delta'] ?? '0');
            $series[$key]['balance'] = $running;
            $series[$key]['net'] = Money::sub($row['contributed'], $row['withdrawn']);
            unset($series[$key]['delta']);
        }

        return array_values($series);
    }

    /**
     * Plan vs actual for one month.
     *
     * @return array{budget: ?MonthlyBudget, savings_target: ?string, saved: string, savings_remaining: ?string, savings_percent: ?float, expense_budget: ?string, spent: string, budget_left: ?string, budget_percent: ?float, over_budget: bool}
     */
    public function budgetStatus(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $budget = MonthlyBudget::forMonth($user, $start);

        $saved = $this->savingsBetween($user, $start, $end)['net'];
        $spent = $this->expensesBetween($user, $start, $end);

        $target = $budget?->savings_target !== null ? (string) $budget->savings_target : null;
        $limit = $budget?->expense_budget !== null ? (string) $budget->expense_budget : null;

        return [
            'budget' => $budget,
            'savings_target' => $target,
            'saved' => $saved,
            'savings_remaining' => $target !== null ? Money::max(Money::sub($target, $saved), '0') : null,
            'savings_percent' => $target !== null ? Money::percent($saved, $target) : null,
            'expense_budget' => $limit,
            'spent' => $spent,
            'budget_left' => $limit !== null ? Money::sub($limit, $spent) : null,
            // Utilization is not capped so "125%" can be shown when over budget.
            'budget_percent' => $limit !== null && Money::compare($limit, '0') > 0
                ? round((float) bcdiv(bcmul($spent, '100', 4), $limit, 4), 1) : null,
            'over_budget' => $limit !== null && Money::compare($spent, $limit) > 0,
        ];
    }

    /** Headline numbers for the dashboard. */
    public function overview(User $user, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy();
        $goals = $user->goals()->select('savings_goals.*')->withBalance()->get();
        $active = $goals->where('status', SavingsGoal::ACTIVE);

        $allocatedActive = Money::add('0', ...$active->map(fn ($g) => $g->balance())->all());
        $targetActive = Money::add('0', ...$active->map(fn ($g) => (string) $g->target_amount)->all());
        // Progress counts at most the target per goal, so one over-funded goal can't hide another.
        $progressSaved = Money::add('0', ...$active->map(fn ($g) => Money::compare($g->balance(), (string) $g->target_amount) > 0 ? (string) $g->target_amount : $g->balance())->all());

        return [
            'total' => $this->ledger->totalBalance($user),
            'unallocated' => $this->ledger->bucketBalance($user, null),
            'month' => $this->savingsBetween($user, $today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'year' => $this->savingsBetween($user, $today->copy()->startOfYear(), $today->copy()->endOfYear()),
            'allocated_active' => $allocatedActive,
            'target_active' => $targetActive,
            'overall_percent' => Money::percent($progressSaved, $targetActive),
            'counts' => [
                'active' => $goals->where('status', SavingsGoal::ACTIVE)->count(),
                'paused' => $goals->where('status', SavingsGoal::PAUSED)->count(),
                'completed' => $goals->where('status', SavingsGoal::COMPLETED)->count(),
                'archived' => $goals->where('status', SavingsGoal::ARCHIVED)->count(),
            ],
            'goals' => $goals,
        ];
    }
}
