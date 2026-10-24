<?php

return [

    /*
    | Shared account printed on the login page so learners can start
    | practising immediately. It cannot be password-reset or pruned.
    */
    'demo_email' => env('PRACTICE_DEMO_EMAIL', 'demo@practice.test'),
    'demo_password' => env('PRACTICE_DEMO_PASSWORD', 'Demo@1234'),

    /*
    | Author credits shown in the footer of every page. A link that is left
    | empty is simply not shown.
    */
    'author' => [
        'name' => 'Rafiul Alam Antar',
        'github' => env('PRACTICE_AUTHOR_GITHUB', 'https://github.com/rafiulalamantar'),
        'linkedin' => env('PRACTICE_AUTHOR_LINKEDIN', 'https://www.linkedin.com/in/rafiulalamantar/'),
        'coffee' => env('PRACTICE_AUTHOR_COFFEE', 'https://buymeacoffee.com/rafiulalamantar'),
    ],

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
    // Learner accounts are deleted this many days after registration, however
    // active they are, so learners register again. The demo account is kept.
    'account_lifetime_days' => (int) env('PRACTICE_ACCOUNT_LIFETIME_DAYS', 30),

    /*
    | TicketTrail sandbox caps per learner. When a cap is reached the oldest
    | entry is replaced, so the tables cannot grow without bound.
    */
    'tickets_max_events' => (int) env('PRACTICE_TICKETS_MAX_EVENTS', 6),
    'tickets_max_bookings' => (int) env('PRACTICE_TICKETS_MAX_BOOKINGS', 9),
    'tickets_max_per_booking' => 10,

    'cron_secret' => env('CRON_SECRET'),

    // Cache-busting fallback for asset URLs; Vercel sets this per deploy.
    'deploy_version' => env('VERCEL_GIT_COMMIT_SHA', ''),

];
