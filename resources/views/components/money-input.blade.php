@props(['name', 'value' => null, 'required' => false, 'placeholder' => '0.00'])
@php
    $id = str_replace(['[', ']', '.'], '_', $name);
    $currency = auth()->user()?->currency ?? 'PKR';
@endphp
<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-400">{{ config("smartsave.currencies.$currency.symbol", $currency) }}</span>
    <input type="number" inputmode="decimal" step="0.01" min="{{ $required ? '0.01' : '0' }}" max="{{ config('smartsave.max_amount') }}"
           name="{{ $name }}" id="{{ $id }}" value="{{ old($name, $value) }}" placeholder="{{ $placeholder }}" @required($required)
           @error($name) aria-invalid="true" aria-describedby="{{ $id }}_error" @enderror
           {{ $attributes->class(['input pl-12 tabular-nums', 'input-error' => $errors->has($name)]) }}>
</div>
