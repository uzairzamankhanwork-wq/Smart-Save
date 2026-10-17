@php $u = auth()->user(); @endphp
<x-layouts.app title="Goals">
    <x-page-header title="Savings goals" subtitle="Everything you're saving towards, in one place.">
        <a href="{{ route('transfers.create') }}" class="btn btn-secondary"><x-icon name="arrows" class="size-4" /> Move money</a>
        <a href="{{ route('goals.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New goal</a>
    </x-page-header>

    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-slate-800/70" aria-label="Filter by status">
            @foreach (['active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed', 'archived' => 'Archived', 'all' => 'All'] as $key => $label)
                <a href="{{ route('goals.index', ['status' => $key, 'sort' => $sort, 'q' => request('q')]) }}" @class([
                    'flex items-center gap-1.5 rounded-lg px-3 py-1.5 whitespace-nowrap transition',
                    'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' => $status === $key,
                    'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' => $status !== $key,
                ]) @if($status === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if ($key !== 'all')<span class="rounded-full bg-slate-200/80 px-1.5 text-xs dark:bg-slate-600">{{ $counts[$key] ?? 0 }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex gap-2" role="search">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative flex-1 lg:w-64">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <label for="goal-search" class="sr-only">Search goals</label>
                <input id="goal-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search goals" class="input pl-9">
            </div>
            <label for="goal-sort" class="sr-only">Sort by</label>
            <select id="goal-sort" name="sort" class="input w-auto" @change="$el.form.requestSubmit()">
                @foreach (['priority' => 'Priority', 'deadline' => 'Deadline', 'progress' => 'Progress', 'name' => 'Name', 'newest' => 'Newest'] as $k => $l)
                    <option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($goals->isEmpty())
        <div class="card">
            @if (request('q'))
                <x-empty-state icon="search" title="No goals match “{{ request('q') }}”" message="Try a different search." />
            @else
                <x-empty-state icon="target" title="No {{ $status === 'all' ? '' : $status }} goals" message="Goals help you put savings to work: a new phone, a trip, or a safety net.">
                    <a href="{{ route('goals.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Create a goal</a>
                </x-empty-state>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($goals as $g)
                @php $pct = $g->percent(); $needed = \App\Support\GoalPlanner::monthlyNeeded($g); @endphp
                <a href="{{ route('goals.show', $g) }}" class="card card-pad group flex flex-col transition duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="flex items-start gap-3">
                        <x-goal-icon :goal="$g" />
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">{{ $g->name }}</h3>
                            <p class="text-xs text-slate-500">{{ $g->categoryLabel() }}@if ($g->priority === 'high') · <span class="font-medium text-rose-600 dark:text-rose-400">High priority</span>@endif</p>
                        </div>
                        <span class="badge {{ \App\Support\Ui::status($g->status) }}">{{ $g->statusLabel() }}</span>
                    </div>

                    <div class="mt-5 flex items-end justify-between">
                        <div>
                            <p class="text-xl font-bold text-slate-900 tabular-nums dark:text-white">{{ $u->money($g->balance()) }}</p>
                            <p class="text-xs text-slate-500">of {{ $u->money($g->target_amount) }}</p>
                        </div>
                        <p class="text-2xl font-bold text-slate-900 tabular-nums dark:text-white">{{ $pct }}<span class="text-sm text-slate-400">%</span></p>
                    </div>
                    <x-progress :percent="$pct" :color="$g->color" :label="$g->name.' progress'" class="mt-3" />

                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                        @if ($g->target_date)
                            <span class="flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> {{ $g->target_date->format('j M Y') }}</span>
                        @endif
                        @if ($g->isTargetReached())
                            <span class="flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"><x-icon name="trophy" class="size-3.5" /> Target reached</span>
                        @elseif ($needed !== null && in_array($g->status, ['active', 'paused']))
                            <span class="flex items-center gap-1"><x-icon name="clock" class="size-3.5" /> {{ $u->money($needed) }}/month needed</span>
                        @else
                            <span>{{ $u->money($g->remaining()) }} to go</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
