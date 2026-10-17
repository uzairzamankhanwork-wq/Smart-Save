{{-- Server flash messages → toast stack. Messages are escaped by Blade. --}}
@foreach (['success' => 'success', 'error' => 'error', 'info' => 'info', 'status' => 'info'] as $key => $type)
    @if (session($key))
        <span hidden data-flash="{{ session($key) }}" data-flash-type="{{ $type }}"></span>
    @endif
@endforeach
@if ($errors->any() && ! session('error'))
    <span hidden data-flash="Please check the highlighted fields." data-flash-type="error"></span>
@endif

<div class="pointer-events-none fixed inset-x-0 top-[calc(1rem+env(safe-area-inset-top))] z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end"
     x-data aria-live="polite" role="status">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2 sm:translate-x-4 sm:translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border bg-white p-4 shadow-xl dark:bg-slate-900"
             :class="{
                'border-emerald-200 dark:border-emerald-500/30': toast.type === 'success',
                'border-rose-200 dark:border-rose-500/30': toast.type === 'error',
                'border-sky-200 dark:border-sky-500/30': toast.type === 'info',
             }">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full"
                  :class="{
                    'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300': toast.type === 'error',
                    'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300': toast.type === 'info',
                  }">
                <x-icon name="check" class="size-4" x-show="toast.type === 'success'" />
                <x-icon name="warning" class="size-4" x-show="toast.type === 'error'" />
                <x-icon name="info" class="size-4" x-show="toast.type === 'info'" />
            </span>
            <p class="flex-1 text-sm text-slate-700 dark:text-slate-200" x-text="toast.message"></p>
            <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="$store.toasts.dismiss(toast.id)" aria-label="Dismiss">
                <x-icon name="x" class="size-4" />
            </button>
        </div>
    </template>
</div>
