@props(['tone' => 'info', 'icon' => null])
@php $icon = $icon ?? (['success' => 'check-circle', 'warning' => 'warning', 'danger' => 'warning'][$tone] ?? 'info'); @endphp
<div {{ $attributes->class(['flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', \App\Support\Ui::tone($tone)]) }} role="{{ in_array($tone, ['warning', 'danger']) ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0" />
    <div class="flex-1">{{ $slot }}</div>
</div>
