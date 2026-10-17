<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GoalAlerts;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'smartsave:reminders';

    protected $description = 'Create deadline, inactivity, monthly-target and budget reminders for all active users';

    public function handle(GoalAlerts $alerts): int
    {
        $count = 0;
        User::where('is_active', true)->each(function (User $user) use ($alerts, &$count) {
            $before = $user->notifications()->count();
            $alerts->runDaily($user);
            $count += $user->notifications()->count() - $before;
        });

        $this->info("Created {$count} reminder(s).");

        return self::SUCCESS;
    }
}
