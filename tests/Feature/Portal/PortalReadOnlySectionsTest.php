<?php

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\Turno;
use App\Models\User;
use App\Models\WorkoutLog;

/*
 * Portal read-only section tests (SPEC-013 FR-002..FR-005, FR-008, FR-010;
 * AC-2, AC-3, AC-4, AC-5, AC-11, AC-14; CP-06, CP-07, CP-05).
 */

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Build an active routine with one day and one set row, assigned (active) to
 * the given client, and return the routine (SPEC-010 model, assigned directly).
 */
function assignedRoutine(Client $client, string $exerciseName): Routine
{
    $exercise = Exercise::factory()->create(['name' => $exerciseName]);
    $routine = Routine::factory()->create([
        'name' => $exerciseName.' Routine',
        'status' => Routine::STATUS_ACTIVE,
    ]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
        'rest_seconds' => 90,
        'notes' => 'Prescription note',
    ]);
    RoutineAssignment::factory()->create([
        'client_id' => $client->id,
        'routine_id' => $routine->id,
        'is_active' => true,
    ]);

    return $routine;
}

it('lists own memberships in chronological order by start date', function () {
    // AC-2 (FR-002).
    $client = clientWithUser();

    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => Plan::factory()->create(['name' => 'Late Plan'])->id,
        'start_date' => today()->subDay()->toDateString(),
        'status' => Membership::STATUS_ACTIVE,
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => Plan::factory()->create(['name' => 'Early Plan'])->id,
        'start_date' => today()->subDays(10)->toDateString(),
        'status' => Membership::STATUS_EXPIRED,
    ]);

    $this->actingAs($client->user)
        ->get('/portal/memberships')
        ->assertOk()
        ->assertSeeInOrder(['Early Plan', 'Late Plan'])
        ->assertSee(Membership::STATUS_ACTIVE)
        ->assertSee(Membership::STATUS_EXPIRED);
});

it('lists own cuotas and payments with amount, status, method and date only', function () {
    // AC-3 (FR-003, CP-06): staff audit fields (reference / notes / recorded_by)
    // are NOT shown to the client.
    $client = clientWithUser();
    $staff = userWithRoles([Role::TRAINER]);

    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => Plan::factory()->create(['name' => 'Pay Plan'])->id,
        'status' => Membership::STATUS_ACTIVE,
    ]);
    $cuota = $membership->cuota;

    Payment::factory()->create([
        'cuota_id' => $cuota->id,
        'amount' => '1500.00',
        'method' => Payment::METHOD_TRANSFER,
        'payment_date' => today()->subDays(2)->toDateString(),
        'reference' => 'REF-12345',
        'notes' => 'Internal audit note',
        'recorded_by' => $staff->id,
    ]);

    $this->actingAs($client->user)
        ->get('/portal/payments')
        ->assertOk()
        ->assertSee('Pay Plan')
        ->assertSee('1500.00')
        ->assertSee(Payment::METHOD_TRANSFER)
        ->assertSee(Payment::STATUS_CONFIRMED)
        ->assertDontSee('REF-12345')
        ->assertDontSee('Internal audit note')
        ->assertDontSee($staff->name);
});

it('lists own attendance in chronological order without the recording staff', function () {
    // AC-4 (FR-004, CP-07): recorded_by is not shown.
    $client = clientWithUser();
    $staff = userWithRoles([Role::TRAINER]);

    $turno = Turno::factory()->create(['date' => today()->toDateString()]);

    Attendance::factory()->create([
        'client_id' => $client->id,
        'attended_at' => today()->subDays(2)->setTime(9, 0),
        'turno_id' => $turno->id,
        'recorded_by' => $staff->id,
    ]);
    Attendance::factory()->create([
        'client_id' => $client->id,
        'attended_at' => today()->subDay()->setTime(9, 0),
        'recorded_by' => $staff->id,
    ]);

    $this->actingAs($client->user)
        ->get('/portal/attendance')
        ->assertOk()
        ->assertSeeInOrder([
            today()->subDays(2)->setTime(9, 0)->format('Y-m-d H:i'),
            today()->subDay()->setTime(9, 0)->format('Y-m-d H:i'),
        ])
        ->assertSee($turno->date->format('Y-m-d'))
        ->assertDontSee($staff->name);
});

it('lists own bookings with status and turno details', function () {
    // AC-5 (FR-005).
    $client = clientWithUser();

    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);
    Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'status' => Booking::STATUS_CONFIRMED,
        'booked_by' => null,
    ]);
    Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => Turno::factory()->create(['date' => now()->addDays(2)->toDateString()])->id,
        'status' => Booking::STATUS_CANCELLED,
        'booked_by' => null,
    ]);

    $this->actingAs($client->user)
        ->get('/portal/bookings')
        ->assertOk()
        ->assertSee(Booking::STATUS_CONFIRMED)
        ->assertSee(Booking::STATUS_CANCELLED)
        ->assertSee($turno->date->format('Y-m-d'))
        ->assertSee('Cancel booking');
});

it('shows the current routine read-only with days and set rows', function () {
    // AC-11 (FR-008, BR-007).
    $client = clientWithUser();
    assignedRoutine($client, 'Bench Press');

    $this->actingAs($client->user)
        ->get('/portal/routine')
        ->assertOk()
        ->assertSee('Bench Press Routine')
        ->assertSee('Bench Press')
        ->assertSee('Day 1')
        ->assertSee('10')
        ->assertSee('60.00')
        ->assertSee('Prescription note');
});

it('shows an empty state when the client has no active assignment', function () {
    // AC-11 (FR-008, AF-004).
    $client = clientWithUser();

    $this->actingAs($client->user)
        ->get('/portal/routine')
        ->assertOk()
        ->assertSee('You have no assigned routine yet.');
});

it('lists own workout history without the staff target/actual comparison', function () {
    // AC-14 (FR-010, CP-05): the flat history only; no Target columns.
    $client = clientWithUser();
    $exercise = Exercise::factory()->create(['name' => 'Squat']);

    WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $client->user->id,
        'exercise_id' => $exercise->id,
        'performed_at' => today()->subDay()->setTime(9, 0),
        'actual_weight' => '100.00',
        'actual_reps' => 5,
        'notes' => 'Heavy set',
    ]);

    $this->actingAs($client->user)
        ->get('/portal/workouts')
        ->assertOk()
        ->assertSee('Squat')
        ->assertSee('Heavy set')
        ->assertSee('100.00')
        ->assertSee('5')
        ->assertDontSee('Target');
});
