@php
    $u = auth()->user();
    $cats = config('smartsave.expense_categories');
@endphp
{{-- Summary --}}
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card card-pad"><p class="text-xs text-slate-500">Contributed</p><p class="mt-1 text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $u->money($totals['contributed']) }}</p></div>
    <div class="card card-pad"><p class="text-xs text-slate-500">Withdrawn</p><p class="mt-1 text-lg font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ $u->money($totals['withdrawn']) }}</p></div>
    <div class="card card-pad"><p class="text-xs text-slate-500">Net saved</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $u->money($totals['net']) }}</p></div>
    <div class="card card-pad"><p class="text-xs text-slate-500">Expenses</p><p class="mt-1 text-lg font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $u->money($expenseTotal) }}</p></div>
</div>
@if (\App\Support\Money::compare($totals['initial'], '0') > 0)
    <p class="mt-2 text-xs text-slate-500">Opening balances of {{ $u->money($totals['initial']) }} recorded in {{ $year }} are not counted as new savings.</p>
@endif

{{-- Monthly savings + budget performance --}}
<section class="card mt-6 overflow-hidden">
    <h3 class="px-5 pt-5 font-semibold sm:px-6">Monthly savings &amp; budget performance</h3>
    <div class="table-wrap mt-3">
        <table class="table">
            <thead><tr>
                <th>Month</th><th class="text-right">Contributed</th><th class="text-right">Withdrawn</th><th class="text-right">Net saved</th>
                <th class="text-right">Target</th><th class="text-right">Expenses</th><th class="text-right">Budget</th><th class="text-right">Total at month end</th>
            </tr></thead>
            <tbody>
                @foreach ($months as $m)
                    <tr>
                        <td class="font-medium whitespace-nowrap">{{ $m['month']->format('F') }}</td>
                        <td class="text-right tabular-nums">{{ $u->money($m['contributed'], false) }}</td>
                        <td class="text-right tabular-nums">{{ $u->money($m['withdrawn'], false) }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ $u->money($m['net'], false) }}</td>
                        <td class="text-right whitespace-nowrap tabular-nums">
                            @if ($m['savings_target'] !== null)
                                {{ $u->money($m['savings_target'], false) }}
                                <span @class(['ml-1', 'text-emerald-600' => $m['target_met'], 'text-rose-500' => ! $m['target_met']]) title="{{ $m['target_met'] ? 'Target met' : 'Target missed' }}">{{ $m['target_met'] ? '✓' : '✗' }}</span>
                            @else <span class="text-slate-400">—</span> @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $u->money($m['expenses'], false) }}</td>
                        <td class="text-right whitespace-nowrap tabular-nums">
                            @if ($m['expense_budget'] !== null)
                                {{ $u->money($m['expense_budget'], false) }}
                                <span @class(['ml-1', 'text-emerald-600' => $m['within_budget'], 'text-rose-500' => ! $m['within_budget']]) title="{{ $m['within_budget'] ? 'Within budget' : 'Over budget' }}">{{ $m['within_budget'] ? '✓' : '✗' }}</span>
                            @else <span class="text-slate-400">—</span> @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $u->money($m['balance'], false) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="px-5 py-3 text-xs text-slate-400 sm:px-6">Amounts in {{ $u->currency }}. ✓ / ✗ show whether the monthly target was met and spending stayed within budget.</p>
</section>

{{-- Goal progress + contributions per goal --}}
<section class="card mt-6 overflow-hidden">
    <h3 class="px-5 pt-5 font-semibold sm:px-6">Goal progress &amp; activity in {{ $year }}</h3>
    @if ($goals->isEmpty())
        <p class="px-6 py-6 text-sm text-slate-500">No goals yet.</p>
    @else
        <div class="table-wrap mt-3">
            <table class="table">
                <thead><tr><th>Goal</th><th>Status</th><th class="text-right">In</th><th class="text-right">Out</th><th class="text-right">Saved</th><th class="text-right">Target</th><th class="text-right">Progress</th></tr></thead>
                <tbody>
                    @foreach ($goals as $g)
                        @php
                            $a = $perGoal[$g->id] ?? collect();
                            $in = \App\Support\Money::add($a['deposit'] ?? '0', $a['initial'] ?? '0', $a['transfer_in'] ?? '0');
                            $out = \App\Support\Money::add($a['withdrawal'] ?? '0', $a['transfer_out'] ?? '0');
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $g->name }}</td>
                            <td><span class="badge {{ \App\Support\Ui::status($g->status) }}">{{ $g->statusLabel() }}</span></td>
                            <td class="text-right tabular-nums">{{ $u->money($in, false) }}</td>
                            <td class="text-right tabular-nums">{{ $u->money($out, false) }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $u->money($g->balance(), false) }}</td>
                            <td class="text-right tabular-nums">{{ $u->money($g->target_amount, false) }}</td>
                            <td class="text-right tabular-nums">{{ $g->percent() }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-5 py-3 text-xs text-slate-400 sm:px-6">“In” includes contributions, opening balances and transfers in; “Out” includes withdrawals and transfers out.</p>
    @endif
</section>

{{-- Expense summary --}}
<section class="card mt-6 overflow-hidden">
    <h3 class="px-5 pt-5 font-semibold sm:px-6">Expense summary</h3>
    @if (! count($byCategory))
        <p class="px-6 py-6 text-sm text-slate-500">No expenses recorded in {{ $year }}.</p>
    @else
        <div class="table-wrap mt-3">
            <table class="table">
                <thead><tr><th>Category</th><th class="text-right">Total</th><th class="text-right">Share</th></tr></thead>
                <tbody>
                    @foreach ($byCategory as $cat => $total)
                        <tr>
                            <td>{{ $cats[$cat] ?? $cat }}</td>
                            <td class="text-right tabular-nums">{{ $u->money($total) }}</td>
                            <td class="text-right tabular-nums">{{ \App\Support\Money::percent($total, $expenseTotal) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
