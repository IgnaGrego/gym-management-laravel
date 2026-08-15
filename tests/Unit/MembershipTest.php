<?php

use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Database\QueryException;

/*
 * Membership model unit tests (SPEC-004 BR-003, BR-004, BR-005, BR-007,
 * BR-008, BR-009, BR-014; AM-03, AM-07; AC-1, AC-3, AC-10, AC-11, AC-15,
 * AC-17).
 */

it('computes end_date as start_date + duration_days - 1 via the creating hook', function () {
    // BR-003, AM-07: the membership is valid for duration_days calendar days,
    // inclusive. The creating hook is the single source of truth (AC-1).
    $membership = Membership::factory()->create([
        'start_date' => '2026-08-15',
        'duration_days' => 30,
    ]);

    expect($membership->end_date->toDateString())->toBe('2026-09-13');
});

it('does not overwrite an explicit end_date', function () {
    // BR-003: the hook only computes the period when end_date is absent; an
    // explicit value (e.g. factory state for tests or past periods) survives.
    $membership = Membership::factory()->create([
        'start_date' => '2026-08-15',
        'duration_days' => 30,
        'end_date' => '2026-12-31',
    ]);

    expect($membership->end_date->toDateString())->toBe('2026-12-31');
});

it('exposes exactly the four status constants', function () {
    // BR-004, AM-03: pending / active / expired / cancelled; no other state
    // exists in the MVP.
    expect(Membership::STATUS_PENDING)->toBe('pending');
    expect(Membership::STATUS_ACTIVE)->toBe('active');
    expect(Membership::STATUS_EXPIRED)->toBe('expired');
    expect(Membership::STATUS_CANCELLED)->toBe('cancelled');
});

it('creates a membership as pending by default', function () {
    // BR-005: a new membership is never created directly as active.
    $membership = Membership::factory()->create();

    expect($membership->status)->toBe(Membership::STATUS_PENDING);
    expect($membership->isActive())->toBeFalse();
});

it('navigates the client and plan relationships', function () {
    // BR-002: a membership belongs to exactly one client and one plan; the
    // parent models navigate back to their memberships (FR-004, SPEC-003 §3).
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
    ]);

    expect($membership->client->is($client))->toBeTrue();
    expect($membership->plan->is($plan))->toBeTrue();

    expect($client->memberships()->pluck('memberships.id'))->toContain($membership->id);
    expect($plan->memberships()->pluck('memberships.id'))->toContain($membership->id);
});

it('rejects a membership with a nonexistent client via the FK constraint', function () {
    // BR-002, ERR-007: the memberships.client_id FK enforces the reference.
    $plan = Plan::factory()->create();

    expect(fn () => Membership::create([
        'client_id' => 999999,
        'plan_id' => $plan->id,
        'start_date' => '2026-08-15',
        'duration_days' => 30,
    ]))->toThrow(QueryException::class);
});

it('rejects a membership with a nonexistent plan via the FK constraint', function () {
    // BR-002, ERR-007: the memberships.plan_id FK enforces the reference.
    $client = Client::factory()->create();

    expect(fn () => Membership::create([
        'client_id' => $client->id,
        'plan_id' => 999999,
        'start_date' => '2026-08-15',
        'duration_days' => 30,
    ]))->toThrow(QueryException::class);
});

it('blocks hard deletion of a client or plan referenced by a membership', function () {
    // BR-014: restrictOnDelete guards historical membership data; clients and
    // plans referenced by memberships cannot be deleted.
    $membership = Membership::factory()->create();

    expect(fn () => $membership->client->delete())->toThrow(QueryException::class);
    expect(fn () => $membership->plan->delete())->toThrow(QueryException::class);

    expect(Client::find($membership->client_id))->not->toBeNull();
    expect(Plan::find($membership->plan_id))->not->toBeNull();
    expect(Membership::find($membership->id))->not->toBeNull();
});

it('cancels a pending or active membership and marks it cancelled', function () {
    // FR-006, BR-008, AC-10: cancellation is a manual terminal transition.
    $pending = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);
    $active = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);

    $pending->cancel();
    $active->cancel();

    expect($pending->status)->toBe(Membership::STATUS_CANCELLED);
    expect($active->status)->toBe(Membership::STATUS_CANCELLED);
    expect($active->isActive())->toBeFalse();
});

it('rejects cancelling an expired or cancelled membership', function () {
    // ERR-004, BR-009, AC-11: only pending and active memberships can be
    // cancelled; expired/cancelled are terminal.
    $expired = Membership::factory()->create(['status' => Membership::STATUS_EXPIRED]);
    $cancelled = Membership::factory()->create(['status' => Membership::STATUS_CANCELLED]);

    expect(fn () => $expired->cancel())->toThrow(DomainException::class);
    expect(fn () => $cancelled->cancel())->toThrow(DomainException::class);

    expect($expired->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect($cancelled->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
});

it('reports isActive and isExpired helpers', function () {
    // FR-007, BR-007/BR-009 helpers consumed by display and later specs.
    $active = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);
    $expired = Membership::factory()->create(['status' => Membership::STATUS_EXPIRED]);

    expect($active->isActive())->toBeTrue();
    expect($active->isExpired())->toBeFalse();
    expect($expired->isActive())->toBeFalse();
    expect($expired->isExpired())->toBeTrue();
});
