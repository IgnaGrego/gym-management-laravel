<?php

use App\Actions\AssignRoutine;
use App\Actions\VersionRoutine;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/*
 * WorkoutLog model and validation-rule unit tests (SPEC-011 BR-001, BR-002,
 * BR-003, BR-004, BR-005, BR-006, BR-008, BR-009, BR-010; WL-05, WL-06,
 * WL-11; C-10, C-11; ERR-001, ERR-002, ERR-004; AC-2, AC-6, AC-10).
 */

it('navigates the client, routineExercise, exercise and recordedBy relationships', function () {
    // BR-002, BR-009: a log belongs to exactly one client, references either a
    // prescribed set row or a free catalogue exercise, and records the staff
    // User who entered it.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
    $routine = Routine::factory()->create(['name' => 'Push Day', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);
    app(AssignRoutine::class)->handle($routine, [$client], now()->subDay());

    $freeExercise = Exercise::factory()->create(['name' => 'Pull-up']);

    $routineLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
    ]);
    $freeLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $freeExercise->id,
    ]);

    expect($routineLog->client->is($client))->toBeTrue();
    expect($routineLog->recordedBy->is($admin))->toBeTrue();
    expect($routineLog->routineExercise->is($set))->toBeTrue();
    expect($routineLog->exercise)->toBeNull();

    expect($freeLog->exercise->is($freeExercise))->toBeTrue();
    expect($freeLog->routineExercise)->toBeNull();
});

it('navigates a client to their workout logs and scopes to one client', function () {
    // C-02, FR-003: a client aggregates workout logs; scopeForClient returns
    // only that client's logs.
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $first = WorkoutLog::factory()->create(['client_id' => $client->id]);
    $second = WorkoutLog::factory()->create(['client_id' => $client->id]);
    $otherLog = WorkoutLog::factory()->create(['client_id' => $otherClient->id]);

    expect($client->workoutLogs()->pluck('workout_logs.id'))->toContain($first->id);
    expect($client->workoutLogs()->pluck('workout_logs.id'))->toContain($second->id);
    expect($client->workoutLogs()->pluck('workout_logs.id'))->not->toContain($otherLog->id);

    expect(WorkoutLog::forClient($client->id)->pluck('id'))
        ->toHaveCount(2)
        ->toContain($first->id)
        ->not->toContain($otherLog->id);
});

it('orders a client workout history by performed_at', function () {
    // FR-003: the consuming query orders chronologically by performed_at (the
    // grouping key of a "workout", WL-01).
    $client = Client::factory()->create();
    $older = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'performed_at' => now()->subDays(2)->setTime(9, 0),
    ]);
    $newer = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'performed_at' => now()->subDay()->setTime(9, 0),
    ]);

    expect($client->workoutLogs()->orderBy('performed_at')->pluck('id')->all())
        ->toBe([$older->id, $newer->id]);
});

it('hasRoutineAssignmentTo reflects active and historical assignments and excludes drafts', function () {
    // BR-004, ERR-003, WL-07: the predicate is true for the active assignment
    // version and stays true after reassignment (historical assignment
    // preserved, SPEC-010 BR-008/AR-09); never-assigned and draft versions
    // are false.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Program', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    expect($client->hasRoutineAssignmentTo($v1->id))->toBeTrue();

    $v2 = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Program v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $set->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
            ]],
        ],
    ]);

    app(AssignRoutine::class)->handle($v2, [$client]);

    expect($client->hasRoutineAssignmentTo($v1->id))->toBeTrue(); // historical
    expect($client->hasRoutineAssignmentTo($v2->id))->toBeTrue(); // active
    expect($client->hasRoutineAssignmentTo($v1->id + $v2->id + 1000))->toBeFalse(); // never assigned

    $draft = Routine::factory()->create(['name' => 'Draft', 'created_by' => $admin->id]);

    expect($client->hasRoutineAssignmentTo($draft->id))->toBeFalse();
});

