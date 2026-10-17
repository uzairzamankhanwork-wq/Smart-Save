{{-- Minimal, self-contained error page: never shows stack traces or internals. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · SmartSave</title>
    <script src="{{ asset('js/theme-init.js') }}"></script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="grid min-h-screen place-items-center bg-slate-50 p-6 text-center dark:bg-slate-950">
    <div>
        <p class="text-6xl font-bold text-brand-600">{{ $code }}</p>
        <h1 class="mt-4 text-xl font-semibold text-slate-900 dark:text-white">{{ $title }}</h1>
        <p class="mt-2 text-slate-500 dark:text-slate-400">{{ $message }}</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary mt-6">Back to dashboard</a>
    </div>
</body>
</html>
