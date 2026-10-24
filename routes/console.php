<?php

use Illuminate\Support\Facades\Schedule;

// On a server with `php artisan schedule:run` in cron. Vercel has no
// scheduler, so there Vercel Cron calls /api/cron/prune instead (vercel.json).
Schedule::command('practice:prune')->dailyAt('03:00');