it('casts performed_at to Carbon, actual_weight to decimal:2 and actual_reps to integer', function () {
    // BR-008, WL-05, WL-06: the performed timestamp is Carbon; the weight uses
    // the ADR-003 decimal cast (Eloquent returns strings); reps are integer.
    $log = WorkoutLog::factory()->create([
        'performed_at' => '2026-08-15 09:30:00',
        'actual_weight' => '62.5',
        'actual_reps' => 8,
    ]);

    expect($log->performed_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($log->performed_at->toDateTimeString())->toBe('2026-08-15 09:30:00');
    expect($log->actual_weight)->toBe('62.50');
    expect($log->actual_reps)->toBe(8);
});

it('enforces the exactly-one-reference invariant through referenceRules', function () {
    // BR-002, ERR-001: the shared rules reject both-set and neither-set
    // payloads and accept each single-reference case (AC-3).
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create();
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    $both = Validator::make([
        'routine_exercise_id' => $set->id,
        'exercise_id' => $exercise->id,
    ], WorkoutLog::referenceRules());

    expect($both->fails())->toBeTrue();
    expect($both->errors()->has('routine_exercise_id'))->toBeTrue();
    expect($both->errors()->has('exercise_id'))->toBeTrue();

    $neither = Validator::make([
        'routine_exercise_id' => null,
        'exercise_id' => null,
    ], WorkoutLog::referenceRules());

    expect($neither->fails())->toBeTrue();
    expect($neither->errors()->has('routine_exercise_id'))->toBeTrue();
    expect($neither->errors()->has('exercise_id'))->toBeTrue();

    $routineOnly = Validator::make([
        'routine_exercise_id' => $set->id,
        'exercise_id' => null,
    ], WorkoutLog::referenceRules());

    expect($routineOnly->fails())->toBeFalse();

    $freeOnly = Validator::make([
        'routine_exercise_id' => null,
        'exercise_id' => $exercise->id,
    ], WorkoutLog::referenceRules());

    expect($freeOnly->fails())->toBeFalse();
});

it('rejects a nonexistent reference through referenceRules', function () {
    // ERR-002: both reference columns are foreign keys.
    $validator = Validator::make([
        'routine_exercise_id' => 999999,
        'exercise_id' => null,
    ], WorkoutLog::referenceRules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('routine_exercise_id'))->toBeTrue();
});

it('requires recorded_by via the database not-null constraint', function () {
    // BR-009, WL-11: every log stores the staff User who recorded it; a record
    // without it fails the NOT NULL constraint.
    $client = Client::factory()->create();

    expect(fn () => WorkoutLog::create([
        'client_id' => $client->id,
        'performed_at' => now(),
        'actual_reps' => 10,
    ]))->toThrow(QueryException::class);
});

it('creates no other record when creating a workout log', function () {
    // BR-003, AC-2 (C-10): recording a log touches only the workout_logs
    // table; no routine, day, set-row, assignment, exercise or client record
    // is created or modified.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Free Exercise']);

    WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $exercise->id,
    ]);

    expect(WorkoutLog::count())->toBe(1);
    expect(Client::count())->toBe(1);
    expect(User::count())->toBe(1); // only the recording staff user
    expect(Exercise::count())->toBe(1);
    expect(DB::table('routines')->count())->toBe(0);
    expect(DB::table('routine_days')->count())->toBe(0);
    expect(DB::table('routine_exercises')->count())->toBe(0);
    expect(DB::table('routine_assignments')->count())->toBe(0);
});

it('keeps the routine_exercise_id version-stable when a routine is versioned', function () {
    // BR-004, AC-10 (D-12 option 3): a log's reference belongs to the version
    // the client was assigned to at log time and survives a later versioning
    // operation unchanged — the prescription display reads the old version's
    // immutable set row.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Squat']);
    $v1 = Routine::factory()->create(['name' => 'Leg Day', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);

    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
        'actual_weight' => 62.5,
        'actual_reps' => 8,
    ]);

    expect($client->hasRoutineAssignmentTo($v1->id))->toBeTrue();

    $v2 = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Leg Day v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $set->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 12, 'target_weight' => 62.5],
            ]],
        ],
    ]);

    expect($v1->fresh()->status)->toBe(Routine::STATUS_ARCHIVED);
    expect(WorkoutLog::count())->toBe(1);

    $log->refresh();

    // The log is unchanged and still references the old version's set row.
    expect($log->routine_exercise_id)->toBe($set->id);
    expect($log->actual_weight)->toBe('62.50');
    expect($log->actual_reps)->toBe(8);

    // The assignment history is preserved, so the reference is still valid
    // (active on v1 until reassignment; the predicate covers it).
    expect($client->hasRoutineAssignmentTo($v1->id))->toBeTrue();

    // The prescription display reads the old version's immutable row.
    expect($log->routineExercise->target_reps)->toBe(10);
    expect($log->routineExercise->target_weight)->toBe('60.00');
    expect($log->exerciseName())->toBe('Squat');
});

