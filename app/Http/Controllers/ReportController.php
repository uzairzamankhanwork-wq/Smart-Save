<?php

namespace App\Http\Controllers;

use App\Models\MonthlyBudget;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Services\FinanceSummary;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Per-user yearly reports. Every query starts from the signed-in user. */
class ReportController extends Controller
{
    public function index(Request $request, FinanceSummary $summary): View
    {
        return view('reports.index', $this->build($request, $summary));
    }

    public function print(Request $request, FinanceSummary $summary): View
    {
        return view('reports.print', $this->build($request, $summary));
    }

    public function export(Request $request, FinanceSummary $summary): StreamedResponse
    {
        $data = $this->build($request, $summary);
        $rows = array_map(fn ($m) => [
            $m['month']->format('Y-m'), $m['contributed'], $m['withdrawn'], $m['net'], $m['savings_target'] ?? '',
            $m['expenses'], $m['expense_budget'] ?? '', $m['balance'],
        ], $data['months']);

        return Csv::download("smartsave-report-{$data['year']}.csv",
            ['Month', 'Contributed', 'Withdrawn', 'Net saved', 'Savings target', 'Expenses', 'Expense budget', 'Total savings at month end'], $rows);
    }

    private function build(Request $request, FinanceSummary $summary): array
    {
        $user = $request->user();
        $years = $this->availableYears($user);
        $year = (int) $request->query('year', now()->year);
        $year = in_array($year, $years, true) ? $year : now()->year;

        $from = Carbon::create($year, 1, 1)->startOfDay();
        // Don't show empty future months for the current year.
        $to = $year === now()->year ? now()->endOfMonth() : Carbon::create($year, 12, 31)->endOfDay();

        $months = $summary->monthlySeries($user, $from, $to);
        foreach ($months as &$m) {
            $plan = MonthlyBudget::forMonth($user, $m['month']);
            $m['savings_target'] = $plan?->savings_target !== null ? (string) $plan->savings_target : null;
            $m['expense_budget'] = $plan?->expense_budget !== null ? (string) $plan->expense_budget : null;
            $m['target_met'] = $m['savings_target'] !== null ? Money::compare($m['net'], $m['savings_target']) >= 0 : null;
            $m['within_budget'] = $m['expense_budget'] !== null ? Money::compare($m['expenses'], $m['expense_budget']) <= 0 : null;
        }
        unset($m);

        // Contributions / withdrawals / transfers per goal within the year.
        $perGoal = $user->savingsTransactions()->whereNull('voided_at')
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->selectRaw('goal_id, type, SUM(amount) as total')
            ->groupBy('goal_id', 'type')->get()
            ->groupBy(fn ($r) => $r->goal_id ?? 0)
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->type => Money::normalize($r->total)]));

        $goals = $user->goals()->select('savings_goals.*')->withBalance()->orderBy('status')->orderBy('name')->get();

        $totals = $summary->savingsBetween($user, $from, $to);

        return [
            'year' => $year,
            'years' => $years,
            'months' => $months,
            'goals' => $goals,
            'perGoal' => $perGoal,
            'totals' => $totals,
            'expenseTotal' => $summary->expensesBetween($user, $from, $to),
            'byCategory' => $summary->expensesByCategory($user, $from, $to),
            'types' => SavingsTransaction::LABELS,
            'chart' => [
                'labels' => array_map(fn ($m) => $m['month']->format('M'), $months),
                'balance' => array_map(fn ($m) => (float) $m['balance'], $months),
                'net' => array_map(fn ($m) => (float) $m['net'], $months),
                'expenses' => array_map(fn ($m) => (float) $m['expenses'], $months),
            ],
        ];
    }

    /** @return int[] years with any activity, plus the current year */
    private function availableYears(User $user): array
    {
        $dates = array_filter([
            $user->savingsTransactions()->min('transaction_date'),
            $user->expenses()->min('expense_date'),
        ]);
        $first = $dates ? Carbon::parse(min($dates))->year : now()->year;

        return range(now()->year, min($first, now()->year));
    }
}
