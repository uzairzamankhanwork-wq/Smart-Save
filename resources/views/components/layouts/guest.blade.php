@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}SmartSave</title>
    <x-pwa-head />
    <script src="{{ asset('js/theme-init.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="relative flex min-h-full items-center justify-center overflow-hidden px-4 py-12">
    <div class="pointer-events-none absolute -top-40 -left-40 size-[32rem] rounded-full bg-brand-400/20 blur-3xl dark:bg-brand-500/10"></div>
    <div class="pointer-events-none absolute -right-40 -bottom-40 size-[32rem] rounded-full bg-teal-400/20 blur-3xl dark:bg-teal-500/10"></div>

    <div class="relative w-full max-w-md animate-fade-up">
        <div class="mb-8 flex flex-col items-center text-center">
            <span class="grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-teal-600 text-white shadow-lg shadow-brand-600/30">
                <x-icon name="piggy-bank" class="size-8" />
            </span>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">SmartSave</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your private savings &amp; goal tracker</p>
        </div>
        <div class="card card-pad sm:p-8">
            {{ $slot }}
        </div>
        <x-install-button class="mx-auto mt-4 w-fit" />
        <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-slate-400"><x-icon name="lock" class="size-3.5" /> Private, invite-only access</p>
    </div>
</div>
<x-flash />
</body>
</html>
