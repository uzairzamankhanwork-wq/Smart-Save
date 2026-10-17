@php
    $u = auth()->user();
    $editing = $transaction->exists;
    $isTransfer = $editing && $transaction->isTransfer();
    $types = [
        'deposit' => ['Contribution', 'arrow-up', 'Add new money to savings'],
        'withdrawal' => ['Withdrawal', 'arrow-down', 'Take money out of savings'],
        'initial' => ['Opening balance', 'flag', 'Money you had saved before using SmartSave'],
    ];
@endphp
<x-layouts.app :title="$editing ? 'Correct entry' : 'New entry'">
    <div class="mx-auto max-w-2xl">
        <x-page-header :title="$editing ? 'Correct an entry' : 'Record savings'"
                       :subtitle="$editing ? 'Corrections are recorded in your audit trail. To change the type, reverse this entry and add a new one.' : 'Amounts are recorded in '.$u->currency.'.'" />

        <form method="POST" action="{{ $editing ? route('transactions.update', $transaction) : route('transactions.store') }}" class="card card-pad space-y-6"
              x-data="{ type: @js(old('type', $transaction->type)), goal: @js((string) old('goal_id', $transaction->goal_id)) }">
            @csrf
            @if ($editing) @method('PUT') @else <x-idempotency /> @endif

            @if ($isTransfer)
                <x-alert tone="info">This is one side of a transfer. Only the date and note can be changed. To change the amount, reverse the transfer and record a new one.</x-alert>
            @endif

            @unless ($editing)
                <fieldset>
                    <legend class="label">Type</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($types as $key => [$label, $icon, $desc])
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $key }}" x-model="type" class="peer sr-only">
                                <span class="flex h-full flex-col gap-1 rounded-xl border border-slate-200 p-3 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-500/20 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 hover:border-slate-300 dark:border-slate-700 dark:peer-checked:bg-brand-500/10">
                                    <span class="flex items-center gap-2 text-sm font-semibold"><x-icon :name="$icon" class="size-4" /> {{ $label }}</span>
                                    <span class="text-xs text-slate-500">{{ $desc }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('type')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                </fieldset>
            @endunless

            @unless ($isTransfer)
                <x-field name="goal_id" label="Goal">
                    <select id="goal_id" name="goal_id" x-model="goal" @class(['input', 'input-error' => $errors->has('goal_id')])>
                        <option value="">No goal (unallocated savings)</option>
                        @foreach ($goals as $g)
                            <option value="{{ $g->id }}" @selected((string) old('goal_id', $transaction->goal_id) === (string) $g->id)>
                                {{ $g->name }} ({{ $u->money($g->balance()) }}){{ $g->status !== 'active' ? ' · '.$g->status : '' }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="amount" label="Amount" required>
                    <x-money-input name="amount" :value="$transaction->amount" required class="text-lg font-semibold" />
                </x-field>
            @else
                <div class="rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/60">
                    <span class="text-slate-500">Amount:</span> <span class="font-semibold">{{ $u->money($transaction->amount) }}</span>
                </div>
            @endunless

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="transaction_date" label="Date" required>
                    <input type="date" id="transaction_date" name="transaction_date" required max="{{ now()->format('Y-m-d') }}"
                           value="{{ old('transaction_date', $transaction->transaction_date?->format('Y-m-d')) }}" @class(['input', 'input-error' => $errors->has('transaction_date')])>
                </x-field>
                <x-field name="note" label="Note">
                    <input id="note" name="note" maxlength="500" value="{{ old('note', $transaction->note) }}" class="input" placeholder="Optional">
                </x-field>
            </div>

            <div x-show="type === 'withdrawal'" x-cloak>
                <x-alert tone="info">A withdrawal can't take a goal below zero. Spending that doesn't come out of savings belongs in <a class="font-semibold underline" href="{{ route('expenses.create') }}">Expenses</a>.</x-alert>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('transactions.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save correction' : 'Record entry' }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
