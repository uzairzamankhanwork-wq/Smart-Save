@props(['title', 'subtitle' => null])
<div {{ $attributes->class(['mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
        @if ($subtitle)<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
    </div>
    @if ($slot->isNotEmpty())<div class="no-print flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
