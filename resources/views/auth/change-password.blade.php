<x-layouts.guest title="Set your password">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Set your own password</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your account was set up with a temporary password. Please choose a new one to continue.</p>

    <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')
        <x-field name="current_password" label="Temporary password">
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" @class(['input', 'input-error' => $errors->has('current_password')])>
        </x-field>
        <x-field name="password" label="New password" hint="At least 10 characters, with upper and lower case letters and a number.">
            <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'input-error' => $errors->has('password')])>
        </x-field>
        <x-field name="password_confirmation" label="Confirm new password">
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </x-field>
        <button type="submit" class="btn btn-primary w-full py-3">Save password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-slate-500 hover:text-slate-700">Sign out</button>
    </form>
</x-layouts.guest>
