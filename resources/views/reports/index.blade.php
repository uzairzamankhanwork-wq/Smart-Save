@php
    $u = auth()->user();
    $symbol = config("smartsave.currencies.{$u->currency}.symbol", $u->currency);
@endphp
<x-layouts.app title="Reports">
    <x-page-header title="Reports" subtitle="Your savings, goals and spending for {{ $year }}.">
        <form method="GET" class="flex items-center gap-2">
            <label for="year" class="sr-only">Year</label>
            <select id="year" name="year" class="input w-auto" @change="$el.form.requestSubmit()">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
            </select>
            <noscript><button class="btn btn-secondary">Go</button></noscript>
        </form>
        <a href="{{ route('reports.export', ['year' => $year]) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> CSV</a>
        <a href="{{ route('reports.print', ['year' => $year]) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" class="size-4" /> Printable</a>
    </x-page-header>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="card card-pad">
            <h3 class="font-semibold">Savings trend</h3>
            <p class="text-xs text-slate-500">Total savings at the end of each month</p>
            <div class="relative mt-4 h-64" data-chart-wrap>
                <div class="skeleton absolute inset-0" data-skeleton></div>
                <canvas class="opacity-0 transition-opacity" data-chart="line" data-currency="{{ $symbol }}" role="img" aria-label="Line chart of total savings by month"
                        data-chart-data="{{ json_encode(['labels' => $chart['labels'], 'datasets' => [['label' => 'Total savings', 'data' => $chart['balance'], 'color' => '#10b981', 'fill' => true]]]) }}"></canvas>
            </div>
        </div>
        <div class="card card-pad">
            <h3 class="font-semibold">Saving vs spending</h3>
            <p class="text-xs text-slate-500">Net saved and expenses by month</p>
            <div class="relative mt-4 h-64" data-chart-wrap>
                <div class="skeleton absolute inset-0" data-skeleton></div>
                <canvas class="opacity-0 transition-opacity" data-chart="bar" data-currency="{{ $symbol }}" role="img" aria-label="Bar chart comparing net savings and expenses by month"
                        data-chart-data="{{ json_encode(['labels' => $chart['labels'], 'datasets' => [
                            ['label' => 'Net saved', 'data' => $chart['net'], 'color' => '#10b981'],
                            ['label' => 'Expenses', 'data' => $chart['expenses'], 'color' => '#f59e0b'],
                        ]]) }}"></canvas>
            </div>
        </div>
    </div>

    @include('reports._tables')
</x-layouts.app>
