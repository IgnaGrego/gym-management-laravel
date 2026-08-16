<?php

use App\Actions\AssignRoutine;
use App\Filament\Resources\RoutineResource;
use App\Filament\Resources\RoutineResource\Pages\CreateRoutine;
use App\Filament\Resources\RoutineResource\Pages\EditRoutine;
use App\Filament\Resources\RoutineResource\Pages\ListRoutines;
use App\Filament\Resources\RoutineResource\Pages\ViewRoutine;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Routine CRUD, lifecycle and versioning feature tests (SPEC-010 FR-001..
 * FR-008, FR-012; BR-001, BR-002, BR-003, BR-004, BR-005, BR-006, BR-007,
 * BR-010, BR-011; ERR-002, ERR-003, ERR-004, ERR-005, ERR-006; AC-1..AC-9,
 * AC-14, AC-16, AC-17, AC-18). Authorization is enforced server-side
 * (AGENTS.md §17).
 */

it('allows ADMIN to create a routine persisted as draft version 1 with created_by and no days', function () {
    // AC-1 (FR-001, BR-002, BR-011, AR-01, AR-05).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateRoutine::class)
        ->fillForm(['name' => 'Push Day'])
        ->call('create')
        ->assertHasNoFormErrors();

    $routine = Routine::where('name', 'Push Day')->firstOrFail();

    expect($routine->status)->toBe(Routine::STATUS_DRAFT);
    expect($routine->version_number)->toBe(1);
    expect($routine->replaces_id)->toBeNull();
    expect($routine->created_by)->toBe($admin->id);
    expect($routine->days)->toHaveCount(0);
    expect($routine->creator->id)->toBe($admin->id);
});

