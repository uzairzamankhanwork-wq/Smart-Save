@props(['icon' => 'sparkles', 'title', 'message' => null])
<div {{ $attributes->class(['flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-50 to-teal-50 text-brand-600 ring-1 ring-brand-100 dark:from-brand-500/10 dark:to-teal-500/10 dark:text-brand-300 dark:ring-brand-500/20">
        <x-icon :name="$icon" class="size-7" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    @if ($message)<p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $message }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5 flex flex-wrap justify-center gap-3">{{ $slot }}</div>@endif
</div>
