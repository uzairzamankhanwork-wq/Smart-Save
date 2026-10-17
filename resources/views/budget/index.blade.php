@php
    $u = auth()->user();
    $s = $status;
    $plan = $s['budget'];
@endphp
<x-layouts.app title="Budget & plan">
    <x-page-header title="Budget & savings plan" subtitle="Set monthly targets and see how you're tracking for {{ now()->format('F Y') }}." />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-2">
                {{-- Savings target --}}
                <div class="card card-pad">
                    <div class="flex items-center gap-3">
                        <x-goal-icon icon="arrow-trending" color="emerald" size="sm" />
                        <h3 class="font-semibold">Savings target</h3>
                    </div>
                    @if ($s['savings_target'] !== null)
                        <p class="mt-4 text-3xl font-bold tabular-nums">{{ $s['savings_percent'] }}%</p>
                        <x-progress :percent="$s['savings_percent']" color="emerald" label="Monthly savings target progress" class="mt-3" />
                        <dl class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Target</dt><dd class="font-semibold tabular-nums">{{ $u->money($s['savings_target']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Saved (net)</dt><dd class="font-semibold tabular-nums">{{ $u->money($s['saved']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Still to save</dt><dd class="font-semibold tabular-nums">{{ $u->money($s['savings_remaining']) }}</dd></div>
                            @if (\App\Support\Money::compare($s['savings_remaining'], '0') > 0)
                                <div class="flex justify-between"><dt class="text-slate-500">Per day for {{ $daysLeft }} days</dt><dd class="font-semibold tabular-nums">{{ $u->money(\App\Support\Money::divideUp($s['savings_remaining'], $daysLeft)) }}</dd></div>
                            @endif
                        </dl>
                        @if (\App\Support\Money::compare($s['savings_remaining'], '0') === 0)
                            <x-alert tone="success" icon="trophy" class="mt-4">Monthly target reached. Nicely done!</x-alert>
                        @endif
                    @else
                        <p class="mt-4 text-sm text-slate-500">Set a monthly savings target to track progress here. You've saved <span class="font-semibold">{{ $u->money($s['saved']) }}</span> (net) so far this month.</p>
                    @endif
                </div>

                {{-- Expense budget --}}
                <div class="card card-pad">
                    <div class="flex items-center gap-3">
                        <x-goal-icon icon="receipt" color="amber" size="sm" />
                        <h3 class="font-semibold">Spending budget</h3>
                    </div>
                    @if ($s['expense_budget'] !== null)
                        <p @class(['mt-4 text-3xl font-bold tabular-nums', 'text-rose-600 dark:text-rose-400' => $s['over_budget']])>{{ $s['budget_percent'] }}%</p>
                        <x-progress :percent="$s['budget_percent']" :color="$s['over_budget'] ? 'rose' : ($s['budget_percent'] >= 80 ? 'amber' : 'sky')" label="Budget used" class="mt-3" />
                        <dl class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Budget</dt><dd class="font-semibold tabular-nums">{{ $u->money($s['expense_budget']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Spent</dt><dd class="font-semibold tabular-nums">{{ $u->money($s['spent']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">{{ $s['over_budget'] ? 'Over by' : 'Left' }}</dt><dd @class(['font-semibold tabular-nums', 'text-rose-600' => $s['over_budget']])>{{ $u->money(ltrim($s['budget_left'], '-')) }}</dd></div>
                        </dl>
                        @if ($s['over_budget'])
                            <x-alert tone="danger" class="mt-4">You've gone over this month's spending budget.</x-alert>
                        @elseif ($s['budget_percent'] >= 80)
                            <x-alert tone="warning" class="mt-4">You've used most of this month's budget.</x-alert>
                        @endif
                    @else
                        <p class="mt-4 text-sm text-slate-500">Optional. Set a spending budget to get a warning before you overspend. You've spent <span class="font-semibold">{{ $u->money($s['spent']) }}</span> this month.</p>
                    @endif
                </div>
            </div>

            {{-- Per-goal plan --}}
            <div class="card overflow-hidden">
                <div class="px-5 pt-5 sm:px-6">
                    <h3 class="font-semibold">Goal contribution plan</h3>
                    <p class="text-sm text-slate-500">What each active goal needs to stay on schedule, compared with what you planned.</p>
                </div>
                @if ($goals->isEmpty())
                    <x-empty-state icon="target" title="No active goals" class="py-8"><a href="{{ route('goals.create') }}" class="btn btn-primary btn-sm">Create a goal</a></x-empty-state>
                @else
                    <div class="table-wrap mt-3">
                        <table class="table">
                            <thead><tr><th>Goal</th><th class="text-right">Needed / month</th><th class="text-right">Your plan</th><th class="hidden text-right md:table-cell">Suggested schedule</th></tr></thead>
                            <tbody>
                                @foreach ($goals as $row)
                                    @php $g = $row['goal']; $sch = $row['schedule']; @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('goals.show', $g) }}" class="flex items-center gap-3">
                                                <x-goal-icon :goal="$g" size="sm" />
                                                <span class="min-w-0"><span class="block truncate font-medium">{{ $g->name }}</span>
                                                <span class="block text-xs text-slate-500">{{ $g->percent() }}% · {{ $g->target_date ? 'due '.$g->target_date->format('M Y') : 'no deadline' }}</span></span>
                                            </a>
                                        </td>
                                        <td class="text-right font-semibold tabular-nums">
                                            @if ($g->isTargetReached()) <span class="text-emerald-600">Funded</span>
                                            @elseif ($row['needed'] === null) <span class="font-normal text-slate-400">No deadline</span>
                                            @else {{ $u->money($row['needed']) }} @endif
                                        </td>
                                        <td class="text-right tabular-nums">{{ $g->planned_contribution ? $u->money($g->planned_contribution).' '.strtolower(config('smartsave.frequencies.'.$g->contribution_frequency)) : '—' }}</td>
                                        <td class="hidden text-right text-sm md:table-cell">
                                            @if ($sch['per_period'])
                                                {{ $u->money($sch['per_period']) }} {{ strtolower(config('smartsave.frequencies.'.$sch['frequency'])) }} × {{ $sch['periods'] }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="font-semibold"><td class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">Total needed per month</td><td class="border-t border-slate-200 px-4 py-3 text-right tabular-nums dark:border-slate-700">{{ $u->money($neededTotal) }}</td><td colspan="2" class="border-t border-slate-200 dark:border-slate-700"></td></tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
                <p class="px-5 py-4 text-xs text-slate-400 sm:px-6">These are planning estimates based on the remaining amount and time, not guaranteed forecasts.</p>
            </div>
        </div>

        {{-- Settings --}}
        <form method="POST" action="{{ route('budget.update') }}" class="card card-pad h-fit space-y-5">
            @csrf @method('PUT')
            <div>
                <h3 class="font-semibold">Monthly plan</h3>
                <p class="text-sm text-slate-500">Applies from {{ now()->format('F Y') }} onward. Earlier months keep the plan they had.</p>
            </div>
            <x-field name="savings_target" label="Monthly savings target" :hint="$neededTotal !== '0.00' ? 'Your goals need about '.$u->money($neededTotal).' per month.' : null">
                <x-money-input name="savings_target" :value="$plan?->savings_target" />
            </x-field>
            <x-field name="expense_budget" label="Monthly spending budget" hint="Optional. Leave empty for no limit.">
                <x-money-input name="expense_budget" :value="$plan?->expense_budget" />
            </x-field>
            <x-field name="savings_frequency" label="How often you save">
                <select id="savings_frequency" name="savings_frequency" class="input">
                    @foreach (config('smartsave.frequencies') as $k => $l)
                        <option value="{{ $k }}" @selected(old('savings_frequency', $plan?->savings_frequency ?? 'monthly') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </x-field>
            @if ($plan && $plan->savings_frequency !== 'monthly' && $s['savings_target'] !== null)
                <p class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60">
                    That's about <span class="font-semibold">{{ $u->money(\App\Support\Money::divideUp($s['savings_target'], $plan->savings_frequency === 'weekly' ? 4 : 2)) }}</span> {{ $plan->savings_frequency === 'weekly' ? 'per week' : 'every two weeks' }}.
                </p>
            @endif
            <button type="submit" class="btn btn-primary w-full">Save plan</button>
        </form>
    </div>
</x-layouts.app>