it('allows TRAINER to create a routine', function () {
    // AC-1 (BR-009, AR-08).
    $trainer = userWithRoles([Role::TRAINER]);

    Livewire::actingAs($trainer)
        ->test(CreateRoutine::class)
        ->fillForm(['name' => 'Leg Day'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Routine::count())->toBe(1);
    expect(Routine::first()->status)->toBe(Routine::STATUS_DRAFT);
});

it('rejects creating a routine without a name', function () {
    // FR-001, BR-010.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateRoutine::class)
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    expect(Routine::count())->toBe(0);
});

it('allows ADMIN to add days and set-level prescription rows to a draft', function () {
    // AC-2 (FR-008, FR-003, BR-003, BR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Bench Press']);
    $routine = Routine::factory()->create(['name' => 'Push Day', 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Push Day',
            'days' => [
                [
                    'day_number' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => $exercise->id,
                            'set_number' => 1,
                            'target_reps' => 10,
                            'target_weight' => '60',
                            'rest_seconds' => '90',
                            'notes' => 'Keep elbows tucked',
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $routine->refresh();

    expect($routine->days)->toHaveCount(1);
    expect($routine->days->first()->day_number)->toBe(1);
    expect($routine->days->first()->exercises)->toHaveCount(1);
    $set = $routine->days->first()->exercises->first();
    expect($set->exercise_id)->toBe($exercise->id);
    expect($set->set_number)->toBe(1);
    expect($set->target_reps)->toBe(10);
    expect($set->target_weight)->toBe('60.00');
    expect($set->rest_seconds)->toBe(90);
    expect($set->notes)->toBe('Keep elbows tucked');
});

it('rejects duplicate day numbers and duplicate set numbers with validation errors', function () {
    // AC-3 (ERR-002, BR-010).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Duplicate Days',
            'days' => [
                ['day_number' => 1, 'exercises' => [['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10]]],
                ['day_number' => 1, 'exercises' => [['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10]]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'days.0.day_number',
            'days.1.day_number',
        ]);

    expect(RoutineDay::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Duplicate Sets',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 12],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors([
            'days.0.exercises.0.set_number',
            'days.0.exercises.1.set_number',
        ]);
});

it('rejects invalid prescription values', function () {
    // ERR-005 (BR-010, AR-06).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Bad Reps',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 0],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['days.0.exercises.0.target_reps' => 'min']);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Bad Weight',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10, 'target_weight' => -1],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['days.0.exercises.0.target_weight' => 'min']);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Bad Rest',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10, 'rest_seconds' => -5],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['days.0.exercises.0.rest_seconds' => 'min']);
});

it('rejects a new set row referencing an inactive exercise and accepts an active one', function () {
    // AC-5 (BR-006, AR-04).
    $admin = userWithRoles([Role::ADMIN]);
    $activeExercise = Exercise::factory()->create(['name' => 'Active Exercise']);
    $inactiveExercise = Exercise::factory()->create(['name' => 'Inactive Exercise', 'is_active' => false]);
    $routine = Routine::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Inactive Referenced',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $inactiveExercise->id, 'set_number' => 1, 'target_reps' => 10],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['days.0.exercises.0.exercise_id']);

    expect(RoutineDay::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Active Referenced',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $activeExercise->id, 'set_number' => 1, 'target_reps' => 10],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(RoutineDay::count())->toBe(1);
    expect(RoutineExercise::first()->exercise_id)->toBe($activeExercise->id);
});

it('rejects a set row referencing a nonexistent exercise', function () {
    // ERR-001 (BR-006): the exercise reference is a foreign key.
    $admin = userWithRoles([Role::ADMIN]);
    $routine = Routine::factory()->create(['created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Ghost Exercise',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => 999999, 'set_number' => 1, 'target_reps' => 10],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['days.0.exercises.0.exercise_id' => 'exists']);
});

it('edits a draft in place without creating a new version', function () {
    // AC-6 (FR-005, AF-001, BR-002).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['name' => 'Original Draft', 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->fillForm([
            'name' => 'Renamed Draft',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $routine->refresh();

    expect(Routine::count())->toBe(1);
    expect($routine->name)->toBe('Renamed Draft');
    expect($routine->status)->toBe(Routine::STATUS_DRAFT);
    expect($routine->version_number)->toBe(1);
    expect($routine->days)->toHaveCount(1);
    expect($routine->days->first()->exercises)->toHaveCount(1);
});

it('rejects activating an empty draft and a draft with an empty day, and activates a valid draft', function () {
    // AC-4 (FR-007, ERR-003, ERR-004, BR-002).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $empty = Routine::factory()->create(['name' => 'Empty', 'created_by' => $admin->id]);
    $noSets = Routine::factory()->create(['name' => 'No Sets', 'created_by' => $admin->id]);
    RoutineDay::factory()->create(['routine_id' => $noSets->id, 'day_number' => 1]);
    $valid = Routine::factory()->create(['name' => 'Valid', 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $valid->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $empty->getRouteKey()])
        ->callAction('activate');

    expect($empty->fresh()->status)->toBe(Routine::STATUS_DRAFT);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $noSets->getRouteKey()])
        ->callAction('activate');

    expect($noSets->fresh()->status)->toBe(Routine::STATUS_DRAFT);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $valid->getRouteKey()])
        ->callAction('activate');

    expect($valid->fresh()->status)->toBe(Routine::STATUS_ACTIVE);
});

it('hides the activate action for non-draft versions and the assign action for non-active versions', function () {
    // FR-007, ERR-008 (action visibility; enforced server-side by the
    // actions themselves).
    $admin = userWithRoles([Role::ADMIN]);
    $draft = Routine::factory()->create(['created_by' => $admin->id]);
    $active = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $archived = Routine::factory()->create(['status' => Routine::STATUS_ARCHIVED, 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $draft->getRouteKey()])
        ->assertActionVisible('activate')
        ->assertActionHidden('assign');

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $active->getRouteKey()])
        ->assertActionHidden('activate')
        ->assertActionVisible('assign');

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $archived->getRouteKey()])
        ->assertActionHidden('activate')
        ->assertActionHidden('assign');
});

it('editing an active routine creates a new version, archives the previous and leaves assignments untouched', function () {
    // AC-7 (FR-006, BR-001, BR-002, D-12 option 3).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Original', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
    ]);

    $client = Client::factory()->create();
    $this->actingAs($admin);
    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $v1->getRouteKey()])
        ->set('data.days', [])
        ->fillForm([
            'name' => 'New Name',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 12, 'target_weight' => '62.5'],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Routine::count())->toBe(2);

    $v1->refresh();

    expect($v1->status)->toBe(Routine::STATUS_ARCHIVED);
    // The old version's rows are untouched.
    expect($v1->days()->first()->exercises()->first()->target_reps)->toBe(10);

    $v2 = Routine::where('version_number', 2)->firstOrFail();

    expect($v2->name)->toBe('New Name');
    expect($v2->status)->toBe(Routine::STATUS_ACTIVE);
    expect($v2->replaces_id)->toBe($v1->id);
    expect($v2->created_by)->toBe($admin->id);
    expect($v2->days()->first()->day_number)->toBe(1);
    expect($v2->days()->first()->exercises()->first()->target_reps)->toBe(12);
    expect($v2->days()->first()->exercises()->first()->target_weight)->toBe('62.50');

    // Assignments are untouched: the client remains on the previous version.
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->first()->routine_id)->toBe($v1->id);
});

it('keeps clients on the previous version until explicitly reassigned after versioning', function () {
    // AC-8 (FR-010, AF-003, D-12 option 3).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Routine', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    $client = Client::factory()->create();
    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    $v2 = app(\App\Actions\VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Routine v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $day->exercises()->first()->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
            ]],
        ],
    ]);

    expect($client->currentRoutine()->id)->toBe($v1->id);

    app(AssignRoutine::class)->handle($v2, [$client]);

    expect($client->currentRoutine()->id)->toBe($v2->id);
    expect(RoutineAssignment::where('client_id', $client->id)->count())->toBe(2);
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->count())->toBe(1);
});

