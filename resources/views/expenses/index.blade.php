@php
    $u = auth()->user();
    $symbol = config("smartsave.currencies.{$u->currency}.symbol", $u->currency);
    $cats = config('smartsave.expense_categories');
@endphp
<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" subtitle="Track where your money goes. Expenses never change your savings balances.">
        <a href="{{ route('expenses.export', request()->query()) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> Export CSV</a>
        <a href="{{ route('expenses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Record expense</a>
    </x-page-header>

    <div class="mb-4 grid gap-4 xl:grid-cols-3">
        <x-stat-card label="Spent this month" icon="receipt" tone="amber" :value="$u->money($monthTotal)">
            @if ($budget['expense_budget'] !== null)
                {{ $budget['budget_percent'] }}% of your {{ $u->money($budget['expense_budget']) }} budget
                <x-progress :percent="$budget['budget_percent']" :color="$budget['over_budget'] ? 'rose' : 'amber'" label="Budget used" size="sm" class="mt-2" />
            @else
                <a href="{{ route('budget.index') }}" class="font-medium text-brand-600 dark:text-brand-400">Set a monthly budget →</a>
            @endif
        </x-stat-card>
        <div class="card card-pad xl:col-span-2">
            <h3 class="font-semibold">This month by category</h3>
            @if (count($byCategory))
                <div class="mt-3 grid items-center gap-4 sm:grid-cols-2">
                    <div class="relative h-48" data-chart-wrap>
                        <div class="skeleton absolute inset-0 rounded-full" data-skeleton></div>
                        <canvas class="opacity-0 transition-opacity" data-chart="doughnut" data-currency="{{ $symbol }}" role="img" aria-label="Expenses by category this month"
                                data-chart-data="{{ json_encode(['labels' => array_map(fn ($c) => $cats[$c] ?? $c, array_keys($byCategory)), 'values' => array_map('floatval', array_values($byCategory))]) }}"></canvas>
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach ($byCategory as $cat => $total)
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $cats[$cat] ?? $cat }}</span>
                                <span class="font-semibold tabular-nums">{{ $u->money($total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">No expenses recorded this month.</p>
            @endif
        </div>
    </div>

    @if ($budget['over_budget'])
        <x-alert tone="danger" class="mb-4">You're {{ $u->money(ltrim($budget['budget_left'], '-')) }} over your spending budget for {{ now()->format('F') }}.</x-alert>
    @endif

    <form method="GET" class="card card-pad mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2"><label for="q" class="label">Search notes</label><input id="q" type="search" name="q" value="{{ $filters['q'] }}" class="input" placeholder="e.g. groceries"></div>
        <div><label for="from" class="label">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="input"></div>
        <div><label for="to" class="label">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="input"></div>
        <div>
            <label for="category" class="label">Category</label>
            <select id="category" name="category" class="input">
                <option value="">All</option>
                @foreach ($cats as $k => $l)<option value="{{ $k }}" @selected($filters['category'] === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
            <button class="btn btn-primary" type="submit"><x-icon name="filter" class="size-4" /> Apply</button>
            @if (request()->query())<a href="{{ route('expenses.index') }}" class="btn btn-ghost">Reset</a>@endif
            <p class="ml-auto self-center text-sm text-slate-500">Filtered total: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $u->money($filteredTotal) }}</span></p>
        </div>
    </form>

    <div class="card overflow-hidden">
        @if ($expenses->isEmpty())
            <x-empty-state icon="receipt" title="No expenses found" message="Recording spending helps you see where you could save more.">
                <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm">Record an expense</a>
            </x-empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Category</th><th class="hidden sm:table-cell">Note</th><th class="text-right">Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($expenses as $e)
                            <tr>
                                <td class="whitespace-nowrap">{{ $e->expense_date->format('j M Y') }}</td>
                                <td><span class="badge bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $e->categoryLabel() }}</span></td>
                                <td class="hidden max-w-xs truncate text-slate-500 sm:table-cell">{{ $e->note }}</td>
                                <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ $u->money($e->amount) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ route('expenses.edit', $e) }}" class="btn btn-ghost btn-sm px-2" aria-label="Edit expense"><x-icon name="pencil" class="size-4" /></a>
                                    <form method="POST" action="{{ route('expenses.destroy', $e) }}" class="inline" data-confirm="Delete this {{ $u->money($e->amount) }} expense? This can't be undone, though it stays in your audit log." data-confirm-action="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm px-2 text-rose-600 dark:text-rose-400" aria-label="Delete expense"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $expenses->links() }}</div>
        @endif
    </div>
</x-layouts.app>
