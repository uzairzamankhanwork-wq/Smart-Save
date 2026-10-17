<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoalRequest;
use App\Models\ActivityLog;
use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Services\GoalAlerts;
use App\Services\SavingsLedger;
use App\Support\GoalPlanner;
use App\Support\Idempotency;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');
        $status = in_array($status, [...SavingsGoal::STATUSES, 'all'], true) ? $status : 'active';
        $sort = in_array($request->query('sort'), ['deadline', 'progress', 'name', 'newest', 'priority'], true) ? $request->query('sort') : 'priority';

        $goals = $request->user()->goals()->select('savings_goals.*')->withBalance()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->query('q')).'%'))
            ->get();

        $goals = match ($sort) {
            'deadline' => $goals->sortBy(fn ($g) => $g->target_date?->timestamp ?? PHP_INT_MAX),
            'progress' => $goals->sortByDesc(fn ($g) => $g->percent()),
            'name' => $goals->sortBy(fn ($g) => mb_strtolower($g->name)),
            'newest' => $goals->sortByDesc('id'),
            default => $goals->sortBy(fn ($g) => [array_search($g->priority, ['high', 'medium', 'low']), $g->target_date?->timestamp ?? PHP_INT_MAX]),
        };

        $counts = $request->user()->goals()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('goals.index', compact('goals', 'status', 'sort', 'counts'));
    }

    public function create(): View
    {
        return view('goals.form', ['goal' => new SavingsGoal([
            'category' => 'emergency', 'icon' => 'shield', 'color' => 'rose', 'priority' => 'medium', 'contribution_frequency' => 'monthly',
        ])]);
    }

    public function store(GoalRequest $request, SavingsLedger $ledger): RedirectResponse
    {
        $user = $request->user();

        $goal = Idempotency::once($request, fn () => DB::transaction(function () use ($request, $user, $ledger) {
            $goal = new SavingsGoal($request->goalData());
            $goal->user_id = $user->id;
            $goal->save();
            ActivityLog::record('goal.created', $goal, ['name' => $goal->name, 'target' => (string) $goal->target_amount]);

            // A starting amount is recorded once, as an opening-balance entry,
            // so it is counted exactly once in every total.
            if ($request->filled('starting_amount') && Money::compare($request->input('starting_amount'), '0') > 0) {
                $ledger->record($user, [
                    'type' => SavingsTransaction::INITIAL,
                    'goal_id' => $goal->id,
                    'amount' => $request->input('starting_amount'),
                    'transaction_date' => now()->toDateString(),
                    'note' => 'Starting amount',
                    'idempotency_key' => $request->input('idempotency_key'),
                ]);
            }

            return $goal;
        }));

        if (! $goal) {
            return redirect()->route('goals.index')->with('info', 'That goal was already created.');
        }

        return redirect()->route('goals.show', $goal)->with('success', "Goal “{$goal->name}” created.");
    }

    public function show(SavingsGoal $goal): View
    {
        $this->authorize('view', $goal);
        $goal = SavingsGoal::whereKey($goal->id)->select('savings_goals.*')->withBalance()->firstOrFail();

        $transactions = $goal->transactions()->latest('transaction_date')->latest('id')->paginate(15);

        return view('goals.show', [
            'goal' => $goal,
            'transactions' => $transactions,
            'monthlyNeeded' => GoalPlanner::monthlyNeeded($goal),
            'monthsLeft' => GoalPlanner::monthsLeft($goal),
            'rate' => GoalPlanner::recentMonthlyRate($goal),
            'estimate' => GoalPlanner::estimatedCompletion($goal),
            'warnings' => GoalPlanner::warnings($goal),
            'schedule' => GoalPlanner::schedule($goal),
        ]);
    }

    public function edit(SavingsGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('goals.form', ['goal' => $goal]);
    }

    public function update(GoalRequest $request, SavingsGoal $goal): RedirectResponse
    {
        if ($goal->status === SavingsGoal::ARCHIVED) {
            return back()->with('error', 'Restore this goal before editing it.');
        }

        $old = $goal->only(['name', 'target_amount', 'target_date', 'status']);
        $goal->fill($request->goalData())->save();
        ActivityLog::record('goal.updated', $goal, ['old' => array_map('strval', array_filter($old)), 'changed' => array_keys($goal->getChanges())]);

        return redirect()->route('goals.show', $goal)->with('success', 'Goal updated.');
    }

    /** Pause / resume / complete / archive / restore, within the allowed transitions. */
    public function updateStatus(Request $request, SavingsGoal $goal, GoalAlerts $alerts): RedirectResponse
    {
        $this->authorize('update', $goal);
        $data = $request->validate(['status' => ['required', Rule::in(SavingsGoal::STATUSES)]]);
        $new = $data['status'];

        $goal = SavingsGoal::whereKey($goal->id)->select('savings_goals.*')->withBalance()->firstOrFail();
        if (! in_array($new, $goal->allowedTransitions(), true)) {
            $reason = $new === SavingsGoal::COMPLETED && ! $goal->isTargetReached()
                ? 'A goal can be marked complete once its target amount has been saved.'
                : "A {$goal->status} goal can't be changed to {$new}.";

            return back()->with('error', $reason);
        }

        $old = $goal->status;
        $goal->status = $new;
        $goal->completed_at = $new === SavingsGoal::COMPLETED ? now() : ($new === SavingsGoal::ACTIVE ? null : $goal->completed_at);
        $goal->archived_at = $new === SavingsGoal::ARCHIVED ? now() : null;
        $goal->save();

        ActivityLog::record('goal.status_changed', $goal, ['from' => $old, 'to' => $new]);
        if ($new === SavingsGoal::COMPLETED) {
            $alerts->goalCompleted($goal);
        }

        $messages = [
            SavingsGoal::PAUSED => 'Goal paused.',
            SavingsGoal::ACTIVE => $old === SavingsGoal::ARCHIVED ? 'Goal restored.' : 'Goal is active again.',
            SavingsGoal::COMPLETED => '🎉 Goal completed. Well done!',
            SavingsGoal::ARCHIVED => 'Goal archived. Its history is kept and its balance still counts in your total savings.',
        ];

        return back()->with('success', $messages[$new]);
    }

    /** Only goals with no ledger history can be deleted; others must be archived. */
    public function destroy(SavingsGoal $goal): RedirectResponse
    {
        $this->authorize('delete', $goal);

        if ($goal->transactions()->exists()) {
            return back()->with('error', 'This goal has transaction history, so it can only be archived, not deleted.');
        }

        ActivityLog::record('goal.deleted', $goal, ['name' => $goal->name]);
        $goal->delete();

        return redirect()->route('goals.index')->with('success', 'Goal deleted.');
    }
}
