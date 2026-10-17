@php
    /** @var \App\Models\SavingsTransaction $t */
    $u = auth()->user();
    $compact = $compact ?? false;
    $showGoal = $showGoal ?? true;
    $neg = $t->isNegative();
    $tone = match ($t->type) {
        'deposit' => 'emerald', 'initial' => 'teal', 'withdrawal' => 'rose', default => 'indigo',
    };
    $icon = match ($t->type) {
        'deposit' => 'arrow-up', 'initial' => 'flag', 'withdrawal' => 'arrow-down', default => 'arrows',
    };
@endphp
<li @class(['flex items-center gap-3 px-5 py-3.5 sm:px-6', 'opacity-60' => $t->isVoided()]) x-data="{ voiding: false }">
    <x-goal-icon :icon="$icon" :color="$tone" size="sm" />
    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-x-2 text-sm font-medium text-slate-900 dark:text-slate-100">
            <span @class(['line-through' => $t->isVoided()])>{{ $t->label() }}</span>
            @if ($showGoal)
                <span class="text-slate-400">·</span>
                @if ($t->goal)
                    <a href="{{ route('goals.show', $t->goal_id) }}" class="truncate text-slate-600 hover:text-brand-600 dark:text-slate-300">{{ $t->goal->name }}</a>
                @else
                    <span class="text-slate-500">Unallocated</span>
                @endif
            @endif
            @if ($t->isVoided())
                <span class="badge bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300" title="{{ $t->void_reason }}">Reversed</span>
            @endif
        </p>
        <p class="truncate text-xs text-slate-500 dark:text-slate-400">
            {{ $t->transaction_date->format('j M Y') }}@if ($t->note) · {{ $t->note }}@endif
            @if ($t->isVoided() && $t->void_reason) · Reason: {{ $t->void_reason }}@endif
        </p>
    </div>
    <p @class(['text-sm font-semibold whitespace-nowrap tabular-nums',
        'text-rose-600 dark:text-rose-400' => $neg && ! $t->isTransfer(),
        'text-emerald-600 dark:text-emerald-400' => ! $neg && ! $t->isTransfer(),
        'text-slate-600 dark:text-slate-300' => $t->isTransfer()])>
        {{ $neg ? '−' : '+' }}{{ $u->money($t->amount) }}
    </p>

    @if (! $compact && ! $t->isVoided())
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" @click.outside="open = false" class="btn btn-ghost btn-sm px-2" aria-label="Actions for this entry" :aria-expanded="open">
                <x-icon name="chevron-down" class="size-4" />
            </button>
            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 z-10 mt-1 w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <a href="{{ route('transactions.edit', $t) }}" class="nav-link py-2"><x-icon name="pencil" class="size-4" /> Correct</a>
                <button type="button" class="nav-link w-full py-2 text-rose-600 dark:text-rose-400" @click="open = false; voiding = true"><x-icon name="undo" class="size-4" /> Reverse</button>
            </div>
        </div>
    @endif

    @if (! $compact && ! $t->isVoided())
        {{-- Reverse dialog --}}
        <template x-teleport="body">
            <div x-show="voiding" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="voiding = false">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="voiding = false"></div>
                <form method="POST" action="{{ route('transactions.void', $t) }}" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900" x-trap.noscroll="voiding">
                    @csrf
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Reverse this entry?</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ $t->label() }} of {{ $u->money($t->amount) }} on {{ $t->transaction_date->format('j M Y') }} will stop counting toward balances.
                        @if ($t->isTransfer()) Both sides of the transfer are reversed. @endif
                        It stays in your history and audit trail.
                    </p>
                    <label for="reason-{{ $t->id }}" class="label mt-4">Reason</label>
                    <input id="reason-{{ $t->id }}" name="reason" required maxlength="255" class="input" placeholder="e.g. Entered twice by mistake">
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="btn btn-secondary" @click="voiding = false">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reverse entry</button>
                    </div>
                </form>
            </div>
        </template>
    @endif
</li>
