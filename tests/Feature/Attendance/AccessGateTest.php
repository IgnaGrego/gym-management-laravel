<?php

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;

/*
 * Access gate tests (SPEC-008 BR-003, BR-004; D-05 option 1; D-06 option 2;
 * ERR-003, ERR-004; AC-2, AC-3, AC-4; AF-006).
 *
 * The gate is tested in isolation on the model helpers consumed by the
 * create-form closure rule: Client::hasQualifyingMembership(),
 * Client::accessDenialReason() and Membership::scopeQualifying(). As SPEC-005
 * is BLOCKED, admin-created memberships remain `pending`; the gate tests use
 * active memberships created directly via factories with an explicit period,
 * the same stance as SPEC-007.
 */

it('rejects a client with no membership records', function () {
    // AC-2 (ERR-003): the gate denial reason is "no membership".
    $client = Client::factory()->create();

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_NO_MEMBERSHIP);
});

it('rejects a client whose memberships are all pending', function () {
    // AC-3 (ERR-004): pending memberships do not qualify.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_PENDING,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP);
});

it('rejects a client whose memberships are all expired', function () {
    // AC-3 (ERR-004): expired memberships do not qualify.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(30)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP);
});

it('rejects a client whose memberships are all cancelled', function () {
    // AC-3 (ERR-004): cancelled memberships do not qualify.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_CANCELLED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP);
});

it('rejects an active membership whose end date has passed', function () {
    // AC-3 (E-01 at the door; no grace period — D-05 option 1): an `active`
    // membership whose end date is before today does not qualify even while
    // the memberships:expire command has not run yet (SPEC-004 BR-007 /
    // ADR-004 window). The reason is "membership expired".
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_MEMBERSHIP_EXPIRED);
});

it('allows a client with one active membership expiring today', function () {
    // BR-003: end_date >= today is the boundary; an active membership whose
    // end date is today still qualifies (the gate is inclusive of the last
    // valid day).
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeTrue();
    expect($client->accessDenialReason())->toBeNull();
});

it('allows a client with one qualifying active membership', function () {
    // AC-4 (BR-003): one active membership with end_date >= today qualifies.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeTrue();
    expect($client->accessDenialReason())->toBeNull();
});

it('allows a client with several concurrent active memberships', function () {
    // AC-4 (D-06 option 2): at least one qualifying membership suffices; there
    // is no "primary membership" selection.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeTrue();
    expect($client->accessDenialReason())->toBeNull();
});

it('allows a client with one qualifying membership among non-qualifying ones', function () {
    // D-06 option 2: a single qualifying membership overrides any
    // non-qualifying ones.
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(30)->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(10)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeTrue();
    expect($client->accessDenialReason())->toBeNull();
});

it('scopes qualifying memberships to active with a not-passed end date', function () {
    // BR-003: the Membership::scopeQualifying predicate (status active AND
    // end_date >= today) — the same rule SPEC-007 BR-005 consumes when
    // unblocked.
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

    $qualifying = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(10)->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_PENDING,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(10)->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(30)->toDateString(),
    ]);

    expect(Membership::qualifying()->pluck('memberships.id'))->toHaveCount(1);
    expect(Membership::qualifying()->pluck('memberships.id'))->toContain($qualifying->id);
});

it('does not retroactively invalidate a recorded check-in when the membership expires afterwards', function () {
    // BR-004, AF-006 (E-01): the gate is evaluated at check-in time only. A
    // client checked in while qualifying keeps the record even if the
    // membership expires later that day or the next day.
    $client = Client::factory()->create();
    $staff = userWithRoles([Role::ADMIN]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeTrue();

    $attendance = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $staff->id,
        'attended_at' => now(),
    ]);

    // The membership expires after the check-in (the end date passes and/or
    // the daily memberships:expire command materializes the expired state).
    $client->memberships()->update(['status' => Membership::STATUS_EXPIRED]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($attendance->fresh()->client_id)->toBe($client->id);
    expect($attendance->fresh()->attended_at)->not->toBeNull();
    expect(Attendance::count())->toBe(1);
});
