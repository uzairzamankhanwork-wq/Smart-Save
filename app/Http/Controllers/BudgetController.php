<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmountRules;
use App\Models\ActivityLog;
use App\Models\MonthlyBudget;
use App\Models\SavingsGoal;
use App\Services\FinanceSummary;
use App\Support\GoalPlanner;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request, FinanceSummary $summary): View
    {
        $user = $request->user();
        $status = $summary->budgetStatus($user, now());

        $goals = $user->goals()->select('savings_goals.*')->withBalance()
            ->where('status', SavingsGoal::ACTIVE)->orderBy('target_date')->get()
            ->map(fn ($g) => [
                'goal' => $g,
                'needed' => GoalPlanner::monthlyNeeded($g),
                'schedule' => GoalPlanner::schedule($g),
            ]);

        $plannedTotal = Money::add('0', ...$goals->map(fn ($r) => (string) ($r['goal']->planned_contribution ?? '0'))->all());
        $neededTotal = Money::add('0', ...$goals->map(fn ($r) => $r['needed'] ?? '0')->all());

        // Days left in the month, to spread the remaining target.
        $daysLeft = now()->diffInDays(now()->copy()->endOfMonth()) + 1;

        return view('budget.index', compact('status', 'goals', 'plannedTotal', 'neededTotal', 'daysLeft'));
    }

    /** Sets the plan from this month onward (earlier months keep their own plan). */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'savings_target' => AmountRules::optional(),
            'expense_budget' => AmountRules::optional(),
            'savings_frequency' => ['required', Rule::in(array_keys(config('smartsave.frequencies')))],
        ]);

        $month = now()->startOfMonth()->toDateString();
        $budget = $request->user()->monthlyBudgets()->whereDate('month', $month)->first() ?? new MonthlyBudget;
        $budget->user_id = $request->user()->id;
        $budget->fill([
            'month' => $month,
            'savings_target' => $data['savings_target'] ?? null,
            'expense_budget' => $data['expense_budget'] ?? null,
            'savings_frequency' => $data['savings_frequency'],
        ])->save();

        ActivityLog::record('budget.updated', $budget, ['month' => $month, 'savings_target' => $data['savings_target'] ?? null, 'expense_budget' => $data['expense_budget'] ?? null]);

        return back()->with('success', 'Monthly plan saved.');
    }
}
