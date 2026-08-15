<?php

use App\Models\Booking;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Turno;

/*
 * Portal booking self-service tests (SPEC-013 FR-006, FR-007, BR-004, BR-005;
 * AC-6..AC-10; ERR-003..ERR-009). The portal reuses the CreateBooking Action
 * (atomicity covered by tests/Feature/Bookings/CapacityTest.php) and
 * Booking::cancel().
 */

beforeEach(function () {
    $this->withoutVite();
});

function qualifyingMembership(int $clientId): Membership
{
    return Membership::factory()->create([
        'client_id' => $clientId,
        'plan_id' => Plan::factory()->create()->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);
}

function futureTurno(): Turno
{
    return Turno::factory()->create(['date' => now()->addDay()->toDateString()]);
}

it('lets a CLIENT book a turno for themselves with booked_by null', function () {
    // AC-6 (FR-006, BR-004, CP-09, AF-007).
    $client = clientWithUser();
    qualifyingMembership($client->id);
    $turno = futureTurno();

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.bookings'));

    $booking = Booking::firstOrFail();

    expect($booking->client_id)->toBe($client->id)
        ->and($booking->turno_id)->toBe($turno->id)
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->booked_by)->toBeNull();
});

it('rejects a CLIENT without a qualifying membership with the gate reason', function () {
    // AC-7 (FR-006, ERR-003, D-05 option 1).
    $client = clientWithUser();
    $turno = futureTurno();

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.turnos'))
        ->assertSessionHasErrors('client_id');

    expect(Booking::count())->toBe(0);
});

it('rejects a full turno', function () {
    // AC-8 (ERR-004).
    $client = clientWithUser();
    qualifyingMembership($client->id);
    $other = clientWithUser();
    qualifyingMembership($other->id);

    $turno = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'capacity_limit' => 1,
    ]);
    Booking::factory()->create([
        'client_id' => $other->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
        'booked_by' => null,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.turnos'))
        ->assertSessionHasErrors('turno_id');

    expect(Booking::count())->toBe(1);
});

it('rejects an inactive turno', function () {
    // AC-8 (ERR-005).
    $client = clientWithUser();
    qualifyingMembership($client->id);
    $turno = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'status' => Turno::STATUS_INACTIVE,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.turnos'))
        ->assertSessionHasErrors('turno_id');

    expect(Booking::count())->toBe(0);
});

it('rejects a turno beyond the lead-time window', function () {
    // AC-8 (ERR-005).
    $client = clientWithUser();
    qualifyingMembership($client->id);
    $turno = Turno::factory()->create(['date' => now()->addDays(8)->toDateString()]);

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.turnos'))
        ->assertSessionHasErrors('turno_id');

    expect(Booking::count())->toBe(0);
});

it('rejects a duplicate confirmed booking', function () {
    // AC-8 (ERR-006).
    $client = clientWithUser();
    qualifyingMembership($client->id);
    $turno = futureTurno();

    Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
        'booked_by' => null,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.turnos'))
        ->assertSessionHasErrors('turno_id');

    expect(Booking::count())->toBe(1);
});

it('lets a CLIENT cancel their own confirmed booking and reopens the spot', function () {
    // AC-9 (FR-007, BR-005).
    $client = clientWithUser();
    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
        'booked_by' => null,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.bookings.cancel', $booking))
        ->assertRedirect(route('portal.bookings'));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and(Booking::confirmedCountForTurno($turno->id))->toBe(0);
});

it('rejects cancelling an already-cancelled booking', function () {
    // AC-10 (ERR-009).
    $client = clientWithUser();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => Turno::factory()->create()->id,
        'status' => Booking::STATUS_CANCELLED,
        'booked_by' => null,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.bookings.cancel', $booking))
        ->assertRedirect(route('portal.bookings'))
        ->assertSessionHasErrors('booking');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});
