<?php

use App\Actions\AssignRoutine;
use App\Actions\VersionRoutine;
use App\Filament\Resources\WorkoutLogResource\Pages\ClientProgress;
use App\Filament\Resources\WorkoutLogResource\Pages\CreateWorkoutLog;
use App\Filament\Resources\WorkoutLogResource\Pages\ListWorkoutLogs;
use App\Filament\Resources\WorkoutLogResource\Pages\ViewWorkoutLog;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
 * Workout-log create/list/view/progress feature tests (SPEC-011 FR-001..
 * FR-005; BR-001, BR-002, BR-003, BR-004, BR-005, BR-006, BR-008, BR-009,
 * BR-010; ERR-001..ERR-005, ERR-007, ERR-008; AC-1..AC-12, AC-15; AF-001,
 * AF-002, AF-003, AF-004). Authorization is enforced server-side (AGENTS.md
 * §17); state/validation rules are form rules, not authorization rules
 * (SPEC-011 §9).
 */

/**
 * Build an active routine version with one day and one set-level row assigned
 * to the given client (SPEC-010 AssignRoutine) and return the set row.
 *
 * The acting user must hold ADMIN or TRAINER (AssignRoutine authorizes via
 * RoutinePolicy::update).
 */
function makeAssignedSet(Client $client, User $actor): RoutineExercise
{
    $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
    $routine = Routine::factory()->create([
        'name' => 'Push Day',
        'status' => Routine::STATUS_ACTIVE,
        'created_by' => $actor->id,
    ]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);

    app(AssignRoutine::class)->handle($routine, [$client], now()->subDay());

    return $set;
}

/**
 * A set row of an ACTIVE routine version that is NOT assigned to any client
 * (a never-assigned reference for ERR-003).
 */
function makeUnassignedActiveSet(User $actor): RoutineExercise
{
    $exercise = Exercise::factory()->create(['name' => 'Unassigned Exercise']);
    $routine = Routine::factory()->create([
        'name' => 'Unassigned Routine',
        'status' => Routine::STATUS_ACTIVE,
        'created_by' => $actor->id,
    ]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);

    return RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 8,
        'target_weight' => 40,
    ]);
}

it('allows ADMIN to record an assigned-routine log for a client', function () {
    // AC-1 (FR-001, BR-001, BR-004, BR-008, BR-009, WL-11): the log is
    // persisted with actual weight/reps, performed_at (default now), notes,
    // recorded_by = the current staff User and logged_at = created_at.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Member One', 'dni' => '12345678']);
    $set = makeAssignedSet($client, $admin);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $set->id,
            'actual_weight' => '62.5',
            'actual_reps' => 8,
            'notes' => 'Strong set',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $log = WorkoutLog::firstOrFail();

    expect($log->client_id)->toBe($client->id);
    expect($log->routine_exercise_id)->toBe($set->id);
    expect($log->exercise_id)->toBeNull();
    expect($log->actual_weight)->toBe('62.50');
    expect($log->actual_reps)->toBe(8);
    expect($log->notes)->toBe('Strong set');
    expect($log->recorded_by)->toBe($admin->id);
    expect($log->performed_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($log->performed_at->isPast())->toBeTrue();
    expect($log->created_at)->not->toBeNull();
    expect(WorkoutLog::count())->toBe(1);
});

it('allows TRAINER to record an assigned-routine log', function () {
    // AC-1 (BR-007, WL-03, WL-09): TRAINER receives the full logging set.
    $trainer = userWithRoles([Role::TRAINER]);
    $this->actingAs($trainer);
    $client = Client::factory()->create();
    $set = makeAssignedSet($client, $trainer);

    Livewire::actingAs($trainer)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $set->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkoutLog::count())->toBe(1);
    expect(WorkoutLog::first()->recorded_by)->toBe($trainer->id);
});

it('records a log for a client with no membership or access decision', function () {
    // BR-010: logging has no membership/access precondition — unlike the
    // Attendance gate, no access rule applies to workout logs.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(); // no memberships at all
    $exercise = Exercise::factory()->create(['name' => 'Squat']);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkoutLog::count())->toBe(1);
});

