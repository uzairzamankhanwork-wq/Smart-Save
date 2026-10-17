<x-layouts.guest title="Reset password">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Forgot your password?</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter your email and we'll send a reset link. The link expires after {{ config('auth.passwords.users.expire') }} minutes.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf
        <x-field name="email" label="Email address">
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['input', 'input-error' => $errors->has('email')])>
        </x-field>
        <button type="submit" class="btn btn-primary w-full py-3">Email reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">Back to sign in</a></p>
</x-layouts.guest>
