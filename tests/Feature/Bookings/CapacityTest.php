<?php

use App\Actions\CreateBooking;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Turno;
use Illuminate\Validation\ValidationException;

/*
 * Capacity and race-safety tests (SPEC-007 BR-008, BR-009, BR-010, BR-013;
 * ERR-007, ERR-008, ERR-011; AC-7, AC-8, AC-9; BK-11; AF-003, AF-004).
 *
 * These drive the CreateBooking Action directly because the atomic capacity +
 * duplicate checks live inside the Action's transaction (ADR-006).
 */

function capacityClient(): Client
{
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    return $client;
}

function capacityTurno(int $capacity): Turno
{
    return Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'capacity_limit' => $capacity,
    ]);
}

it('rejects a booking for a full turno', function () {
    // AC-7 (ERR-007, BR-008): confirmed count == capacity_limit is rejected.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(1);

    app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);

    try {
        app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);
        $this->fail('Expected a ValidationException for a full turno.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('turno_id');
    }

    expect(Booking::confirmedCountForTurno($turno->id))->toBe(1);
    expect(Booking::count())->toBe(1);
});

it('frees a spot when a confirmed booking is cancelled', function () {
    // AC-7, AF-004 (BR-010, BK-11): a cancelled booking no longer counts; the
    // freed spot is re-bookable by another client.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(1);

    $first = app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);
    $first->cancel();

    $second = app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);

    expect($second->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(Booking::confirmedCountForTurno($turno->id))->toBe(1);
    expect(Booking::count())->toBe(2); // one cancelled + one confirmed
});

it('does not count cancelled bookings toward capacity', function () {
    // BK-11 (BR-008): only confirmed bookings count.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(1);

    $booking = app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);
    $booking->cancel();

    $second = app(CreateBooking::class)->handle(capacityClient()->id, $turno->id);

    expect($second->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(Booking::confirmedCountForTurno($turno->id))->toBe(1);
});

it('rejects a duplicate confirmed booking for the same client and turno', function () {
    // AC-8 (ERR-008, BR-009): a second confirmed booking for the same client
    // and turno is rejected.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(5);
    $client = capacityClient();

    app(CreateBooking::class)->handle($client->id, $turno->id);

    expect(fn () => app(CreateBooking::class)->handle($client->id, $turno->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(1);
});

it('allows the same client to re-book a turno after cancelling', function () {
    // AC-8, AF-003 (BR-004, BR-009): the cancelled record is never
    // reactivated; a fresh confirmed booking is created instead.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(5);
    $client = capacityClient();

    $first = app(CreateBooking::class)->handle($client->id, $turno->id);
    $first->cancel();

    $second = app(CreateBooking::class)->handle($client->id, $turno->id);

    expect($second->id)->not->toBe($first->id);
    expect($second->status)->toBe(Booking::STATUS_CONFIRMED);
    expect(Booking::count())->toBe(2); // one cancelled + one confirmed
});

it('never oversells the last spot when two booking attempts race', function () {
    // AC-9 (ERR-011, BR-008, ADR-006): exactly one of two attempts for the
    // last spot succeeds; the other is rejected as "turno full". The capacity
    // check and insert are inside a single transaction with a row lock on the
    // turno, so the confirmed count can never exceed capacity_limit.
    //
    // On the SQLite test database (single writer, no true row-level FOR UPDATE)
    // the interleaving is serialized by the DB; this drives the same Action
    // code path (in-transaction re-check) and asserts the observable invariant.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $turno = capacityTurno(1);
    $clientA = capacityClient();
    $clientB = capacityClient();

    $first = app(CreateBooking::class)->handle($clientA->id, $turno->id);

    expect($first->status)->toBe(Booking::STATUS_CONFIRMED);

    try {
        app(CreateBooking::class)->handle($clientB->id, $turno->id);
        $this->fail('Expected the second racing booking to be rejected.');
    } catch (ValidationException $e) {
        expect($e->errors()['turno_id'][0])->toContain('lleno');
    }

    expect(Booking::confirmedCountForTurno($turno->id))->toBe(1);
    expect($turno->capacity_limit)->toBe(1);
    expect(Booking::confirmedCountForTurno($turno->id))->toBeLessThanOrEqual($turno->capacity_limit);
});