it('never creates or modifies routine, day, set-row or assignment records when logging', function () {
    // AC-2 (BR-003, C-10): recording a log touches only the workout_logs
    // table; the prescription and assignment rows are byte-for-byte unchanged.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
    $routine = Routine::factory()->create([
        'name' => 'Push Day',
        'status' => Routine::STATUS_ACTIVE,
        'created_by' => $admin->id,
    ]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);
    app(AssignRoutine::class)->handle($routine, [$client], now()->subDay());

    $assignment = $client->routineAssignments()->first();
    $routineBefore = $routine->fresh()->toArray();
    $dayBefore = $day->fresh()->toArray();
    $setBefore = $set->fresh()->toArray();
    $assignmentBefore = $assignment->fresh()->toArray();

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $set->id,
            'actual_weight' => '62.5',
            'actual_reps' => 8,
            'notes' => 'Recorded set',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkoutLog::count())->toBe(1);
    expect(Routine::count())->toBe(1);
    expect(RoutineDay::count())->toBe(1);
    expect(RoutineExercise::count())->toBe(1);
    expect(DB::table('routine_assignments')->count())->toBe(1);
    expect(Exercise::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting admin

    expect($routine->fresh()->toArray())->toBe($routineBefore);
    expect($day->fresh()->toArray())->toBe($dayBefore);
    expect($set->fresh()->toArray())->toBe($setBefore);
    expect($assignment->fresh()->toArray())->toBe($assignmentBefore);
});

it('rejects a log with both references and a log with neither reference', function () {
    // AC-3 (ERR-001, BR-002): exactly one of routine_exercise_id /
    // exercise_id must be set. The invariant is enforced by the create form's
    // validation via the shared WorkoutLog::referenceRules() (required_without
    // + prohibits) — the architecture test plan's "via the shared
    // referenceRules()". The transient reference_type toggle keeps the two
    // reference selects mutually exclusive, and the installed
    // Filament/Livewire behavior drops the state of the hidden reference
    // field, so a both-set payload can never be submitted through the form
    // (architecture §9 risk note: "the Developer verifies the installed
    // Filament/Livewire behavior and clears the hidden field on switch").
    // The both-set rejection is therefore asserted at the shared-rules level
    // here and in the WorkoutLogTest unit tests; the neither-set rejection is
    // asserted through the form (the visible reference field's
    // required_without rule fires).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $set = makeAssignedSet($client, $admin);
    $freeExercise = Exercise::factory()->create(['name' => 'Pull-up']);

    // Both references set -> rejected by the shared rules (ERR-001).
    $both = Validator::make([
        'routine_exercise_id' => $set->id,
        'exercise_id' => $freeExercise->id,
    ], WorkoutLog::referenceRules());

    expect($both->fails())->toBeTrue();
    expect($both->errors()->has('routine_exercise_id'))->toBeTrue();
    expect($both->errors()->has('exercise_id'))->toBeTrue();

    // Neither reference set -> rejected by the form (required_without on the
    // visible reference field); no log is persisted.
    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['routine_exercise_id']);

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects a routine-exercise reference from a version the client was never assigned to', function () {
    // AC-4 (ERR-003, BR-004, BR-008): the row must belong to a routine version
    // the client has been assigned to — even an active version never assigned
    // to THIS client is rejected.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    makeAssignedSet($client, $admin);
    $foreignSet = makeUnassignedActiveSet($admin);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $foreignSet->id,
            'actual_reps' => 8,
        ])
        ->call('create')
        ->assertHasFormErrors(['routine_exercise_id'])
        ->assertSee('nunca fue asignado');

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects a routine-exercise reference from a draft version', function () {
    // AC-4 (ERR-003, BR-004): drafts are never assignable, so their rows are
    // never valid log targets.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    makeAssignedSet($client, $admin);
    $draft = Routine::factory()->create(['name' => 'Draft Routine', 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $draft->id, 'day_number' => 1]);
    $draftSet = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'set_number' => 1,
        'target_reps' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $draftSet->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['routine_exercise_id'])
        ->assertSee('nunca fue asignado');

    expect(WorkoutLog::count())->toBe(0);
});

