<?php

use App\Actions\AssignRoutine;
use App\Actions\VersionRoutine;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
 * Routine model and action unit tests (SPEC-010 BR-001, BR-002, BR-003,
 * BR-004, BR-005, BR-006, BR-007, BR-008, BR-010; FR-004, FR-006, FR-007,
 * FR-010, FR-011; AR-01..AR-04, AR-06, AR-09; AC-1, AC-7, AC-11, AC-16,
 * AC-17, AC-18).
 */

it('exposes the three status constants and creates a routine as draft version 1', function () {
    // BR-002, AR-01, FR-001, AC-1.
    expect(Routine::STATUS_DRAFT)->toBe('draft');
    expect(Routine::STATUS_ACTIVE)->toBe('active');
    expect(Routine::STATUS_ARCHIVED)->toBe('archived');

    $routine = Routine::factory()->create(['name' => 'Push Day']);

    expect($routine->status)->toBe(Routine::STATUS_DRAFT);
    expect($routine->version_number)->toBe(1);
    expect($routine->replaces_id)->toBeNull();
    expect($routine->days)->toHaveCount(0);
    expect($routine->isDraft())->toBeTrue();
    expect($routine->isActive())->toBeFalse();
    expect($routine->isArchived())->toBeFalse();
});

it('defaults status to draft even for a raw write path and creates the expected columns and indexes', function () {
    // BR-002, AR-01: the DB default on status is 'draft' and version_number
    // is 1 even for a raw insert; the expected columns and the name / status
    // indexes exist.
    $creator = User::factory()->create();

    DB::table('routines')->insert([
        'name' => 'Raw Insert Routine',
        'created_by' => $creator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('routines')->first();

    expect($row->status)->toBe('draft');
    expect((int) $row->version_number)->toBe(1);

    expect(Schema::hasColumns('routines', [
        'id',
        'name',
        'status',
        'version_number',
        'replaces_id',
        'created_by',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasIndex('routines', 'routines_name_index'))->toBeTrue();
    expect(Schema::hasIndex('routines', 'routines_status_index'))->toBeTrue();
});

it('rejects duplicate day numbers and duplicate set numbers via the database unique indexes', function () {
    // ERR-002, BR-003, BR-010: the unique indexes enforce uniqueness at the
    // database level.
    $routine = Routine::factory()->create();
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);

    expect(fn () => RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]))
        ->toThrow(QueryException::class);

    $exercise = Exercise::factory()->create();
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'set_number' => 1, 'exercise_id' => $exercise->id]);

    expect(fn () => RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'set_number' => 1, 'exercise_id' => $exercise->id]))
        ->toThrow(QueryException::class);
});

it('orders days by day_number and sets by set_number', function () {
    // BR-003, BR-004 (FR-003 display ordering).
    $routine = Routine::factory()->create();
    $day2 = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 2]);
    $day1 = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);

    expect($routine->days->pluck('day_number')->all())->toBe([1, 2]);

    $exercise = Exercise::factory()->create();
    RoutineExercise::factory()->create(['routine_day_id' => $day1->id, 'set_number' => 2, 'exercise_id' => $exercise->id]);
    RoutineExercise::factory()->create(['routine_day_id' => $day1->id, 'set_number' => 1, 'exercise_id' => $exercise->id]);

    expect($day1->exercises->pluck('set_number')->all())->toBe([1, 2]);
});

it('rejects activation of an empty draft, a draft with an empty day, and a non-draft version', function () {
    // FR-007, ERR-003, ERR-004, BR-002 (AC-4).
    $routine = Routine::factory()->create();

    expect(fn () => $routine->activate())->toThrow(DomainException::class);
    expect($routine->fresh()->status)->toBe(Routine::STATUS_DRAFT);

    $emptyDay = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $routine->refresh();

    expect(fn () => $routine->activate())->toThrow(DomainException::class);
    expect($routine->fresh()->status)->toBe(Routine::STATUS_DRAFT);

    RoutineExercise::factory()->create(['routine_day_id' => $emptyDay->id, 'set_number' => 1]);

    $routine->activate();

    expect($routine->fresh()->status)->toBe(Routine::STATUS_ACTIVE);

    expect(fn () => $routine->activate())->toThrow(DomainException::class);
});

