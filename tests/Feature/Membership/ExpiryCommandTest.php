<?php

use App\Models\Membership;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/*
 * Expiry command tests (SPEC-004 BR-007, AC-12, AM-10; ADR-004): the daily
 * `memberships:expire` command materializes the `expired` state for
 * pending/active memberships whose end date has passed.
 */

it('marks pending and active memberships with a passed end date as expired', function () {
    // AC-12 (BR-007): pending (never paid) and active memberships whose period
    // ended become `expired`; memberships still within their period are
    // untouched.
    $pastPending = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'end_date' => now()->subDays(2)->toDateString(),
    ]);
    $pastActive = Membership::factory()->create([
        'status' => Membership::STATUS_ACTIVE,
        'end_date' => now()->subDay()->toDateString(),
    ]);
    $todayActive = Membership::factory()->create([
        'status' => Membership::STATUS_ACTIVE,
        'end_date' => now()->toDateString(),
    ]);
    $futureActive = Membership::factory()->create([
        'status' => Membership::STATUS_ACTIVE,
        'end_date' => now()->addDay()->toDateString(),
    ]);

    Artisan::call('memberships:expire');

    expect(Artisan::output())->toBeEmpty();

    expect($pastPending->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect($pastActive->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect($todayActive->fresh()->status)->toBe(Membership::STATUS_ACTIVE);
    expect($futureActive->fresh()->status)->toBe(Membership::STATUS_ACTIVE);
});

it('leaves expired and cancelled memberships untouched', function () {
    // BR-009: expired/cancelled are terminal; the command never flips them.
    $expired = Membership::factory()->create([
        'status' => Membership::STATUS_EXPIRED,
        'end_date' => now()->subDays(5)->toDateString(),
    ]);
    $cancelled = Membership::factory()->create([
        'status' => Membership::STATUS_CANCELLED,
        'end_date' => now()->subDays(3)->toDateString(),
    ]);

    Artisan::call('memberships:expire');

    expect($expired->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect($cancelled->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
});

it('is idempotent when run repeatedly', function () {
    // ADR-004: the bulk update is safe to run repeatedly.
    $pastActive = Membership::factory()->create([
        'status' => Membership::STATUS_ACTIVE,
        'end_date' => now()->subDay()->toDateString(),
    ]);

    Artisan::call('memberships:expire');
    Artisan::call('memberships:expire');
    Artisan::call('memberships:expire');

    expect($pastActive->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
});

it('registers the memberships:expire command in the application schedule', function () {
    // ADR-004 / architecture SPEC-004 §5: the command is scheduled daily.
    $scheduled = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'memberships:expire'));

    expect($scheduled)->toHaveCount(1);
    expect((string) $scheduled->first()->expression)->toBe('5 0 * * *');
});