it('accepts a routine-exercise reference from a historical assignment version', function () {
    // AF-002 (BR-004, WL-07): after versioning + reassignment, the previous
    // version's set rows remain valid log targets because the assignment
    // history is preserved.
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
    app(AssignRoutine::class)->handle($v1, [$client], now()->subDays(5));

    $v2 = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Leg Day v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $set->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 12],
            ]],
        ],
    ]);
    app(AssignRoutine::class)->handle($v2, [$client], now()->subDay());

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => $set->id, // the historical v1 row
            'actual_weight' => '62.5',
            'actual_reps' => 9,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $log = WorkoutLog::firstOrFail();

    expect($log->routine_exercise_id)->toBe($set->id);
});

it('records a free log for a client with no assigned routine', function () {
    // AC-5 (FR-002, AF-001, BR-002): a client without an assigned routine can
    // still be logged through the free-exercise path.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Deadlift']);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
            'actual_weight' => '100',
            'actual_reps' => 5,
            'notes' => 'Free session',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $log = WorkoutLog::firstOrFail();

    expect($log->client_id)->toBe($client->id);
    expect($log->exercise_id)->toBe($exercise->id);
    expect($log->routine_exercise_id)->toBeNull();
    expect($log->actual_weight)->toBe('100.00');
    expect($log->actual_reps)->toBe(5);
    expect($log->notes)->toBe('Free session');
});

it('rejects a free log referencing an inactive exercise', function () {
    // AC-6 (ERR-005, BR-005): new free logs may only reference active
    // catalogue exercises.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $inactive = Exercise::factory()->create(['name' => 'Retired Exercise', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $inactive->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['exercise_id'])
        ->assertSee('ejercicio activo');

    expect(WorkoutLog::count())->toBe(0);
});

it('rejects invalid performed values and accepts a backdated performed_at', function () {
    // AC-7 (ERR-004, BR-008, WL-05, WL-06): missing/zero/negative reps,
    // negative weight and a future performed_at are rejected; backdating is
    // allowed.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Curl']);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['actual_reps' => 'required']);

    foreach ([0, -1] as $reps) {
        Livewire::actingAs($admin)
            ->test(CreateWorkoutLog::class)
            ->fillForm([
                'client_id' => $client->id,
                'reference_type' => 'free',
                'exercise_id' => $exercise->id,
                'actual_reps' => $reps,
            ])
            ->call('create')
            ->assertHasFormErrors(['actual_reps' => 'min']);
    }

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
            'actual_weight' => '-1',
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['actual_weight' => 'min']);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
            'performed_at' => now()->addHour()->format('Y-m-d H:i'),
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['performed_at' => 'before_or_equal']);

    expect(WorkoutLog::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'reference_type' => 'free',
            'exercise_id' => $exercise->id,
            'performed_at' => now()->subHours(2)->format('Y-m-d H:i'),
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $log = WorkoutLog::firstOrFail();

    expect($log->performed_at->format('Y-m-d H:i'))->toBe(now()->subHours(2)->format('Y-m-d H:i'));
});

