<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Materialize the `expired` membership state daily (SPEC-004 BR-007, ADR-004).
// The early run minimizes the staleness window between midnight and the job.
// Requires the Laravel scheduler to run in every environment
// (`php artisan schedule:run` every minute via cron, or equivalent in Docker).
Schedule::command('memberships:expire')->dailyAt('00:05');

// Nightly demo reset so a public portfolio demo can never be permanently
// polluted or abused: transactional/demo data is wiped and reseeded (SPEC-016).
// Skipped automatically in production (demo:reset guard). Requires the same
// scheduler as above.
Schedule::command('demo:reset')->dailyAt('04:00');
