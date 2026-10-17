@php
    $u = auth()->user();
    $symbol = config("smartsave.currencies.{$u->currency}.symbol", $u->currency);
    $o = $overview;
    $hasActivity = $o['goals']->isNotEmpty() || \App\Support\Money::compare($o['total'], '0') !== 0;
@endphp
<x-layouts.app title="Dashboard">
    <div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ now()->format('l, j F Y') }}</p>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                @php $h = now()->hour; @endphp
                Good {{ $h < 12 ? 'morning' : ($h < 17 ? 'afternoon' : 'evening') }}, {{ \Illuminate\Support\Str::before($u->name, ' ') }}
            </h2>
        </div>
    </div>

    @unless ($hasActivity)
        <div class="card mb-6 overflow-hidden">
            <x-empty-state icon="piggy-bank" title="Let's set up your first savings goal"
                           message="Create a goal (an emergency fund is a great start), then record what you've already saved as an opening balance.">
                <a href="{{ route('goals.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Create a goal</a>
                <a href="{{ route('transactions.create', ['type' => 'initial']) }}" class="btn btn-secondary">Record existing savings</a>
            </x-empty-state>
        </div>
    @endunless

    {{-- Hero + key stats --}}
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 via-emerald-600 to-teal-700 p-6 text-white shadow-lg shadow-brand-700/20 xl:col-span-2">
            <div class="pointer-events-none absolute -top-16 -right-16 size-64 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/3 size-64 rounded-full bg-teal-300/20 blur-3xl"></div>
            <div class="relative">
                <div class="flex items-center gap-2 text-sm font-medium text-emerald-50/90"><x-icon name="wallet" class="size-4" /> Total personal savings</div>
                <p class="mt-2 text-4xl font-bold tracking-tight tabular-nums sm:text-5xl">
                    <span class="text-2xl font-semibold text-emerald-100/80">{{ $symbol }}</span>
                    <span x-data="countUp({{ (float) $o['total'] }})" x-text="display">{{ number_format((float) $o['total'], 2) }}</span>
                </p>
                <p class="mt-1 text-sm text-emerald-50/80">
                    {{ $u->money($o['allocated_active']) }} in active goals
                    @if (\App\Support\Money::compare($o['unallocated'], '0') !== 0) · {{ $u->money($o['unallocated']) }} unallocated @endif
                </p>

                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium">Overall goal progress</span>
                        <span class="font-semibold tabular-nums">{{ $o['overall_percent'] }}%</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-label="Overall goal progress" aria-valuenow="{{ $o['overall_percent'] }}" aria-valuemin="0" aria-valuemax="100"
                         x-data="{ w: 0 }" x-init="requestAnimationFrame(() => w = {{ $o['overall_percent'] }})">
                        <div class="h-full rounded-full bg-white transition-[width] duration-1000 ease-out" :style="`width:${w}%`"></div>
                    </div>
                    <p class="mt-2 text-xs text-emerald-50/80">
                        @if ($o['counts']['active'])
                            {{ $u->money($o['allocated_active']) }} of {{ $u->money($o['target_active']) }} across {{ $o['counts']['active'] }} active {{ \Illuminate\Support\Str::plural('goal', $o['counts']['active']) }}. {{ \App\Support\Ui::encouragement($o['overall_percent']) }}
                        @else
                            No active goals yet.
                        @endif
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('transactions.create', ['type' => 'deposit']) }}" class="btn bg-white text-brand-700 shadow-sm hover:bg-emerald-50"><x-icon name="plus" class="size-4" /> Add savings</a>
                    <a href="{{ route('goals.create') }}" class="btn bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25"><x-icon name="target" class="size-4" /> New goal</a>
                    <a href="{{ route('expenses.create') }}" class="btn bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25"><x-icon name="receipt" class="size-4" /> Record expense</a>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <x-stat-card label="Saved this month" icon="arrow-trending" tone="emerald" :animate="$o['month']['net']" :currency="$symbol">
                {{ $u->money($o['month']['contributed']) }} in · {{ $u->money($o['month']['withdrawn']) }} out
            </x-stat-card>
            <x-stat-card label="Contributed this year" icon="calendar" tone="indigo" :animate="$o['year']['contributed']" :currency="$symbol">
                Net {{ $u->money($o['year']['net']) }} after withdrawals
            </x-stat-card>
        </div>
    </div>

    {{-- Goal counts + monthly plan --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Allocated to active goals" icon="target" tone="sky" :value="$u->money($o['allocated_active'])" />
        <div class="card card-pad">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Goals</p>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                @foreach (['active' => 'emerald', 'completed' => 'indigo', 'paused' => 'amber'] as $s => $c)
                    <a href="{{ route('goals.index', ['status' => $s]) }}" class="rounded-xl py-2 transition hover:bg-slate-50 dark:hover:bg-slate-800">
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $o['counts'][$s] }}</p>
                        <p class="truncate text-[11px] text-slate-500 capitalize">{{ $s }}</p>
                    </a>
                @endforeach
            </div>
        </div>
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Monthly savings target</p>
                <a href="{{ route('budget.index') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">{{ $budget['savings_target'] !== null ? 'Edit' : 'Set' }}</a>
            </div>
            @if ($budget['savings_target'] !== null)
                <p class="mt-2 text-lg font-bold tabular-nums">{{ $budget['savings_percent'] }}%</p>
                <x-progress :percent="$budget['savings_percent']" color="emerald" label="Monthly savings target" class="mt-2" />
                <p class="mt-2 text-xs text-slate-500">{{ $u->money($budget['savings_remaining']) }} left of {{ $u->money($budget['savings_target']) }}</p>
            @else
                <p class="mt-3 text-sm text-slate-500">No target set for this month.</p>
            @endif
        </div>
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Spending budget</p>
                <a href="{{ route('budget.index') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400">{{ $budget['expense_budget'] !== null ? 'Edit' : 'Set' }}</a>
            </div>
            @if ($budget['expense_budget'] !== null)
                <p @class(['mt-2 text-lg font-bold tabular-nums', 'text-rose-600 dark:text-rose-400' => $budget['over_budget']])>{{ $budget['budget_percent'] }}% used</p>
                <x-progress :percent="$budget['budget_percent']" :color="$budget['over_budget'] ? 'rose' : (($budget['budget_percent'] ?? 0) >= 80 ? 'amber' : 'sky')" label="Spending budget used" class="mt-2" />
                <p class="mt-2 text-xs text-slate-500">{{ $u->money($budget['spent']) }} of {{ $u->money($budget['expense_budget']) }}</p>
            @else
                <p class="mt-3 text-sm text-slate-500">Spent {{ $u->money($budget['spent']) }} this month.</p>
            @endif
        </div>
    </div>

    {{-- Charts --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card card-pad xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Monthly activity</h3>
                    <p class="text-xs text-slate-500">Net saved in period: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $u->money($rangeTotals['net']) }}</span></p>
                </div>
                <nav class="flex rounded-xl bg-slate-100 p-1 text-xs font-medium dark:bg-slate-800" aria-label="Date range">
                    @foreach (\App\Http\Controllers\DashboardController::RANGES as $key => $label)
                        <a href="{{ route('dashboard', ['range' => $key]) }}" @class([
                            'rounded-lg px-2.5 py-1.5 transition',
                            'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' => $range === $key,
                            'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300' => $range !== $key,
                        ]) @if($range === $key) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="relative mt-4 h-72" data-chart-wrap>
                <div class="skeleton absolute inset-0" data-skeleton></div>
                <canvas class="opacity-0 transition-opacity" data-chart="bar" data-currency="{{ $symbol }}" role="img" aria-label="Bar chart of monthly contributions, withdrawals and expenses"
                        data-chart-data="{{ json_encode(['labels' => $chart['labels'], 'datasets' => [
                            ['label' => 'Contributions', 'data' => $chart['contributed'], 'color' => '#10b981'],
                            ['label' => 'Withdrawals', 'data' => $chart['withdrawn'], 'color' => '#f43f5e'],
                            ['label' => 'Expenses', 'data' => $chart['expenses'], 'color' => '#f59e0b', 'type' => 'line'],
                        ]]) }}"></canvas>
            </div>
        </div>

        <div class="card card-pad">
            <h3 class="font-semibold text-slate-900 dark:text-white">Savings by goal</h3>
            @if (count($distribution['values']))
                <div class="relative mt-4 h-72" data-chart-wrap>
                    <div class="skeleton absolute inset-0 rounded-full" data-skeleton></div>
                    <canvas class="opacity-0 transition-opacity" data-chart="doughnut" data-currency="{{ $symbol }}" role="img" aria-label="Doughnut chart of savings by goal"
                            data-chart-data="{{ json_encode($distribution) }}"></canvas>
                </div>
            @else
                <x-empty-state icon="chart" title="Nothing to show yet" message="Your savings split by goal will appear here." class="py-10" />
            @endif
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        {{-- Top goals --}}
        <div class="card card-pad xl:col-span-2">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900 dark:text-white">Active goals</h3>
                <a href="{{ route('goals.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-400">View all</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @forelse ($topGoals as $g)
                    <a href="{{ route('goals.show', $g) }}" class="group rounded-xl border border-slate-100 p-4 transition hover:border-slate-200 hover:shadow-sm dark:border-slate-800 dark:hover:border-slate-700">
                        <div class="flex items-center gap-3">
                            <x-goal-icon :goal="$g" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold group-hover:text-brand-700 dark:group-hover:text-brand-300">{{ $g->name }}</p>
                                <p class="text-xs text-slate-500 tabular-nums">{{ $u->money($g->balance()) }} / {{ $u->money($g->target_amount) }}</p>
                            </div>
                            <span class="text-sm font-bold tabular-nums">{{ $g->percent() }}%</span>
                        </div>
                        <x-progress :percent="$g->percent()" :color="$g->color" :label="$g->name.' progress'" size="sm" class="mt-3" />
                    </a>
                @empty
                    <x-empty-state icon="target" title="No active goals" message="Create a goal to start tracking progress." class="col-span-full py-8">
                        <a href="{{ route('goals.create') }}" class="btn btn-primary btn-sm">New goal</a>
                    </x-empty-state>
                @endforelse
            </div>
        </div>

        {{-- Upcoming deadlines --}}
        <div class="card card-pad">
            <h3 class="font-semibold text-slate-900 dark:text-white">Upcoming deadlines</h3>
            <ul class="mt-4 space-y-3">
                @forelse ($upcoming as $g)
                    @php $days = (int) now()->startOfDay()->diffInDays($g->target_date, false); @endphp
                    <li>
                        <a href="{{ route('goals.show', $g) }}" class="flex items-center gap-3 rounded-xl p-2 -m-2 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                            <div @class(['grid size-11 shrink-0 place-items-center rounded-xl text-center leading-tight',
                                'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' => $days < 30,
                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $days >= 30])>
                                <span><span class="block text-[10px] font-semibold uppercase">{{ $g->target_date->format('M') }}</span><span class="block text-sm font-bold">{{ $g->target_date->format('j') }}</span></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $g->name }}</p>
                                <p class="text-xs text-slate-500">{{ $days < 0 ? 'Overdue' : ($days === 0 ? 'Due today' : "in {$days} days") }} · {{ $u->money($g->remaining()) }} to go</p>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">No upcoming deadlines.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Recent transactions --}}
    <div class="card mt-4 overflow-hidden">
        <div class="flex items-center justify-between px-5 pt-5 sm:px-6">
            <h3 class="font-semibold text-slate-900 dark:text-white">Recent transactions</h3>
            <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-400">View all</a>
        </div>
        @if ($recent->isEmpty())
            <x-empty-state icon="arrows" title="No transactions yet" message="Contributions and withdrawals you record will show up here." class="py-8" />
        @else
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($recent as $t)
                    @include('transactions._row', ['t' => $t, 'compact' => true])
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
