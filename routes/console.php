<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler WAJIB berjalan (php artisan schedule:work, atau cron tiap menit).
Schedule::command('zones:check-offline')->everyMinute()->withoutOverlapping();
Schedule::command('model:prune')->daily();