it('rejects a log for a nonexistent client or reference', function () {
    // AC-15, ERR-002, ERR-008: client_id, routine_exercise_id and exercise_id
    // are foreign keys.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $set = makeAssignedSet($client, $admin);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => 999999,
            'routine_exercise_id' => $set->id,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['client_id' => 'exists']);

    expect(WorkoutLog::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(CreateWorkoutLog::class)
        ->fillForm([
            'client_id' => $client->id,
            'routine_exercise_id' => 999999,
            'actual_reps' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors(['routine_exercise_id' => 'exists']);

    expect(WorkoutLog::count())->toBe(0);
});

it('shows recorded_by and logged_at and supports filtering by client, date range, recorded_by and reference type', function () {
    // FR-005, FR-003: the list shows the recording staff User and logged_at
    // (created_at) and filters by client, date range on performed_at,
    // recorded_by and reference type.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Filter Client']);
    $set = makeAssignedSet($client, $admin);
    $otherClient = Client::factory()->create(['full_name' => 'Other Person']);
    $freeExercise = Exercise::factory()->create(['name' => 'Lat Pulldown']);

    $withSet = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
        'performed_at' => today()->subDay()->setTime(10, 0),
    ]);
    $free = WorkoutLog::factory()->create([
        'client_id' => $otherClient->id,
        'recorded_by' => $trainer->id,
        'exercise_id' => $freeExercise->id,
        'performed_at' => today()->setTime(9, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->filterTable('client_id', $client->id)
        ->assertCanSeeTableRecords([$withSet])
        ->assertCanNotSeeTableRecords([$free]);

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->filterTable('performed_at', ['performed_from' => today()->toDateString()])
        ->assertCanSeeTableRecords([$free])
        ->assertCanNotSeeTableRecords([$withSet]);

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->filterTable('recorded_by', $trainer->id)
        ->assertCanSeeTableRecords([$free])
        ->assertCanNotSeeTableRecords([$withSet]);

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->filterTable('reference_type', 'free')
        ->assertCanSeeTableRecords([$free])
        ->assertCanNotSeeTableRecords([$withSet]);

    // FR-005: the recording staff user and logged_at are visible in the list.
    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->assertSee($trainer->name);
});

it('lists a client workout history in chronological order', function () {
    // FR-003 (AC-8): the list is ordered by performed_at ascending; the client
    // filter isolates one client's history.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();

    $oldest = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'performed_at' => today()->subDays(2)->setTime(9, 0),
    ]);
    $middle = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'performed_at' => today()->subDay()->setTime(9, 0),
    ]);
    $newest = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'performed_at' => today()->setTime(9, 0),
    ]);
    $other = WorkoutLog::factory()->create([
        'recorded_by' => $admin->id,
        'performed_at' => today()->setTime(12, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->filterTable('client_id', $client->id)
        ->assertCanSeeTableRecords([$oldest, $middle, $newest], inOrder: true)
        ->assertCanNotSeeTableRecords([$other]);
});

it('lets ADMIN view the full workout-log detail', function () {
    // FR-005: the detail view shows the client (name/DNI), the performed
    // timestamp, the exercise, the prescription target, the actual weight/reps,
    // the notes, the recording staff User and logged_at.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Detail Member', 'dni' => '12345678']);
    $set = makeAssignedSet($client, $admin);
    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
        'performed_at' => today()->setTime(9, 30),
        'actual_weight' => '62.5',
        'actual_reps' => 8,
        'notes' => 'Detail note',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewWorkoutLog::class, ['record' => $log->getRouteKey()])
        ->assertSee('Detail Member')
        ->assertSee('12345678')
        ->assertSee('Bench Press')
        ->assertSee('60.00') // prescription target weight
        ->assertSee('62.50') // actual weight
        ->assertSee('8')     // actual reps
        ->assertSee('Detail note')
        ->assertSee($admin->name);
});

it('shows the client progress page with the prescription-vs-actual comparison', function () {
    // AC-8, AC-9 (FR-003, FR-004, WL-10): the progress page shows the client's
    // logs chronologically with the exercise, the target weight/reps when the
    // log references a prescribed row, and the actual weight/reps.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Progress Member']);
    $set = makeAssignedSet($client, $admin);
    $freeExercise = Exercise::factory()->create(['name' => 'Cable Crossover']);

    $setLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
        'performed_at' => today()->subDay()->setTime(9, 0),
        'actual_weight' => '62.5',
        'actual_reps' => 8,
    ]);
    $freeLog = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $freeExercise->id,
        'performed_at' => today()->setTime(9, 0),
        'actual_weight' => '20',
        'actual_reps' => 15,
    ]);
    WorkoutLog::factory()->create([ // another client's log is excluded
        'recorded_by' => $admin->id,
        'performed_at' => today()->setTime(10, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(ClientProgress::class, ['client' => $client->id])
        ->assertCanSeeTableRecords([$setLog, $freeLog], inOrder: true)
        ->assertSee('Bench Press')
        ->assertSee('60.00') // target weight of the prescribed row
        ->assertSee('10')    // target reps of the prescribed row
        ->assertSee('62.50') // actual weight
        ->assertSee('Cable Crossover');
});

it('shows the target columns as a dash for free logs on the progress page', function () {
    // AC-9 (FR-004): free-log rows show the target columns as '—'.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Free Client']);
    $exercise = Exercise::factory()->create(['name' => 'Push Press']);

    WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $exercise->id,
        'actual_weight' => '40',
        'actual_reps' => 12,
    ]);

    Livewire::actingAs($admin)
        ->test(ClientProgress::class, ['client' => $client->id])
        ->assertCanSeeTableRecords([WorkoutLog::first()])
        ->assertSee('Push Press')
        ->assertSee('—');
});

