{{-- Global confirmation dialog, opened by any <form data-confirm="..."> (see app.js). --}}
<div x-data x-cloak x-show="$store.confirm.open" class="fixed inset-0 z-50 grid place-items-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="confirm-title" @keydown.escape.window="$store.confirm.cancel()">
    <div x-show="$store.confirm.open" x-transition.opacity class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$store.confirm.cancel()"></div>
    <div x-show="$store.confirm.open" x-transition.scale.95 x-trap.noscroll="$store.confirm.open"
         class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
        <div class="flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full"
                  :class="$store.confirm.danger ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'">
                <x-icon name="warning" class="size-6" />
            </span>
            <div>
                <h2 id="confirm-title" class="text-lg font-semibold text-slate-900 dark:text-white" x-text="$store.confirm.title"></h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400" x-text="$store.confirm.message"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="btn btn-secondary" @click="$store.confirm.cancel()">Cancel</button>
            <button type="button" class="btn" :class="$store.confirm.danger ? 'btn-danger' : 'btn-primary'" @click="$store.confirm.accept()" x-text="$store.confirm.action"></button>
        </div>
    </div>
</div>