it('walks the lineage in both directions and scopes lineage heads', function () {
    // BR-001, AR-02, FR-004, FR-002.
    $admin = userWithRoles([Role::ADMIN]);
    $v1 = Routine::factory()->create(['name' => 'Lineage', 'version_number' => 1]);
    $v2 = Routine::factory()->create(['name' => 'Lineage', 'version_number' => 2, 'replaces_id' => $v1->id, 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $v3 = Routine::factory()->create(['name' => 'Lineage', 'version_number' => 3, 'replaces_id' => $v2->id, 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);

    expect($v1->lineageIds())->toBe([$v1->id, $v2->id, $v3->id]);
    expect($v2->lineageIds())->toBe([$v1->id, $v2->id, $v3->id]);
    expect($v3->lineageIds())->toBe([$v1->id, $v2->id, $v3->id]);

    expect($v1->lineage()->pluck('version_number')->all())->toBe([1, 2, 3]);

    expect(Routine::lineageHeads()->pluck('id')->all())->toBe([$v3->id]);
});

it('scopes active versions only', function () {
    // BR-002: the "currently assignable" set (FR-007).
    $active = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    Routine::factory()->create(['status' => Routine::STATUS_DRAFT]);
    Routine::factory()->create(['status' => Routine::STATUS_ARCHIVED]);

    expect(Routine::active()->pluck('id'))->toHaveCount(1);
    expect(Routine::active()->pluck('id'))->toContain($active->id);
});

it('creates a new version on copy-on-edit, archives the previous and keeps assignments untouched', function () {
    // FR-006, BR-001, BR-002, AR-02 (AC-7, AC-8).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Original Name', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
        'rest_seconds' => 90,
        'notes' => 'Slow negatives',
    ]);

    $client = Client::factory()->create();
    $this->actingAs($admin);

    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    $newVersion = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'New Name',
        'days' => [
            [
                'day_number' => 1,
                'exercises' => [
                    [
                        'id' => $day->exercises()->first()->id,
                        'exercise_id' => $exercise->id,
                        'set_number' => 1,
                        'target_reps' => 12,
                        'target_weight' => 62.5,
                        'rest_seconds' => null,
                        'notes' => null,
                    ],
                ],
            ],
        ],
    ]);

    expect($newVersion->id)->not->toBe($v1->id);
    expect($newVersion->name)->toBe('New Name');
    expect($newVersion->status)->toBe(Routine::STATUS_ACTIVE);
    expect($newVersion->version_number)->toBe(2);
    expect($newVersion->replaces_id)->toBe($v1->id);
    expect($newVersion->created_by)->toBe($admin->id);

    expect($v1->fresh()->status)->toBe(Routine::STATUS_ARCHIVED);

    // Fresh rows are created: no row is shared across versions (BR-001).
    expect($newVersion->days)->toHaveCount(1);
    expect($newVersion->days()->first()->exercises)->toHaveCount(1);
    expect($newVersion->days()->first()->exercises()->first()->target_reps)->toBe(12);
    expect($newVersion->days()->first()->exercises()->first()->target_weight)->toBe('62.50');

    // The previous version's rows are untouched.
    expect($v1->days()->first()->exercises()->first()->target_reps)->toBe(10);

    // Assignments are untouched: the client remains on the previous version.
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->first()->routine_id)->toBe($v1->id);
});

it('preserves set rows referencing a now-inactive exercise on copy-on-edit and rejects inactive exercises for new rows', function () {
    // BR-006, AR-04 (AC-18, AC-5).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    $setRow = RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $this->actingAs($admin);

    // Deactivating the exercise (SPEC-009) never modifies the prescription row.
    $exercise->update(['is_active' => false]);

    expect($setRow->fresh()->exercise_id)->toBe($exercise->id);
    expect(RoutineExercise::count())->toBe(1);
    expect(RoutineDay::count())->toBe(1);

    // Copy-on-edit keeps the reference (the copied row carries its source id).
    $newVersion = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => $v1->name,
        'days' => [
            [
                'day_number' => 1,
                'exercises' => [
                    [
                        'id' => $setRow->id,
                        'exercise_id' => $exercise->id,
                        'set_number' => 1,
                        'target_reps' => 10,
                    ],
                ],
            ],
        ],
    ]);

    expect($newVersion->days()->first()->exercises()->first()->exercise_id)->toBe($exercise->id);

    // A NEW row (no source id) referencing the inactive exercise is rejected.
    expect(fn () => app(VersionRoutine::class)->handle($v1->fresh(), $admin, [
        'name' => 'Invalid',
        'days' => [
            [
                'day_number' => 1,
                'exercises' => [
                    [
                        'exercise_id' => $exercise->id,
                        'set_number' => 1,
                        'target_reps' => 10,
                    ],
                ],
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

it('rejects versioning a draft or archived routine and invalid prescription state', function () {
    // ERR-006, ERR-002, ERR-005, BR-010.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $draft = Routine::factory()->create();

    expect(fn () => app(VersionRoutine::class)->handle($draft, $admin, ['name' => 'X', 'days' => []]))
        ->toThrow(ValidationException::class);

    $archived = Routine::factory()->create(['status' => Routine::STATUS_ARCHIVED]);

    expect(fn () => app(VersionRoutine::class)->handle($archived, $admin, ['name' => 'X', 'days' => []]))
        ->toThrow(ValidationException::class);

    $active = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $exercise = Exercise::factory()->create();

    // Zero days.
    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, ['name' => 'X', 'days' => []]))
        ->toThrow(ValidationException::class);

    // A day with zero sets.
    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, [
        'name' => 'X',
        'days' => [['day_number' => 1, 'exercises' => []]],
    ]))->toThrow(ValidationException::class);

    // Duplicate day numbers.
    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, [
        'name' => 'X',
        'days' => [
            ['day_number' => 1, 'exercises' => [['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10]]],
            ['day_number' => 1, 'exercises' => [['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10]]],
        ],
    ]))->toThrow(ValidationException::class);

    // Duplicate set numbers in a day.
    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, [
        'name' => 'X',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
                ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
            ]],
        ],
    ]))->toThrow(ValidationException::class);

    // Invalid prescription values (ERR-005).
    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, [
        'name' => 'X',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 0],
            ]],
        ],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(VersionRoutine::class)->handle($active, $admin, [
        'name' => 'X',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10, 'target_weight' => -1],
            ]],
        ],
    ]))->toThrow(ValidationException::class);
});

