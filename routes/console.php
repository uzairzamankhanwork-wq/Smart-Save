<?php

use Illuminate\Support\Facades\Schedule;

// Requires the server cron entry: * * * * * php /path/to/artisan schedule:run
Schedule::command('smartsave:reminders')->dailyAt('08:00')->withoutOverlapping();
