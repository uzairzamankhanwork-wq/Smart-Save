@props(['goal' => null, 'icon' => null, 'color' => null, 'size' => 'md'])
@php
    $icon = $icon ?? $goal?->icon ?? 'wallet';
    $color = $color ?? $goal?->color ?? 'slate';
    $box = ['sm' => 'size-8 rounded-lg', 'md' => 'size-11 rounded-xl', 'lg' => 'size-14 rounded-2xl'][$size];
    $ico = ['sm' => 'size-4', 'md' => 'size-5', 'lg' => 'size-7'][$size];
@endphp
<span {{ $attributes->class(['grid shrink-0 place-items-center', $box, \App\Support\Ui::goal($color)]) }}>
    <x-icon :name="$icon" :class="$ico" />
</span>
