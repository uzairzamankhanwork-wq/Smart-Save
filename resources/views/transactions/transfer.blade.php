@php $u = auth()->user(); @endphp
<x-layouts.app title="Move money">
    <div class="mx-auto max-w-2xl">
        <x-page-header title="Move money between goals" subtitle="A transfer is recorded as a matched pair, so your total savings stay exactly the same." />

        <form method="POST" action="{{ route('transfers.store') }}" class="card card-pad space-y-6">
            @csrf
            <x-idempotency />

            <div class="grid items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                <x-field name="from_goal_id" label="From">
                    <select id="from_goal_id" name="from_goal_id" @class(['input', 'input-error' => $errors->has('from_goal_id')])>
                        <option value="">Unallocated ({{ $u->money($unallocated) }})</option>
                        @foreach ($goals as $g)
                            <option value="{{ $g->id }}" @selected((string) old('from_goal_id', $from) === (string) $g->id)>{{ $g->name }} ({{ $u->money($g->balance()) }})</option>
                        @endforeach
                    </select>
                </x-field>
                <span class="hidden pb-3 text-slate-400 sm:block"><x-icon name="chevron-right" class="size-5" /></span>
                <x-field name="to_goal_id" label="To">
                    <select id="to_goal_id" name="to_goal_id" @class(['input', 'input-error' => $errors->has('to_goal_id')])>
                        <option value="">Unallocated</option>
                        @foreach ($goals->whereIn('status', ['active', 'paused']) as $g)
                            <option value="{{ $g->id }}" @selected((string) old('to_goal_id') === (string) $g->id)>{{ $g->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="amount" label="Amount" required><x-money-input name="amount" required /></x-field>
                <x-field name="transaction_date" label="Date" required>
                    <input type="date" id="transaction_date" name="transaction_date" required max="{{ now()->format('Y-m-d') }}" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" class="input">
                </x-field>
            </div>
            <x-field name="note" label="Note"><input id="note" name="note" maxlength="500" value="{{ old('note') }}" class="input" placeholder="Optional"></x-field>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><x-icon name="arrows" class="size-4" /> Move money</button>
            </div>
        </form>
    </div>
</x-layouts.app>
