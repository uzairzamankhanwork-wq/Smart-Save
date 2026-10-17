@php
    $labels = [
        'deadline' => ['Approaching deadlines', 'When a goal is due within two weeks'],
        'monthly_target' => ['Monthly savings target', 'From the 20th if you are behind your target'],
        'milestone' => ['Goal milestones', 'When a goal reaches 25%, 50%, 75% and 100%'],
        'budget' => ['Budget warnings', 'At 80% and 100% of your spending budget'],
        'inactive_goal' => ['Inactive goals', 'When nothing has been added to a goal for 30 days'],
        'goal_completed' => ['Completed goals', 'When you mark a goal complete'],
        'email' => ['Also send by email', 'Requires email to be configured on the server'],
    ];
    $mailReady = ! in_array(config('mail.default'), ['log', 'array', null], true);
@endphp
<x-layouts.app title="Profile">
    <x-page-header title="Profile & settings" />

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('profile.update') }}" class="card card-pad space-y-5">
            @csrf @method('PATCH')
            <h3 class="font-semibold">Your details</h3>
            <x-field name="name" label="Name" required>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name" @class(['input', 'input-error' => $errors->has('name')])>
            </x-field>
            <x-field name="email" label="Email" required>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email" @class(['input', 'input-error' => $errors->has('email')])>
            </x-field>
            <x-field name="currency" label="Currency" hint="Changes the label on all amounts. Amounts are not converted.">
                <select id="currency" name="currency" class="input">
                    @foreach (config('smartsave.currencies') as $code => $c)
                        <option value="{{ $code }}" @selected(old('currency', $user->currency) === $code)>{{ $code }} · {{ $c['name'] }}</option>
                    @endforeach
                </select>
            </x-field>
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>

        <form method="POST" action="{{ route('profile.password') }}" class="card card-pad space-y-5">
            @csrf @method('PUT')
            <h3 class="font-semibold">Change password</h3>
            <x-field name="current_password" label="Current password" required>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" @class(['input', 'input-error' => $errors->has('current_password')])>
            </x-field>
            <x-field name="password" label="New password" required hint="At least 10 characters, with upper and lower case letters and a number.">
                <input id="password" type="password" name="password" required autocomplete="new-password" @class(['input', 'input-error' => $errors->has('password')])>
            </x-field>
            <x-field name="password_confirmation" label="Confirm new password" required>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
            </x-field>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>

        <form method="POST" action="{{ route('profile.preferences') }}" class="card card-pad space-y-4" id="reminders">
            @csrf @method('PATCH')
            <h3 class="font-semibold">Reminders &amp; notifications</h3>
            @foreach ($labels as $key => [$label, $desc])
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="hidden" name="notifications[{{ $key }}]" value="0">
                    <input type="checkbox" name="notifications[{{ $key }}]" value="1" @checked($user->wantsNotification($key)) @disabled($key === 'email' && ! $mailReady)
                           class="mt-0.5 size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span><span class="block text-sm font-medium">{{ $label }}</span><span class="block text-xs text-slate-500">{{ $desc }}</span></span>
                </label>
            @endforeach
            @unless ($mailReady)
                <p class="text-xs text-slate-400">Email delivery isn't configured on this server, so reminders are shown in the app only.</p>
            @endunless
            <button type="submit" class="btn btn-primary">Save preferences</button>
        </form>

        <div class="card card-pad">
            <h3 class="font-semibold">Recent account activity</h3>
            <ul class="mt-4 space-y-3 text-sm">
                @forelse ($activity as $a)
                    <li class="flex items-start justify-between gap-4">
                        <span>{{ $a->description() }}</span>
                        <span class="shrink-0 text-xs text-slate-400" title="{{ $a->created_at }}">{{ $a->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No activity yet.</li>
                @endforelse
            </ul>
            <p class="mt-4 text-xs text-slate-400">Last sign-in: {{ $user->last_login_at?->format('j M Y, H:i') ?? 'n/a' }}. You're signed out automatically after {{ config('smartsave.idle_timeout') }} minutes of inactivity.</p>
        </div>
    </div>
</x-layouts.app>
