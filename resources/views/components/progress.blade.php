@props(['percent' => 0, 'color' => 'emerald', 'label' => 'Progress', 'size' => 'md'])
@php $pct = max(0, min(100, (float) $percent)); @endphp
<div {{ $attributes->class(['progress-track', '!h-1.5' => $size === 'sm', '!h-3' => $size === 'lg']) }}
     role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}" aria-label="{{ $label }}"
     x-data="{ w: 0 }" x-init="requestAnimationFrame(() => w = {{ $pct }})">
    <div class="progress-bar {{ \App\Support\Ui::goal($color, 'bar') }}" :style="`width: ${w}%`"></div>
</div>
