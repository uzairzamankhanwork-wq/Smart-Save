<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\SavingsTransaction;
use App\Services\SavingsLedger;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->filteredQuery($request, $filters);

        $totals = (clone $query)->whereNull('voided_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('initial','deposit') THEN amount ELSE 0 END), 0) as money_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as money_out")
            ->first();

        return view('transactions.index', [
            'transactions' => $query->with('goal:id,name,icon,color')->orderByDesc('transaction_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'goals' => $request->user()->goals()->orderBy('name')->get(['id', 'name']),
            'moneyIn' => Money::normalize($totals->money_in),
            'moneyOut' => Money::normalize($totals->money_out),
        ]);
    }

    public function create(Request $request): View
    {
        $type = in_array($request->query('type'), [SavingsTransaction::INITIAL, SavingsTransaction::DEPOSIT, SavingsTransaction::WITHDRAWAL], true)
            ? $request->query('type') : SavingsTransaction::DEPOSIT;

        return view('transactions.form', [
            'transaction' => new SavingsTransaction(['type' => $type, 'goal_id' => $request->integer('goal') ?: null, 'transaction_date' => now()]),
            'goals' => $this->selectableGoals($request),
        ]);
    }

    public function store(TransactionRequest $request, SavingsLedger $ledger): RedirectResponse
    {
        $tx = $ledger->record($request->user(), $request->validated());

        if (! $tx->wasRecentlyCreated) {
            return $this->redirectAfter($tx)->with('info', 'That entry was already recorded, so it was not added twice.');
        }

        $verb = $tx->isNegative() ? 'Withdrawal of' : 'Saved';

        return $this->redirectAfter($tx)->with('success', "{$verb} {$request->user()->money($tx->amount)} recorded.");
    }

    public function edit(Request $request, SavingsTransaction $transaction): View
    {
        $this->authorize('update', $transaction);
        abort_if($transaction->isVoided(), 404);

        return view('transactions.form', [
            'transaction' => $transaction,
            'goals' => $this->selectableGoals($request, $transaction->goal_id),
        ]);
    }

    public function update(TransactionRequest $request, SavingsTransaction $transaction, SavingsLedger $ledger): RedirectResponse
    {
        $ledger->update($transaction, $request->validated());

        return $this->redirectAfter($transaction)->with('success', 'Entry corrected. The change was saved to your audit trail.');
    }

    public function void(Request $request, SavingsTransaction $transaction, SavingsLedger $ledger): RedirectResponse
    {
        $this->authorize('update', $transaction);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $ledger->void($transaction, $data['reason']);

        return back()->with('success', $transaction->isTransfer() ? 'Transfer reversed (both sides).' : 'Entry reversed. It no longer counts toward any balance.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $rows = $this->filteredQuery($request, $this->filters($request))
            ->with('goal:id,name')->orderBy('transaction_date')->orderBy('id')->lazy(500)
            ->map(fn (SavingsTransaction $t) => [
                $t->id, $t->transaction_date->toDateString(), $t->label(), $t->goal?->name ?? 'Unallocated',
                $t->signedAmount(), $user->currency, $t->note, $t->isVoided() ? 'Voided: '.$t->void_reason : 'Active',
            ]);

        return Csv::download('smartsave-transactions-'.now()->format('Y-m-d').'.csv',
            ['ID', 'Date', 'Type', 'Goal', 'Amount', 'Currency', 'Note', 'Status'], $rows);
    }

    // -------------------------------------------------------------------------

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'goal' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', Rule::in(SavingsTransaction::TYPES)],
            'show' => ['nullable', Rule::in(['active', 'voided', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return array_merge(['from' => null, 'to' => null, 'goal' => null, 'type' => null, 'show' => 'active', 'q' => null], array_filter($validated));
    }

    /** Always scoped to the current user before any filter is applied. */
    private function filteredQuery(Request $request, array $f): Builder
    {
        return SavingsTransaction::query()
            ->where('user_id', $request->user()->id)
            ->when($f['from'], fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($f['to'], fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->when($f['goal'] === 'none', fn ($q) => $q->whereNull('goal_id'))
            ->when($f['goal'] && ctype_digit((string) $f['goal']), fn ($q) => $q->where('goal_id', (int) $f['goal']))
            ->when($f['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($f['show'] === 'active', fn ($q) => $q->whereNull('voided_at'))
            ->when($f['show'] === 'voided', fn ($q) => $q->whereNotNull('voided_at'))
            ->when($f['q'], fn ($q, $v) => $q->where('note', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%'));
    }

    private function selectableGoals(Request $request, ?int $include = null)
    {
        return $request->user()->goals()->select('savings_goals.*')->withBalance()
            ->where(fn ($q) => $q->where('status', '!=', 'archived')->when($include, fn ($q) => $q->orWhere('savings_goals.id', $include)))
            ->orderBy('name')->get();
    }

    private function redirectAfter(SavingsTransaction $tx): RedirectResponse
    {
        return $tx->goal_id ? redirect()->route('goals.show', $tx->goal_id) : redirect()->route('transactions.index');
    }
}
