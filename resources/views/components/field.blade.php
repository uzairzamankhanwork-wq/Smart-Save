{{-- Label + control slot + hint + validation error, wired up for screen readers. --}}
@props(['name', 'label', 'hint' => null, 'required' => false])
@php $id = str_replace(['[', ']', '.'], '_', $name); @endphp
<div {{ $attributes }}>
    <label for="{{ $id }}" class="label">{{ $label }} @if($required)<span class="text-rose-500" aria-hidden="true">*</span>@endif</label>
    {{ $slot }}
    @if ($hint)<p class="hint" id="{{ $id }}_hint">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400" id="{{ $id }}_error" role="alert">{{ $message }}</p>@enderror
</div>
