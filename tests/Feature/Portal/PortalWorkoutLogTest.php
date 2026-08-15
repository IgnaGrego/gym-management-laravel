<?php

use App\Models\Client;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\WorkoutLog;

/*
 * Portal workout self-logging tests (SPEC-013 FR-009, BR-006; AC-12, AC-13;
 * ERR-010; SPEC-011 BR-002..BR-006, BR-008).
 */

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Build an active routine version with one set row and assign it (active) to
 * the client; return the set row.
 */
function assignedSetRow(Client $client, string $exerciseName): RoutineExercise
{
    $exercise = Exercise::factory()->create(['name' => $exerciseName]);
    $routine = Routine::factory()->create([
        'name' => $exerciseName.' Routine',
        'status' => Routine::STATUS_ACTIVE,
    ]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);
    RoutineAssignment::factory()->create([
        'client_id' => $client->id,
        'routine_id' => $routine->id,
        'is_active' => true,
    ]);

    return $set;
}

it('lets a CLIENT log a workout against their assigned routine', function () {
    // AC-12 (FR-009, BR-006): recorded_by = own user, client_id derived.
    $client = clientWithUser();
    $set = assignedSetRow($client, 'Bench Press');

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'routine_exercise_id' => $set->id,
            'performed_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'actual_weight' => '62.5',
            'actual_reps' => 8,
            'notes' => 'Portal set',
        ])
        ->assertRedirect(route('portal.workouts'));

    $log = WorkoutLog::firstOrFail();

    expect($log->client_id)->toBe($client->id)
        ->and($log->routine_exercise_id)->toBe($set->id)
        ->and($log->exercise_id)->toBeNull()
        ->and($log->recorded_by)->toBe($client->user->id)
        ->and($log->actual_weight)->toBe('62.50')
        ->and($log->actual_reps)->toBe(8);
});

it('lets a CLIENT log a free workout against an active exercise', function () {
    // AC-12 (FR-009, BR-006, CP-03).
    $client = clientWithUser();
    $exercise = Exercise::factory()->create(['name' => 'Deadlift']);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $exercise->id,
            'performed_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'actual_weight' => '100',
            'actual_reps' => 5,
        ])
        ->assertRedirect(route('portal.workouts'));

    $log = WorkoutLog::firstOrFail();

    expect($log->client_id)->toBe($client->id)
        ->and($log->exercise_id)->toBe($exercise->id)
        ->and($log->routine_exercise_id)->toBeNull()
        ->and($log->recorded_by)->toBe($client->user->id);
});

it('rejects a log with both or neither exercise reference', function () {
    // AC-13 (ERR-010, ERR-001/ERR-002).
    $client = clientWithUser();
    $set = assignedSetRow($client, 'Bench Press');
    $exercise = Exercise::factory()->create(['name' => 'Pull-up']);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'routine_exercise_id' => $set->id,
            'exercise_id' => $exercise->id,
            'actual_reps' => 10,
        ])
        ->assertSessionHasErrors(['routine_exercise_id', 'exercise_id']);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'actual_reps' => 10,
        ])
        ->assertSessionHasErrors('routine_exercise_id');

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects a routine set from a version the client was never assigned to', function () {
    // AC-13 (ERR-010, ERR-003).
    $client = clientWithUser();
    assignedSetRow($client, 'Bench Press');

    // A set row of an active routine never assigned to this client.
    $foreignExercise = Exercise::factory()->create(['name' => 'Foreign Exercise']);
    $foreignRoutine = Routine::factory()->create([
        'name' => 'Foreign Routine',
        'status' => Routine::STATUS_ACTIVE,
    ]);
    $foreignDay = RoutineDay::factory()->create(['routine_id' => $foreignRoutine->id, 'day_number' => 1]);
    $foreignSet = RoutineExercise::factory()->create([
        'routine_day_id' => $foreignDay->id,
        'exercise_id' => $foreignExercise->id,
        'set_number' => 1,
        'target_reps' => 8,
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'routine_exercise_id' => $foreignSet->id,
            'actual_reps' => 8,
        ])
        ->assertSessionHasErrors('routine_exercise_id');

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects a free log referencing an inactive exercise', function () {
    // AC-13 (ERR-010, ERR-005).
    $client = clientWithUser();
    $inactive = Exercise::factory()->create(['name' => 'Retired Exercise', 'is_active' => false]);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $inactive->id,
            'actual_reps' => 10,
        ])
        ->assertSessionHasErrors('exercise_id');

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects invalid weight, reps and a future performed_at', function () {
    // AC-13 (ERR-010, WL-05/WL-06).
    $client = clientWithUser();
    $exercise = Exercise::factory()->create(['name' => 'Curl']);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $exercise->id,
            'actual_reps' => 0,
        ])
        ->assertSessionHasErrors('actual_reps');

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $exercise->id,
            'actual_weight' => '-1',
            'actual_reps' => 10,
        ])
        ->assertSessionHasErrors('actual_weight');

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $exercise->id,
            'performed_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'actual_reps' => 10,
        ])
        ->assertSessionHasErrors('performed_at');

    expect(WorkoutLog::count())->toBe(0);
});

it('accepts a backdated performed_at', function () {
    // AC-12 (WL-05: backdating allowed).
    $client = clientWithUser();
    $exercise = Exercise::factory()->create(['name' => 'Squat']);

    $this->actingAs($client->user)
        ->post(route('portal.workouts.store'), [
            'exercise_id' => $exercise->id,
            'performed_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'actual_reps' => 10,
        ])
        ->assertRedirect(route('portal.workouts'));

    expect(WorkoutLog::count())->toBe(1);
});
