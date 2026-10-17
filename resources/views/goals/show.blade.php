@php
    $u = auth()->user();
    $pct = $goal->percent();
    $balance = $goal->balance();
    $reached = $goal->milestonesReached();
    $transitions = $goal->allowedTransitions();
@endphp
<x-layouts.app :title="$goal->name">
    <a href="{{ route('goals.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"><x-icon name="chevron-left" class="size-4" /> All goals</a>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Summary --}}
            <div class="card card-pad">
                <div class="flex flex-wrap items-start gap-4">
                    <x-goal-icon :goal="$goal" size="lg" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $goal->name }}</h2>
                            <span class="badge {{ \App\Support\Ui::status($goal->status) }}">{{ $goal->statusLabel() }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $goal->categoryLabel() }} · {{ \App\Models\SavingsGoal::PRIORITIES[$goal->priority] }} priority</p>
                        @if ($goal->description)<p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $goal->description }}</p>@endif
                    </div>
                    @if ($goal->status !== 'archived')
                        <a href="{{ route('goals.edit', $goal) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> Edit</a>
                    @endif
                </div>

                <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div><p class="text-xs text-slate-500">Saved</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $u->money($balance) }}</p></div>
                    <div><p class="text-xs text-slate-500">Target</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $u->money($goal->target_amount) }}</p></div>
                    <div><p class="text-xs text-slate-500">Remaining</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $u->money($goal->remaining()) }}</p></div>
                    <div><p class="text-xs text-slate-500">Target date</p><p class="mt-1 text-lg font-bold">{{ $goal->target_date?->format('j M Y') ?? 'Not set' }}</p></div>
                </div>

                <div class="mt-6">
                    <div class="mb-2 flex justify-between text-sm"><span class="font-medium">{{ \App\Support\Ui::encouragement($pct) }}</span><span class="font-bold tabular-nums">{{ $pct }}%</span></div>
                    <x-progress :percent="$pct" :color="$goal->color" size="lg" :label="$goal->name.' progress'" />
                    {{-- Milestones --}}
                    <ol class="mt-4 grid grid-cols-4 gap-2" aria-label="Milestones">
                        @foreach (config('smartsave.milestones') as $m)
                            @php $done = in_array($m, $reached); @endphp
                            <li @class(['flex flex-col items-center gap-1 rounded-xl py-2 text-center text-xs transition',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $done,
                                'bg-slate-50 text-slate-400 dark:bg-slate-800/50' => ! $done])>
                                <x-icon :name="$done ? ($m === 100 ? 'trophy' : 'check-circle') : 'flag'" class="size-5" />
                                <span class="font-semibold">{{ $m }}%</span>
                                <span class="sr-only">{{ $done ? 'reached' : 'not yet reached' }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @foreach ($warnings as $w)
                    <x-alert :tone="$w['level']" class="mt-4">{{ $w['message'] }}</x-alert>
                @endforeach
                @if ($goal->isTargetReached() && in_array('completed', $transitions))
                    <x-alert tone="success" icon="trophy" class="mt-4">You've reached your target! Mark this goal as complete when you're ready.</x-alert>
                @endif

                {{-- Actions --}}
                <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                    @if ($goal->canReceiveMoney())
                        <a href="{{ route('transactions.create', ['type' => 'deposit', 'goal' => $goal->id]) }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Add money</a>
                    @endif
                    @if ($goal->canReleaseMoney() && \App\Support\Money::compare($balance, '0') > 0)
                        <a href="{{ route('transactions.create', ['type' => 'withdrawal', 'goal' => $goal->id]) }}" class="btn btn-secondary"><x-icon name="minus" class="size-4" /> Withdraw</a>
                        <a href="{{ route('transfers.create', ['from' => $goal->id]) }}" class="btn btn-secondary"><x-icon name="arrows" class="size-4" /> Move</a>
                    @endif
                    <div class="ml-auto flex flex-wrap gap-2">
                        @foreach ($transitions as $to)
                            @php
                                $cfg = [
                                    'paused' => ['Pause', 'pause', 'Pause this goal? Reminders stop until you resume it.', 'safe'],
                                    'active' => [$goal->status === 'archived' ? 'Restore' : ($goal->status === 'completed' ? 'Reopen' : 'Resume'), 'play', null, 'safe'],
                                    'completed' => ['Mark complete', 'check', 'Mark this goal as complete? You can reopen it later.', 'safe'],
                                    'archived' => ['Archive', 'archive', 'Archive this goal? Its history is kept and its balance still counts toward your total savings, but it becomes read-only.', 'danger'],
                                ][$to];
                            @endphp
                            <form method="POST" action="{{ route('goals.status', $goal) }}" @if($cfg[2]) data-confirm="{{ $cfg[2] }}" data-confirm-action="{{ $cfg[0] }}" data-confirm-tone="{{ $cfg[3] }}" @endif>
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $to }}">
                                <button type="submit" class="btn btn-ghost btn-sm"><x-icon :name="$cfg[1]" class="size-4" /> {{ $cfg[0] }}</button>
                            </form>
                        @endforeach
                        @if ($transactions->total() === 0)
                            <form method="POST" action="{{ route('goals.destroy', $goal) }}" data-confirm="Delete this goal permanently? It has no transactions, so nothing else is affected." data-confirm-action="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm text-rose-600 dark:text-rose-400"><x-icon name="trash" class="size-4" /> Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- History --}}
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-5 sm:px-6">
                    <h3 class="font-semibold">Contribution history</h3>
                    <a href="{{ route('transactions.index', ['goal' => $goal->id, 'show' => 'all']) }}" class="text-sm font-medium text-brand-600 dark:text-brand-400">Filter &amp; export</a>
                </div>
                @if ($transactions->isEmpty())
                    <x-empty-state icon="arrows" title="No contributions yet" message="Add your first contribution to start the progress bar moving." class="py-8">
                        @if ($goal->canReceiveMoney())<a href="{{ route('transactions.create', ['type' => 'deposit', 'goal' => $goal->id]) }}" class="btn btn-primary btn-sm">Add money</a>@endif
                    </x-empty-state>
                @else
                    <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($transactions as $t)
                            @include('transactions._row', ['t' => $t, 'showGoal' => false])
                        @endforeach
                    </ul>
                    <div class="px-5 py-4 sm:px-6">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>

        {{-- Plan sidebar --}}
        <div class="space-y-6">
            <div class="card card-pad">
                <h3 class="font-semibold">Plan</h3>
                <dl class="mt-4 space-y-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Needed per month</dt>
                        <dd class="text-right font-semibold tabular-nums">
                            @if ($goal->isTargetReached()) Done 🎉
                            @elseif ($monthlyNeeded === null) <span class="font-normal text-slate-400">Set a target date</span>
                            @else {{ $u->money($monthlyNeeded) }} @endif
                        </dd>
                    </div>
                    @if ($monthsLeft !== null && ! $goal->isTargetReached())
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Time left</dt><dd class="font-semibold">{{ $monthsLeft === 0 ? 'Deadline passed' : $monthsLeft.' '.\Illuminate\Support\Str::plural('month', $monthsLeft) }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Recent rate</dt>
                        <dd class="text-right font-semibold tabular-nums">{{ $rate !== null ? $u->money($rate).'/month' : '' }}@if ($rate === null)<span class="font-normal text-slate-400">Not enough history</span>@endif</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Estimated finish</dt>
                        <dd class="text-right font-semibold">
                            @if ($goal->isTargetReached()) Reached
                            @elseif ($estimate) {{ $estimate->format('M Y') }}
                            @else <span class="font-normal text-slate-400">Needs 2+ recent contributions</span> @endif
                        </dd>
                    </div>
                    @if ($goal->planned_contribution)
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Your planned amount</dt><dd class="font-semibold tabular-nums">{{ $u->money($goal->planned_contribution) }} {{ strtolower(config('smartsave.frequencies.'.$goal->contribution_frequency)) }}</dd></div>
                    @endif
                </dl>
                <p class="mt-4 text-xs text-slate-400">Estimates use the last {{ \App\Support\GoalPlanner::RATE_WINDOW_DAYS }} days of activity. They're a guide, not a guarantee.</p>
            </div>

            @if ($schedule['per_period'])
                <div class="card card-pad">
                    <h3 class="font-semibold">Suggested schedule</h3>
                    <p class="mt-1 text-sm text-slate-500">Save <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $u->money($schedule['per_period']) }}</span> {{ strtolower(config('smartsave.frequencies.'.$schedule['frequency'])) }} for {{ $schedule['periods'] }} {{ \Illuminate\Support\Str::plural('payment', $schedule['periods']) }} to reach your target on time.</p>
                    <ol class="mt-4 space-y-2">
                        @foreach ($schedule['dates'] as $i => $d)
                            <li class="flex items-center gap-3 text-sm">
                                <span class="grid size-7 place-items-center rounded-full bg-slate-100 text-xs font-semibold dark:bg-slate-800">{{ $i + 1 }}</span>
                                <span class="flex-1">{{ $d->format('D, j M Y') }}</span>
                                <span class="font-medium tabular-nums">{{ $u->money($schedule['per_period']) }}</span>
                            </li>
                        @endforeach
                        @if ($schedule['periods'] > count($schedule['dates']))<li class="pl-10 text-xs text-slate-400">…and {{ $schedule['periods'] - count($schedule['dates']) }} more</li>@endif
                    </ol>
                </div>
            @endif

            @if ($goal->notes)
                <div class="card card-pad">
                    <h3 class="font-semibold">Notes</h3>
                    <p class="mt-2 text-sm whitespace-pre-line text-slate-600 dark:text-slate-300">{{ $goal->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
