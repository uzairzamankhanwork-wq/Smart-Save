<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" subtitle="Milestones, deadlines and budget alerts.">
        <a href="{{ route('profile.edit') }}#reminders" class="btn btn-secondary"><x-icon name="cog" class="size-4" /> Preferences</a>
        @if (auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button type="submit" class="btn btn-primary"><x-icon name="check" class="size-4" /> Mark all read</button></form>
        @endif
    </x-page-header>

    <div class="card overflow-hidden">
        @forelse ($notifications as $n)
            @php
                $tone = $n->data['tone'] ?? 'info';
                $icon = ['milestone' => 'trophy', 'goal_completed' => 'trophy', 'deadline' => 'calendar', 'budget' => 'warning', 'monthly_target' => 'arrow-trending', 'inactive_goal' => 'clock'][$n->data['kind'] ?? ''] ?? 'bell';
                $color = ['success' => 'emerald', 'warning' => 'amber', 'info' => 'sky'][$tone] ?? 'sky';
            @endphp
            <a href="{{ route('notifications.open', $n->id) }}" @class(['flex items-start gap-4 border-b border-slate-100 px-5 py-4 transition last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40 sm:px-6', 'bg-brand-50/40 dark:bg-brand-500/5' => ! $n->read_at])>
                <x-goal-icon :icon="$icon" :color="$color" size="sm" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ $n->data['title'] ?? 'Notification' }}
                        @unless ($n->read_at)<span class="size-2 rounded-full bg-brand-500" aria-label="Unread"></span>@endunless
                    </p>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $n->data['message'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</p>
                </div>
            </a>
        @empty
            <x-empty-state icon="bell" title="You're all caught up" message="We'll let you know about milestones, approaching deadlines and budget limits." />
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
