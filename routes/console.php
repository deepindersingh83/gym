<?php

use Illuminate\Support\Facades\Schedule;

// CloudPanel cron (every minute): php /home/<site-user>/htdocs/<domain>/artisan schedule:run
Schedule::command('gym:send-reminders')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-batches')->daily();
