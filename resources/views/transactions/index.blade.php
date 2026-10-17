@php $u = auth()->user(); @endphp
<x-layouts.app title="Transactions">
    <x-page-header title="Transactions" subtitle="Every contribution, withdrawal and transfer, with nothing hidden.">
        <a href="{{ route('transactions.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Export CSV</a>
        <a href="{{ route('transfers.create') }}" class="btn btn-secondary"><x-icon name="arrows" class="size-4" /> Transfer</a>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> New entry</a>
    </x-page-header>

    {{-- Filters --}}
    <form method="GET" class="card card-pad mb-4" x-data="{ open: {{ count(array_filter(\Illuminate\Support\Arr::except($filters, 'show'))) ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-48 flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <label for="tx-q" class="sr-only">Search notes</label>
                <input id="tx-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search notes" class="input pl-9">
            </div>
            <button type="button" class="btn btn-secondary" @click="open = !open" :aria-expanded="open"><x-icon name="filter" class="size-4" /> Filters</button>
            <button type="submit" class="btn btn-primary">Apply</button>
            @if (request()->query())<a href="{{ route('transactions.index') }}" class="btn btn-ghost">Reset</a>@endif
        </div>
        <div x-show="open" x-transition x-cloak class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div><label for="from" class="label">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="input"></div>
            <div><label for="to" class="label">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="input"></div>
            <div>
                <label for="goal" class="label">Goal</label>
                <select id="goal" name="goal" class="input">
                    <option value="">All goals</option>
                    <option value="none" @selected($filters['goal'] === 'none')>Unallocated</option>
                    @foreach ($goals as $g)<option value="{{ $g->id }}" @selected((string) $filters['goal'] === (string) $g->id)>{{ $g->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="type" class="label">Type</label>
                <select id="type" name="type" class="input">
                    <option value="">All types</option>
                    @foreach (\App\Models\SavingsTransaction::LABELS as $k => $l)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="show" class="label">Show</label>
                <select id="show" name="show" class="input">
                    <option value="active" @selected($filters['show'] === 'active')>Active entries</option>
                    <option value="voided" @selected($filters['show'] === 'voided')>Reversed entries</option>
                    <option value="all" @selected($filters['show'] === 'all')>Everything</option>
                </select>
            </div>
        </div>
    </form>

    <div class="mb-4 grid grid-cols-2 gap-4">
        <div class="card card-pad"><p class="text-xs text-slate-500">Money in (filtered)</p><p class="mt-1 text-lg font-bold text-emerald-600 tabular-nums dark:text-emerald-400">+{{ $u->money($moneyIn) }}</p></div>
        <div class="card card-pad"><p class="text-xs text-slate-500">Withdrawn (filtered)</p><p class="mt-1 text-lg font-bold text-rose-600 tabular-nums dark:text-rose-400">−{{ $u->money($moneyOut) }}</p></div>
    </div>

    <div class="card overflow-hidden">
        @if ($transactions->isEmpty())
            <x-empty-state icon="arrows" title="No transactions found" message="{{ request()->query() ? 'Try adjusting the filters.' : 'Record your first contribution to get started.' }}">
                <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm">Add savings</a>
            </x-empty-state>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($transactions as $t)
                    @include('transactions._row', ['t' => $t])
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">{{ $transactions->links() }}</div>
        @endif
    </div>
    <p class="mt-3 text-xs text-slate-400">Transfers move money between goals, so they're excluded from the money-in / withdrawn totals.</p>
</x-layouts.app>
