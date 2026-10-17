<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>SmartSave summary {{ $year }}: {{ auth()->user()->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900">
<main class="mx-auto max-w-5xl p-8 print:p-0">
    <header class="mb-6 flex items-start justify-between border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold">SmartSave annual summary: {{ $year }}</h1>
            <p class="text-sm text-slate-500">{{ auth()->user()->name }} · Generated {{ now()->format('j M Y, H:i') }} · Currency {{ auth()->user()->currency }}</p>
        </div>
        <a href="{{ route('reports.index', ['year' => $year]) }}" class="no-print btn btn-secondary btn-sm">Back</a>
    </header>
    <p class="no-print mb-4 text-sm text-slate-500">Use your browser's Print command (Ctrl/Cmd + P) to print or save as PDF.</p>
    @include('reports._tables')
    <p class="mt-8 text-xs text-slate-400">Figures are calculated from the entries recorded in SmartSave. Planning figures are estimates only.</p>
</main>
</body>
</html>