it('assigns an active version to many clients and supersedes previous active assignments', function () {
    // FR-009, AF-002, BR-007, AR-03 (AC-10, AC-11).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $routineA = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    $routineB = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    $client = Client::factory()->create();

    app(AssignRoutine::class)->handle($routineA, [$client], now()->subDay());

    expect($client->routineAssignments()->count())->toBe(1);
    expect($client->currentRoutine()->id)->toBe($routineA->id);

    app(AssignRoutine::class)->handle($routineB, [$client]);

    expect($client->routineAssignments()->count())->toBe(2);
    expect($client->routineAssignments()->where('is_active', true)->count())->toBe(1);
    expect($client->currentRoutine()->id)->toBe($routineB->id);
    expect($client->routineAssignments()->where('is_active', false)->first()->routine_id)->toBe($routineA->id);
});

it('rejects assigning a draft or archived version and a nonexistent client', function () {
    // ERR-008, BR-007 (AC-12).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $draft = Routine::factory()->create();
    $archived = Routine::factory()->create(['status' => Routine::STATUS_ARCHIVED]);
    $client = Client::factory()->create();

    expect(fn () => app(AssignRoutine::class)->handle($draft, [$client]))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AssignRoutine::class)->handle($archived, [$client]))
        ->toThrow(ValidationException::class);

    $active = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);

    expect(fn () => app(AssignRoutine::class)->handle($active, [new Client(['id' => 999999])]))
        ->toThrow(ValidationException::class);
});

it('deactivates only an active assignment and leaves history intact', function () {
    // FR-010, AF-006, BR-008 (AC-13).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    $client = Client::factory()->create();

    app(AssignRoutine::class)->handle($routine, [$client]);

    $assignment = $client->routineAssignments()->first();

    $assignment->deactivate();

    expect($assignment->fresh()->is_active)->toBeFalse();
    expect(RoutineAssignment::find($assignment->id))->not->toBeNull();
    expect($client->currentRoutine())->toBeNull();

    expect(fn () => $assignment->deactivate())->toThrow(DomainException::class);
});

it('returns the active version for currentRoutine() and null otherwise', function () {
    // FR-011, AR-03.
    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    $client = Client::factory()->create();

    expect($client->currentRoutine())->toBeNull();

    RoutineAssignment::factory()->create(['client_id' => $client->id, 'routine_id' => $routine->id, 'is_active' => true]);

    expect($client->currentRoutine()->id)->toBe($routine->id);

    RoutineAssignment::factory()->create(['client_id' => $client->id, 'routine_id' => $routine->id, 'is_active' => false]);

    expect($client->currentRoutine()->id)->toBe($routine->id);
});

it('creates no other record when creating a routine', function () {
    // BR-005, AC-17: creating a routine touches only the routine tables; no
    // workout-log / user / client / exercise record is created.
    $admin = userWithRoles([Role::ADMIN]);

    Routine::factory()->create(['created_by' => $admin->id]);

    expect(Routine::count())->toBe(1);
    expect(DB::table('routine_days')->count())->toBe(0);
    expect(DB::table('routine_exercises')->count())->toBe(0);
    expect(DB::table('routine_assignments')->count())->toBe(0);
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('exercises')->count())->toBe(0);
});

it('never hard-deletes routine data', function () {
    // BR-008, ERR-009 (AC-16): no delete policy is registered and no delete
    // operation exists.
    $admin = userWithRoles([Role::ADMIN]);
    $routine = Routine::factory()->create();
    $assignment = RoutineAssignment::factory()->create(['routine_id' => $routine->id]);

    expect($admin->can('delete', $routine))->toBeFalse();
    expect($admin->can('delete', $assignment))->toBeFalse();

    expect(Routine::find($routine->id))->not->toBeNull();
    expect(RoutineAssignment::find($assignment->id))->not->toBeNull();
});
