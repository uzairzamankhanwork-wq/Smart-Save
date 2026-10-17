<x-layouts.guest title="Sign in">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Welcome back</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sign in to continue to your savings.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf
        <x-field name="email" label="Email address">
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   @class(['input', 'input-error' => $errors->has('email')]) @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
        </x-field>

        <x-field name="password" label="Password" x-data="{ show: false }">
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" class="input pr-16">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" x-text="show ? 'Hide' : 'Show'">Show</button>
            </div>
        </x-field>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="remember" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3">Sign in</button>
    </form>
</x-layouts.guest>