it('shows the version history with number, status and creator, and blocks editing archived versions', function () {
    // AC-9 (FR-004, AF-004, ERR-006).
    $admin = userWithRoles([Role::ADMIN]);
    $v1 = Routine::factory()->create(['name' => 'Lineage', 'created_by' => $admin->id]);
    $v2 = Routine::factory()->create(['name' => 'Lineage', 'status' => Routine::STATUS_ACTIVE, 'version_number' => 2, 'replaces_id' => $v1->id, 'created_by' => $admin->id]);
    $v3 = Routine::factory()->create(['name' => 'Lineage', 'status' => Routine::STATUS_ARCHIVED, 'version_number' => 3, 'replaces_id' => $v2->id, 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $v3->getRouteKey()])
        ->assertSee('Historial de versiones')
        ->assertSee('v1')
        ->assertSee('v2')
        ->assertSee('v3')
        ->assertSee('Archivado');

    // Archived versions are fully readable (AF-004) but cannot be edited:
    // canEdit blocks both the row/header Edit action and direct URL access.
    expect(RoutineResource::canEdit($v1))->toBeTrue();
    expect(RoutineResource::canEdit($v3))->toBeFalse();

    $this->actingAs($admin)->get("/admin/routines/{$v3->getRouteKey()}/edit")->assertForbidden();
    $this->actingAs($admin)->get("/admin/routines/{$v3->getRouteKey()}")->assertOk();
});

it('lets ADMIN search routines by name and filter by status; lists show status and version', function () {
    // AC-14 (FR-002, FR-012).
    $admin = userWithRoles([Role::ADMIN]);
    $draft = Routine::factory()->create(['name' => 'Searchable Push', 'created_by' => $admin->id]);
    $active = Routine::factory()->create(['name' => 'Other Pull', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $archived = Routine::factory()->create(['name' => 'Old Program', 'status' => Routine::STATUS_ARCHIVED, 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(ListRoutines::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$active, $archived]);

    Livewire::actingAs($admin)
        ->test(ListRoutines::class)
        ->filterTable('status', Routine::STATUS_ARCHIVED)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$draft, $active]);

    Livewire::actingAs($admin)
        ->test(ListRoutines::class)
        ->assertCanSeeTableRecords([$draft, $active, $archived]);
});

it('does not expose a delete operation and preserves created routine records', function () {
    // AC-16 (ERR-009, BR-008).
    $admin = userWithRoles([Role::ADMIN]);
    $routine = Routine::factory()->create(['name' => 'Eternal Routine', 'created_by' => $admin->id]);

    Livewire::actingAs($admin)
        ->test(ListRoutines::class)
        ->assertTableActionDoesNotExist('delete');

    expect($admin->can('delete', $routine))->toBeFalse();

    $this->actingAs($admin)->get('/admin/routines')->assertOk();

    expect(Routine::find($routine->id))->not->toBeNull();
});

it('deactivating an exercise never modifies the prescription rows that reference it', function () {
    // AC-18 (BR-006, AR-04; SPEC-009 BR-010/BR-011 consumed here).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Deactivatable Exercise']);
    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create([
        'routine_day_id' => $day->id,
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'target_reps' => 10,
        'target_weight' => 60,
        'notes' => 'Preserve me',
    ]);

    $exercise->update(['is_active' => false]);

    expect(RoutineExercise::count())->toBe(1);
    expect(RoutineDay::count())->toBe(1);
    expect(Routine::count())->toBe(1);

    $set = RoutineExercise::first();

    expect($set->exercise_id)->toBe($exercise->id);
    expect($set->target_reps)->toBe(10);
    expect($set->target_weight)->toBe('60.00');
    expect($set->notes)->toBe('Preserve me');

    // The prescription still displays the exercise with its current catalogue
    // attributes (AR-04).
    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $routine->getRouteKey()])
        ->assertSee('Deactivatable Exercise');
});

it('touches only the routine tables when creating, editing, activating or versioning a routine', function () {
    // AC-17 (BR-005): no workout-log / user / client / exercise record is
    // created or modified.
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateRoutine::class)
        ->fillForm(['name' => 'Side Effect Free'])
        ->call('create')
        ->assertHasNoFormErrors();

    $routine = Routine::where('name', 'Side Effect Free')->firstOrFail();

    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    $routine->activate();

    Livewire::actingAs($admin)
        ->test(EditRoutine::class, ['record' => $routine->getRouteKey()])
        ->set('data.days', [])
        ->fillForm([
            'name' => 'Side Effect Free v2',
            'days' => [
                ['day_number' => 1, 'exercises' => [
                    ['exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
                ]],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Routine::count())->toBe(2);
    expect(DB::table('routine_days')->count())->toBe(2);
    expect(DB::table('routine_exercises')->count())->toBe(2);
    expect(DB::table('routine_assignments')->count())->toBe(0);
    expect(DB::table('users')->count())->toBe(1); // only the acting admin
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('exercises')->count())->toBe(1);
});
