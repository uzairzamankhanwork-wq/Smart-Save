<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmountRules;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Services\FinanceSummary;
use App\Services\GoalAlerts;
use App\Support\Csv;
use App\Support\Idempotency;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spending tracker. Expenses never touch savings balances. Taking money out
 * of a goal is recorded separately as a withdrawal.
 */
class ExpenseController extends Controller
{
    public function index(Request $request, FinanceSummary $summary): View
    {
        $f = $this->filters($request);
        $query = $this->filteredQuery($request, $f);

        $month = now();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        return view('expenses.index', [
            'expenses' => (clone $query)->orderByDesc('expense_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $f,
            'filteredTotal' => Money::normalize((clone $query)->sum('amount')),
            'monthTotal' => $summary->expensesBetween($request->user(), $monthStart, $monthEnd),
            'byCategory' => $summary->expensesByCategory($request->user(), $monthStart, $monthEnd),
            'budget' => $summary->budgetStatus($request->user(), $month),
        ]);
    }

    public function create(): View
    {
        return view('expenses.form', ['expense' => new Expense(['expense_date' => now(), 'category' => 'food'])]);
    }

    public function store(Request $request, GoalAlerts $alerts): RedirectResponse
    {
        $data = $this->validated($request, true);
        $user = $request->user();

        $expense = Idempotency::once($request, function () use ($data, $user) {
            if (! empty($data['idempotency_key']) && $user->expenses()->where('idempotency_key', $data['idempotency_key'])->exists()) {
                return null;
            }
            $expense = new Expense($data);
            $expense->user_id = $user->id;
            $expense->save();
            ActivityLog::record('expense.created', $expense, ['amount' => (string) $expense->amount, 'category' => $expense->category]);

            return $expense;
        });

        if (! $expense) {
            return redirect()->route('expenses.index')->with('info', 'That expense was already recorded.');
        }

        $alerts->checkBudget($user, Carbon::parse($expense->expense_date));

        return redirect()->route('expenses.index')->with('success', "Expense of {$user->money($expense->amount)} recorded.");
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.form', ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense, GoalAlerts $alerts): RedirectResponse
    {
        $this->authorize('update', $expense);
        $data = $this->validated($request, false);

        $old = $expense->only(['amount', 'category', 'expense_date', 'note']);
        $expense->fill($data)->save();
        ActivityLog::record('expense.updated', $expense, [
            'old' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v, $old),
            'changed' => array_keys($expense->getChanges()),
        ]);
        $alerts->checkBudget($request->user(), Carbon::parse($expense->expense_date));

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        ActivityLog::record('expense.deleted', $expense, [
            'amount' => (string) $expense->amount, 'category' => $expense->category, 'date' => $expense->expense_date->toDateString(), 'note' => $expense->note,
        ]);
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $rows = $this->filteredQuery($request, $this->filters($request))
            ->orderBy('expense_date')->orderBy('id')->lazy(500)
            ->map(fn (Expense $e) => [$e->id, $e->expense_date->toDateString(), $e->categoryLabel(), (string) $e->amount, $user->currency, $e->note]);

        return Csv::download('smartsave-expenses-'.now()->format('Y-m-d').'.csv', ['ID', 'Date', 'Category', 'Amount', 'Currency', 'Note'], $rows);
    }

    // -------------------------------------------------------------------------

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'amount' => AmountRules::required(),
            'category' => ['required', Rule::in(array_keys(config('smartsave.expense_categories')))],
            'expense_date' => AmountRules::pastDate(),
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => $creating ? ['nullable', 'uuid'] : ['prohibited'],
        ]);
    }

    private function filters(Request $request): array
    {
        $v = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', Rule::in(array_keys(config('smartsave.expense_categories')))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return array_merge(['from' => null, 'to' => null, 'category' => null, 'q' => null], array_filter($v));
    }

    private function filteredQuery(Request $request, array $f): Builder
    {
        return Expense::query()
            ->where('user_id', $request->user()->id)
            ->when($f['from'], fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->when($f['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($f['q'], fn ($q, $v) => $q->where('note', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%'));
    }
}