it('keeps existing logs unchanged and still referencing the old version after versioning', function () {
    // AC-10 (BR-004, AF-004, D-12 option 3): versioning a routine creates,
    // modifies or deletes no log; logs keep pointing at the old version's rows.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Overhead Press']);
    $v1 = Routine::factory()->create(['name' => 'Shoulder Day', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    $set = RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 40,
    ]);
    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'routine_exercise_id' => $set->id,
        'actual_weight' => '42.5',
        'actual_reps' => 9,
        'notes' => 'Preserve me',
    ]);

    app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Shoulder Day v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $set->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 12],
            ]],
        ],
    ]);

    expect(WorkoutLog::count())->toBe(1);
    $log->refresh();

    expect($log->routine_exercise_id)->toBe($set->id);
    expect($log->actual_weight)->toBe('42.50');
    expect($log->actual_reps)->toBe(9);
    expect($log->notes)->toBe('Preserve me');
    expect($log->routineExercise->target_reps)->toBe(10); // the old version's row
});

it('keeps logs unchanged when an exercise is deactivated', function () {
    // AC-11 (AF-003, BR-005): deactivating an exercise never creates,
    // modifies or deletes any log; a log referencing the now-inactive exercise
    // still displays, reading the exercise's current catalogue attributes.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Deactivation Client']);
    $exercise = Exercise::factory()->create(['name' => 'Deactivatable Exercise']);
    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'exercise_id' => $exercise->id,
        'actual_weight' => '30',
        'actual_reps' => 12,
        'notes' => 'Still shown',
    ]);

    $exercise->update(['is_active' => false]);

    expect(WorkoutLog::count())->toBe(1);
    expect($log->fresh()->exercise_id)->toBe($exercise->id);

    Livewire::actingAs($admin)
        ->test(ClientProgress::class, ['client' => $client->id])
        ->assertCanSeeTableRecords([$log])
        ->assertSee('Deactivatable Exercise')
        ->assertSee('Still shown');
});

it('exposes no edit or delete path and keeps created records unchanged', function () {
    // AC-12 (ERR-007, BR-006): no edit/delete page, action or policy ability
    // exists; a created log persists unchanged.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $log = WorkoutLog::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'performed_at' => now()->subHour(),
        'actual_weight' => '50',
        'actual_reps' => 10,
        'notes' => 'Original log',
    ]);

    expect($admin->can('update', $log))->toBeFalse();
    expect($admin->can('delete', $log))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListWorkoutLogs::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)
        ->get("/admin/workout-logs/{$log->getRouteKey()}/edit")
        ->assertNotFound();

    // No DELETE route is registered on the resource (immutability, BR-006):
    // the request is rejected with 405 Method Not Allowed — the same
    // "no such operation" guarantee as a 404, and the record persists.
    $this->actingAs($admin)
        ->delete("/admin/workout-logs/{$log->getRouteKey()}")
        ->assertStatus(405);

    expect($log->fresh()->notes)->toBe('Original log');
    expect($log->fresh()->actual_reps)->toBe(10);
    expect(WorkoutLog::find($log->id))->not->toBeNull();
});
