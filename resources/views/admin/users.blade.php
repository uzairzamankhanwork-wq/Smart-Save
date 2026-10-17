<x-layouts.app title="Users">
    <x-page-header title="User accounts" subtitle="{{ $users->count() }} of {{ $maxUsers }} accounts used. Administrators manage accounts only and cannot see anyone's finances." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="hidden md:table-cell">Last sign-in</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php $self = $user->id === auth()->id(); @endphp
                            <tr>
                                <td>
                                    <p class="font-medium">{{ $user->name }} @if($self)<span class="text-xs text-slate-400">(you)</span>@endif</p>
                                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                </td>
                                <td><span class="badge {{ $user->isAdmin() ? 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ ucfirst($user->role) }}</span></td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' }}">{{ $user->is_active ? 'Active' : 'Deactivated' }}</span>
                                    @if ($user->must_change_password)<span class="badge mt-1 bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Temp password</span>@endif
                                </td>
                                <td class="hidden text-sm text-slate-500 md:table-cell">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @unless ($self)
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline"
                                              data-confirm="{{ $user->is_active ? 'Deactivate '.$user->name.'? They will be signed out and unable to sign in. Their data is kept.' : 'Reactivate '.$user->name.'?' }}"
                                              data-confirm-action="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}" data-confirm-tone="{{ $user->is_active ? 'danger' : 'safe' }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="role" value="{{ $user->role }}">
                                            <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-ghost btn-sm">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline"
                                              data-confirm="{{ $user->isAdmin() ? 'Remove admin rights from '.$user->name.'?' : 'Make '.$user->name.' an administrator? Admins manage accounts but still cannot see other users\' finances.' }}" data-confirm-tone="safe" data-confirm-action="Change role">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="role" value="{{ $user->isAdmin() ? 'user' : 'admin' }}">
                                            <input type="hidden" name="is_active" value="{{ $user->is_active ? 1 : 0 }}">
                                            <button type="submit" class="btn btn-ghost btn-sm">{{ $user->isAdmin() ? 'Make user' : 'Make admin' }}</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Password resets (one form per user, shown on demand) --}}
            @foreach ($users->where('id', '!=', auth()->id()) as $user)
                <details class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <summary class="cursor-pointer text-sm text-slate-600 dark:text-slate-300">Set a temporary password for {{ $user->name }}</summary>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-3 grid gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf @method('PUT')
                        <div><label class="label" for="pw-{{ $user->id }}">Temporary password</label><input id="pw-{{ $user->id }}" type="password" name="password" required autocomplete="new-password" class="input"></div>
                        <div><label class="label" for="pwc-{{ $user->id }}">Confirm</label><input id="pwc-{{ $user->id }}" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></div>
                        <button type="submit" class="btn btn-secondary">Set &amp; sign them out</button>
                    </form>
                </details>
            @endforeach
        </div>

        <div class="space-y-6">
            @if ($users->count() < $maxUsers)
                <form method="POST" action="{{ route('admin.users.store') }}" class="card card-pad space-y-4">
                    @csrf
                    <h3 class="font-semibold">Add an account</h3>
                    <x-field name="name" label="Name" required><input id="name" name="name" value="{{ old('name') }}" required maxlength="100" class="input"></x-field>
                    <x-field name="email" label="Email" required><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></x-field>
                    <x-field name="role" label="Role">
                        <select id="role" name="role" class="input"><option value="user">User</option><option value="admin">Admin</option></select>
                    </x-field>
                    <x-field name="password" label="Temporary password" required hint="They'll be asked to choose their own at first sign-in.">
                        <input id="password" type="password" name="password" required autocomplete="new-password" class="input">
                    </x-field>
                    <x-field name="password_confirmation" label="Confirm password" required><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></x-field>
                    <button type="submit" class="btn btn-primary w-full">Create account</button>
                </form>
            @else
                <x-alert tone="info">The account limit ({{ $maxUsers }}) has been reached. Deactivated accounts still count toward the limit.</x-alert>
            @endif

            <div class="card card-pad">
                <h3 class="font-semibold">Security log</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($log as $entry)
                        <li class="flex justify-between gap-3">
                            <span>{{ $entry->description() }} <span class="text-slate-400">· {{ $entry->user?->name ?? 'unknown' }}</span></span>
                            <span class="shrink-0 text-xs text-slate-400">{{ $entry->created_at->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Nothing yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
