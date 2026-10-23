<?php

return [

    /*
    | Shared account printed on the login page so learners can start
    | practising immediately. It cannot be password-reset or pruned.
    */
    'demo_email' => env('PRACTICE_DEMO_EMAIL', 'demo@practice.test'),
    'demo_password' => env('PRACTICE_DEMO_PASSWORD', 'Demo@1234'),

    /*
    | Challenge mode: when a request carries the "practice_chaos" cookie or
    | an "X-Chaos-Mode: 1" header, API responses are delayed by a random
    | amount within this range so learners must use proper waits.
    */
    'chaos_min_ms' => (int) env('PRACTICE_CHAOS_MIN_MS', 300),
    'chaos_max_ms' => (int) env('PRACTICE_CHAOS_MAX_MS', 2500),

    /*
    | Data retention, enforced by `php artisan practice:prune` (run daily
    | by Vercel Cron through /api/cron/prune).
    */
    'order_retention_days' => (int) env('PRACTICE_ORDER_RETENTION_DAYS', 7),
    'inactive_user_days' => (int) env('PRACTICE_INACTIVE_USER_DAYS', 30),

    'cron_secret' => env('CRON_SECRET'),

];