it('exposes the free-log active-exercise rule through the exercise predicates', function () {
    // BR-005, AC-6: new free logs may only reference active exercises; the
    // ERR-005 check consumes Exercise::scopeActive() / isActive().
    $active = Exercise::factory()->create(['name' => 'Active Exercise', 'is_active' => true]);
    $inactive = Exercise::factory()->create(['name' => 'Inactive Exercise', 'is_active' => false]);

    expect($active->isActive())->toBeTrue();
    expect($inactive->isActive())->toBeFalse();
    expect(Exercise::active()->whereKey($active->id)->exists())->toBeTrue();
    expect(Exercise::active()->whereKey($inactive->id)->exists())->toBeFalse();
});

it('keeps logs unchanged and still displaying when a referenced exercise is deactivated', function () {
    // BR-005, AC-11 (AF-003): deactivating an exercise never creates,
    // modifies or deletes any log; a log referencing the now-inactive
    // exercise still displays, reading the exercise's current catalogue
    // attributes live (WL-08).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Deadlift']);
    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $exercise->id,
        'actual_weight' => 100,
        'actual_reps' => 5,
    ]);

    $exercise->update(['is_active' => false]);

    expect(WorkoutLog::count())->toBe(1);
    $log->refresh();

    expect($log->exercise_id)->toBe($exercise->id);
    expect($log->actual_reps)->toBe(5);
    expect($log->actual_weight)->toBe('100.00');
    expect($log->exerciseName())->toBe('Deadlift');
});

it('exposes the exercise display name for both reference kinds', function () {
    // FR-003, WL-08: exerciseName() reads the exercise the same way whether
    // the reference is a prescribed set row or a free exercise.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
    $routine = Routine::factory()->create(['name' => 'Push Day', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    app(AssignRoutine::class)->handle($routine, [$client], now()->subDay());

    $free = Exercise::factory()->create(['name' => 'Cable Row']);

    $routineLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
    ]);
    $freeLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $free->id,
    ]);

    expect($routineLog->exerciseName())->toBe('Bench Press');
    expect($freeLog->exerciseName())->toBe('Cable Row');
});

it('has no status column, no sets-count field and no membership-gating logic', function () {
    // BR-001, BR-006, BR-010: a log is a flat per-set event record with no
    // status column and no actual_sets count field; logging has no
    // membership/access precondition.
    expect(Schema::hasColumn('workout_logs', 'status'))->toBeFalse();
    expect(Schema::hasColumn('workout_logs', 'actual_sets'))->toBeFalse();
    expect(method_exists(WorkoutLog::class, 'hasQualifyingMembership'))->toBeFalse();
});

it('creates the expected columns and indexes', function () {
    // SPEC-011 §10 / architecture §6: the exact column set and the suggested
    // indexes (client_id, performed_at), routine_exercise_id, exercise_id,
    // recorded_by.
    expect(Schema::hasColumns('workout_logs', [
        'id',
        'client_id',
        'performed_at',
        'routine_exercise_id',
        'exercise_id',
        'actual_weight',
        'actual_reps',
        'notes',
        'recorded_by',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasIndex('workout_logs', 'workout_logs_client_id_performed_at_index'))->toBeTrue();
    expect(Schema::hasIndex('workout_logs', 'workout_logs_routine_exercise_id_index'))->toBeTrue();
    expect(Schema::hasIndex('workout_logs', 'workout_logs_exercise_id_index'))->toBeTrue();
    expect(Schema::hasIndex('workout_logs', 'workout_logs_recorded_by_index'))->toBeTrue();
});

it('blocks hard deletion of a referenced client, user, exercise or set row', function () {
    // BR-006 (preservation pattern): restrictOnDelete guards historical
    // execution data; referenced clients, users, exercises and set rows cannot
    // be hard-deleted.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['name' => 'Program', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    app(AssignRoutine::class)->handle($routine, [$client], now()->subDay());

    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
    ]);

    expect(fn () => $log->client->delete())->toThrow(QueryException::class);
    expect(fn () => $log->recordedBy->delete())->toThrow(QueryException::class);
    expect(fn () => $log->routineExercise->delete())->toThrow(QueryException::class);
    expect(fn () => $log->routineExercise->exercise->delete())->toThrow(QueryException::class);

    expect(WorkoutLog::find($log->id))->not->toBeNull();
});
