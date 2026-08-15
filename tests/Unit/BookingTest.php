<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Role;
use App\Models\Turno;
use Illuminate\Database\QueryException;

/*
 * Booking model unit tests (SPEC-007 BR-002, BR-003, BR-004, BR-008, BR-009,
 * BR-011, BR-014; FR-001, FR-004, FR-007; ERR-009, ERR-012; BK-03, BK-11,
 * BK-12; AC-10, AC-13; the one-confirmed-per-client-per-turno invariant).
 */

it('exposes exactly the two status constants and no completed constant', function () {
    // BR-003, BK-03: confirmed / cancelled; `completed` is reserved for the
    // SPEC-008 tie-in and is intentionally not a reachable state here.
    expect(Booking::STATUS_CONFIRMED)->toBe('confirmed');
    expect(Booking::STATUS_CANCELLED)->toBe('cancelled');
    expect(defined(Booking::class.'::STATUS_COMPLETED'))->toBeFalse();
});

it('creates a booking as confirmed by default with booked_at set to now', function () {
    // FR-001, BR-003, BK-12: a new booking is never created in any other state;
    // the factory defaults booked_at to now and notes to null.
    $booking = Booking::factory()->create();

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
    expect($booking->booked_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($booking->notes)->toBeNull();
});

it('casts booked_at to a Carbon datetime', function () {
    // BR-002 (the gym-local reservation timestamp; SPEC-006 BR-011 convention).
    $booking = Booking::factory()->create([
        'booked_at' => '2026-08-15 08:30:00',
    ]);

    expect($booking->booked_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($booking->booked_at->toDateTimeString())->toBe('2026-08-15 08:30:00');
});

it('navigates the client, turno and bookedBy relationships and their inverses', function () {
    // BR-002, C-02, BK-12: a booking belongs to one client and one turno; the
    // client and turno aggregate their bookings.
    $client = Client::factory()->create();
    $turno = Turno::factory()->create();
    $staff = userWithRoles([Role::ADMIN]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'booked_by' => $staff->id,
    ]);

    expect($booking->client->is($client))->toBeTrue();
    expect($booking->turno->is($turno))->toBeTrue();
    expect($booking->bookedBy->is($staff))->toBeTrue();

    expect($client->bookings()->pluck('bookings.id'))->toContain($booking->id);
    expect($turno->bookings()->pluck('bookings.id'))->toContain($booking->id);
});

it('scopes to confirmed bookings only', function () {
    // BR-008, BK-11: scopeConfirmed is the capacity-counting predicate.
    $confirmed = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['status' => Booking::STATUS_CANCELLED]);

    expect(Booking::confirmed()->pluck('id'))->toHaveCount(1);
    expect(Booking::confirmed()->pluck('id'))->toContain($confirmed->id);
});

it('cancels a confirmed booking and is terminal', function () {
    // FR-004, BR-004, ERR-009: confirmed -> cancelled; a cancelled booking
    // cannot be cancelled again (no un-cancel, BK-06).
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED]);

    $booking->cancel();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLED);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);

    expect(fn () => $booking->cancel())->toThrow(DomainException::class);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('rejects cancelling a cancelled booking', function () {
    // ERR-009, BR-004: only a confirmed booking can be cancelled.
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CANCELLED]);

    expect(fn () => $booking->cancel())->toThrow(DomainException::class);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('cancelForTurno cancels exactly the turno confirmed bookings and is idempotent', function () {
    // FR-007, BR-014, NC-01: the bulk auto-cancel touches only confirmed rows
    // of the target turno and leaves other turnos' bookings untouched.
    $turno = Turno::factory()->create();
    $other = Turno::factory()->create();
    $a = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    $b = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    $alreadyCancelled = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);
    $otherConfirmed = Booking::factory()->create(['turno_id' => $other->id, 'status' => Booking::STATUS_CONFIRMED]);

    $count = Booking::cancelForTurno($turno);

    expect($count)->toBe(2);
    expect($a->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($b->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($alreadyCancelled->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($otherConfirmed->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);

    // Idempotent: a second call affects zero rows.
    expect(Booking::cancelForTurno($turno))->toBe(0);
});

it('confirmedCountForTurno counts only confirmed bookings', function () {
    // BR-008, BK-11: cancelled bookings do not count toward capacity.
    $turno = Turno::factory()->create();
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);

    expect(Booking::confirmedCountForTurno($turno->id))->toBe(2);
});

it('assertCapacityLimitNotBelowConfirmed rejects lowering below the confirmed count', function () {
    // BR-014, ERR-012, NC-01: the capacity_limit cannot be lowered below the
    // number of confirmed bookings; cancelled bookings do not count.
    $turno = Turno::factory()->create(['capacity_limit' => 5]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);

    expect(fn () => $turno->assertCapacityLimitNotBelowConfirmed(1))->toThrow(DomainException::class);

    // Equal to or above the confirmed count is allowed.
    $turno->assertCapacityLimitNotBelowConfirmed(2);
    $turno->assertCapacityLimitNotBelowConfirmed(5);

    expect(Booking::confirmedCountForTurno($turno->id))->toBe(2);
});

it('enforces one confirmed booking per client per turno at the database level', function () {
    // BR-009: the partial unique index is the DB backstop for the duplicate
    // invariant; a second confirmed booking for the same pair is rejected.
    $client = Client::factory()->create();
    $turno = Turno::factory()->create();

    Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    expect(fn () => Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
    ]))->toThrow(QueryException::class);

    expect(Booking::count())->toBe(1);
});

it('allows multiple cancelled bookings for the same client and turno', function () {
    // BR-009, AF-003: the partial index applies only to confirmed rows; after
    // cancelling, a fresh confirmed booking is a new record, never a
    // reactivation of the cancelled one.
    $client = Client::factory()->create();
    $turno = Turno::factory()->create();

    Booking::factory()->create(['client_id' => $client->id, 'turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);
    Booking::factory()->create(['client_id' => $client->id, 'turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);

    expect(Booking::where('client_id', $client->id)->where('turno_id', $turno->id)->count())->toBe(2);
});

it('blocks hard deletion of a client, user or turno referenced by a booking', function () {
    // BR-011 (preservation pattern): restrictOnDelete guards historical booking
    // data; referenced clients, users and turnos cannot be hard-deleted.
    $booking = Booking::factory()->create();

    expect(fn () => $booking->client->delete())->toThrow(QueryException::class);
    expect(fn () => $booking->turno->delete())->toThrow(QueryException::class);
    expect(fn () => $booking->bookedBy->delete())->toThrow(QueryException::class);

    expect(Client::find($booking->client_id))->not->toBeNull();
    expect(Turno::find($booking->turno_id))->not->toBeNull();
    expect(Booking::find($booking->id))->not->toBeNull();
});
