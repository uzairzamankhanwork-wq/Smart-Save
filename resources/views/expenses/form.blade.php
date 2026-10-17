@php $editing = $expense->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit expense' : 'Record expense'">
    <div class="mx-auto max-w-2xl">
        <x-page-header :title="$editing ? 'Edit expense' : 'Record an expense'" subtitle="Expenses are tracked separately from savings. To spend money from a goal, record a withdrawal instead." />

        <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" class="card card-pad space-y-6">
            @csrf
            @if ($editing) @method('PUT') @else <x-idempotency /> @endif

            <x-field name="amount" label="Amount" required>
                <x-money-input name="amount" :value="$expense->amount" required class="text-lg font-semibold" />
            </x-field>

            <fieldset>
                <legend class="label">Category</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (config('smartsave.expense_categories') as $k => $l)
                        <label class="cursor-pointer">
                            <input type="radio" name="category" value="{{ $k }}" class="peer sr-only" @checked(old('category', $expense->category) === $k)>
                            <span class="block rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:font-semibold peer-checked:text-brand-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 hover:border-slate-300 dark:border-slate-700 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-300">{{ $l }}</span>
                        </label>
                    @endforeach
                </div>
                @error('category')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="expense_date" label="Date" required>
                    <input type="date" id="expense_date" name="expense_date" required max="{{ now()->format('Y-m-d') }}" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" @class(['input', 'input-error' => $errors->has('expense_date')])>
                </x-field>
                <x-field name="note" label="Note">
                    <input id="note" name="note" maxlength="500" value="{{ old('note', $expense->note) }}" class="input" placeholder="Optional">
                </x-field>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <a href="{{ route('expenses.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Record expense' }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
