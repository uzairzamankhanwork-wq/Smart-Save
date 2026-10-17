<?php

namespace App\Http\Controllers;

use App\Http\Requests\AmountRules;
use App\Services\SavingsLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Moves money between goals (or to/from unallocated savings) without changing total savings. */
class TransferController extends Controller
{
    public function create(Request $request, SavingsLedger $ledger): View
    {
        return view('transactions.transfer', [
            'goals' => $request->user()->goals()->select('savings_goals.*')->withBalance()->where('status', '!=', 'archived')->orderBy('name')->get(),
            'unallocated' => $ledger->bucketBalance($request->user(), null),
            'from' => $request->integer('from') ?: null,
        ]);
    }

    public function store(Request $request, SavingsLedger $ledger): RedirectResponse
    {
        $owned = Rule::exists('savings_goals', 'id')->where('user_id', $request->user()->id);
        $data = $request->validate([
            'from_goal_id' => ['nullable', 'integer', $owned],
            'to_goal_id' => ['nullable', 'integer', $owned],
            'amount' => AmountRules::required(),
            'transaction_date' => AmountRules::pastDate(),
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'uuid'],
        ]);

        [$out] = $ledger->transfer(
            $request->user(),
            isset($data['from_goal_id']) ? (int) $data['from_goal_id'] : null,
            isset($data['to_goal_id']) ? (int) $data['to_goal_id'] : null,
            $data['amount'], $data['transaction_date'], $data['note'] ?? null, $data['idempotency_key'] ?? null,
        );

        if (! $out->wasRecentlyCreated) {
            return redirect()->route('transactions.index')->with('info', 'That transfer was already recorded.');
        }

        return redirect()->route('transactions.index')->with('success', "Moved {$request->user()->money($data['amount'])}. Your total savings are unchanged.");
    }
}
