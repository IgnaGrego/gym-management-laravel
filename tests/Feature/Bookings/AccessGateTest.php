<?php

use App\Actions\CreateBooking;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Turno;
use Illuminate\Validation\ValidationException;

/*
 * Booking access-gate tests (SPEC-007 BR-005; D-05 option 1; D-06 option 2;
 * ERR-006; AC-6; AF-005, BK-09).
 *
 * The gate is enforced by the CreateBooking Action (server-side, AGENTS.md
 * §17) using Client::hasQualifyingMembership() / Membership::scopeQualifying().
 * As with the SPEC-008 gate, tests use active memberships created directly via
 * factories with an explicit period.
 */

function bookingGateTurno(): Turno
{
    return Turno::factory()->create(['date' => now()->addDay()->toDateString()]);
}

it('rejects a client with no membership records', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();

    expect($client->hasQualifyingMembership())->toBeFalse();

    expect(fn () => app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects a client whose memberships are all pending', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_PENDING,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();

    expect(fn () => app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects a client whose memberships are all expired', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(30)->toDateString(),
    ]);

    expect(fn () => app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects a client whose memberships are all cancelled', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_CANCELLED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    expect(fn () => app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects a client whose only active membership has an expired end date', function () {
    // E-01 at booking time; no grace period (D-05 option 1): an active
    // membership whose end date passed does not qualify.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($client->accessDenialReason())->toBe(Client::ACCESS_DENIED_MEMBERSHIP_EXPIRED);

    expect(fn () => app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('allows a client with one qualifying active membership', function () {
    // AC-6 (BR-005): one active membership with end_date >= today qualifies.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    $booking = app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id);

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(Booking::count())->toBe(1);
});

it('allows a client with an active membership expiring today', function () {
    // BR-005: end_date >= today is inclusive of the last valid day.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $booking = app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id);

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('allows a client with several concurrent active memberships', function () {
    // AC-6 (D-06 option 2): at least one qualifying membership suffices.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
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

    $booking = app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id);

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('allows a client with one qualifying membership among non-qualifying ones', function () {
    // D-06 option 2: a single qualifying membership overrides non-qualifying ones.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
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

    $booking = app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id);

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('does not retroactively cancel a booking when the membership expires afterwards', function () {
    // AF-005, BK-09: the gate is evaluated at booking time only; a membership
    // expiring after the booking does not cancel it.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->toDateString(),
        'end_date' => today()->toDateString(),
    ]);

    $booking = app(CreateBooking::class)->handle($client->id, bookingGateTurno()->id);

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);

    $client->memberships()->update(['status' => Membership::STATUS_EXPIRED]);

    expect($client->hasQualifyingMembership())->toBeFalse();
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(Booking::count())->toBe(1);
});
