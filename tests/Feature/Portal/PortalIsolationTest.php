<?php

use App\Models\Booking;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Turno;
use App\Models\WorkoutLog;

/*
 * Client isolation (C-13) tests (SPEC-013 BR-002; AC-16; ERR-008, ERR-012).
 * Every portal response contains only the authenticated CLIENT's own records,
 * and a foreign record id on a mutation path is rejected without leaking data.
 */

beforeEach(function () {
    $this->withoutVite();
});

it('shows only the authenticated client own memberships', function () {
    $alice = clientWithUser(['full_name' => 'Alice Isolation']);
    $bob = clientWithUser(['full_name' => 'Bob Isolation']);

    Membership::factory()->create([
        'client_id' => $alice->id,
        'plan_id' => Plan::factory()->create(['name' => 'Alice Plan'])->id,
        'status' => Membership::STATUS_ACTIVE,
    ]);
    Membership::factory()->create([
        'client_id' => $bob->id,
        'plan_id' => Plan::factory()->create(['name' => 'Bob Plan'])->id,
        'status' => Membership::STATUS_ACTIVE,
    ]);

    $this->actingAs($alice->user)
        ->get('/portal/memberships')
        ->assertOk()
        ->assertSee('Alice Plan')
        ->assertDontSee('Bob Plan')
        ->assertDontSee('Bob Isolation');
});

it('shows only the authenticated client own bookings', function () {
    $alice = clientWithUser(['full_name' => 'Alice Isolation']);
    $bob = clientWithUser(['full_name' => 'Bob Isolation']);

    $aliceTurno = Turno::factory()->create();
    $bobTurno = Turno::factory()->create(['label' => 'Bob Turno Label']);

    Booking::factory()->create(['client_id' => $alice->id, 'turno_id' => $aliceTurno->id, 'booked_by' => null]);
    Booking::factory()->create(['client_id' => $bob->id, 'turno_id' => $bobTurno->id, 'booked_by' => null]);

    $this->actingAs($alice->user)
        ->get('/portal/bookings')
        ->assertOk()
        ->assertSee($aliceTurno->date->format('Y-m-d'))
        ->assertDontSee('Bob Turno Label');
});

it('shows only the authenticated client own workout history', function () {
    $alice = clientWithUser();
    $bob = clientWithUser();

    WorkoutLog::factory()->create(['client_id' => $alice->id, 'recorded_by' => $alice->user->id, 'actual_reps' => 10, 'notes' => 'Alice set']);
    WorkoutLog::factory()->create(['client_id' => $bob->id, 'recorded_by' => $bob->user->id, 'actual_reps' => 10, 'notes' => 'Bob secret set']);

    $this->actingAs($alice->user)
        ->get('/portal/workouts')
        ->assertOk()
        ->assertSee('Alice set')
        ->assertDontSee('Bob secret set');
});

it('rejects cancelling a booking belonging to another client with 404', function () {
    $alice = clientWithUser();
    $bob = clientWithUser();

    $bobBooking = Booking::factory()->create([
        'client_id' => $bob->id,
        'turno_id' => Turno::factory()->create()->id,
        'booked_by' => null,
    ]);

    $this->actingAs($alice->user)
        ->post(route('portal.bookings.cancel', $bobBooking))
        ->assertNotFound();

    expect($bobBooking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('never targets another client when booking a turno', function () {
    $alice = clientWithUser();
    $bob = clientWithUser();

    Membership::factory()->create([
        'client_id' => $alice->id,
        'plan_id' => Plan::factory()->create()->id,
        'status' => Membership::STATUS_ACTIVE,
    ]);

    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);

    $this->actingAs($alice->user)
        ->post(route('portal.turnos.book', $turno))
        ->assertRedirect(route('portal.bookings'));

    $booking = Booking::firstOrFail();

    expect($booking->client_id)->toBe($alice->id)
        ->and($booking->client_id)->not->toBe($bob->id)
        ->and($booking->booked_by)->toBeNull();
});
