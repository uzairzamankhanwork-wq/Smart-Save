<x-layouts.guest title="Choose a new password">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Choose a new password</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">At least 10 characters, with upper and lower case letters and a number.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email address">
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" @class(['input', 'input-error' => $errors->has('email')])>
        </x-field>
        <x-field name="password" label="New password">
            <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'input-error' => $errors->has('password')])>
        </x-field>
        <x-field name="password_confirmation" label="Confirm new password">
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </x-field>
        <button type="submit" class="btn btn-primary w-full py-3">Reset password</button>
    </form>
</x-layouts.guest>
