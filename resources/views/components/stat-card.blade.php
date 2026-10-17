@props(['label', 'value' => null, 'icon', 'tone' => 'emerald', 'hint' => null, 'animate' => null, 'currency' => null])
<div {{ $attributes->class(['card card-pad relative overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:shadow-md']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
            <p class="mt-2 text-xl font-bold tracking-tight text-slate-900 tabular-nums [overflow-wrap:anywhere] dark:text-white 2xl:text-2xl">
                @if ($animate !== null)
                    <span class="text-base font-semibold text-slate-400">{{ $currency }}</span>
                    <span x-data="countUp({{ (float) $animate }})" x-text="display">{{ number_format((float) $animate, 2) }}</span>
                @else
                    {{ $value }}
                @endif
            </p>
        </div>
        <x-goal-icon :icon="$icon" :color="$tone" />
    </div>
    @if ($hint || $slot->isNotEmpty())
        <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}{{ $slot }}</div>
    @endif
</div>
