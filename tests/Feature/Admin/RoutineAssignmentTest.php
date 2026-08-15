<?php

use App\Actions\AssignRoutine;
use App\Actions\VersionRoutine;
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
 * Routine assignment feature tests (SPEC-010 FR-009..FR-011; BR-007, BR-008;
 * AF-002, AF-006; ERR-008; AC-8, AC-10..AC-13).
 */

it('allows ADMIN to assign an active routine version to one or more clients via the view action', function () {
    // AC-10 (FR-009, BR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['name' => 'Assigned Routine', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $clientA = Client::factory()->create(['full_name' => 'Alice Client']);
    $clientB = Client::factory()->create(['full_name' => 'Bob Client']);

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $routine->getRouteKey()])
        ->callAction('assign', data: ['client_ids' => [$clientA->id, $clientB->id]]);

    expect(RoutineAssignment::count())->toBe(2);

    $assignmentA = RoutineAssignment::where('client_id', $clientA->id)->firstOrFail();

    expect($assignmentA->routine_id)->toBe($routine->id);
    expect($assignmentA->assigned_at)->not->toBeNull();
    expect($assignmentA->is_active)->toBeTrue();

    expect(RoutineAssignment::where('client_id', $clientB->id)->where('is_active', true)->count())->toBe(1);
    expect($clientA->currentRoutine()->id)->toBe($routine->id);
});

it('supersedes a previous active assignment and preserves history', function () {
    // AC-11 (AF-002, BR-007, AR-03).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $exercise = Exercise::factory()->create();
    $oldRoutine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $oldRoutine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $newRoutine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $client = Client::factory()->create();

    app(AssignRoutine::class)->handle($oldRoutine, [$client], now()->subDay());

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $newRoutine->getRouteKey()])
        ->callAction('assign', data: ['client_ids' => [$client->id]]);

    expect(RoutineAssignment::where('client_id', $client->id)->count())->toBe(2);
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->count())->toBe(1);
    expect($client->currentRoutine()->id)->toBe($newRoutine->id);

    $oldAssignment = RoutineAssignment::where('client_id', $client->id)->where('routine_id', $oldRoutine->id)->firstOrFail();

    expect($oldAssignment->is_active)->toBeFalse();
    expect(RoutineAssignment::find($oldAssignment->id))->not->toBeNull();
});

it('rejects assigning a draft or archived version', function () {
    // AC-12 (ERR-008, BR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $draft = Routine::factory()->create(['created_by' => $admin->id]);
    $archived = Routine::factory()->create(['status' => Routine::STATUS_ARCHIVED, 'created_by' => $admin->id]);
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $draft->getRouteKey()])
        ->assertActionHidden('assign');

    Livewire::actingAs($admin)
        ->test(ViewRoutine::class, ['record' => $archived->getRouteKey()])
        ->assertActionHidden('assign');

    expect(fn () => app(AssignRoutine::class)->handle($draft, [$client]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(AssignRoutine::class)->handle($archived, [$client]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(RoutineAssignment::count())->toBe(0);
});

it('reassigns a client to another version and unassigns without replacement, preserving history', function () {
    // AC-13 (FR-010, AF-006, BR-008).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Lineage v1', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $v2 = Routine::factory()->create(['name' => 'Lineage v2', 'status' => Routine::STATUS_ACTIVE, 'version_number' => 2, 'replaces_id' => $v1->id, 'created_by' => $admin->id]);
    $client = Client::factory()->create();

    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());
    app(AssignRoutine::class)->handle($v2, [$client]);

    expect($client->currentRoutine()->id)->toBe($v2->id);
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->count())->toBe(1);

    // Unassign without replacement (AF-006): the active row is deactivated.
    $activeAssignment = RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->firstOrFail();
    $activeAssignment->deactivate();

    expect($client->currentRoutine())->toBeNull();
    expect(RoutineAssignment::where('client_id', $client->id)->count())->toBe(2);
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->count())->toBe(0);
});

it('keeps clients on the old version after an edit creates a new version until reassigned', function () {
    // AC-8 (FR-010, AF-003, D-12 option 3).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $exercise = Exercise::factory()->create();
    $v1 = Routine::factory()->create(['name' => 'Routine', 'status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $v1->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

    $client = Client::factory()->create();
    app(AssignRoutine::class)->handle($v1, [$client], now()->subDay());

    $v2 = app(VersionRoutine::class)->handle($v1, $admin, [
        'name' => 'Routine v2',
        'days' => [
            ['day_number' => 1, 'exercises' => [
                ['id' => $day->exercises()->first()->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'target_reps' => 10],
            ]],
        ],
    ]);

    expect($client->currentRoutine()->id)->toBe($v1->id);
    expect(RoutineAssignment::where('client_id', $client->id)->where('is_active', true)->first()->routine_id)->toBe($v1->id);

    app(AssignRoutine::class)->handle($v2, [$client]);

    expect($client->currentRoutine()->id)->toBe($v2->id);
});

it('unassigns an active assignment via the assigned-clients relation manager row action', function () {
    // FR-010 (the Unassign row action calls RoutineAssignment::deactivate()).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $client = Client::factory()->create();
    $assignment = RoutineAssignment::factory()->create([
        'client_id' => $client->id,
        'routine_id' => $routine->id,
        'assigned_at' => now(),
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(\App\Filament\Resources\RoutineResource\RelationManagers\AssignmentsRelationManager::class, [
            'ownerRecord' => $routine,
            'pageClass' => ViewRoutine::class,
        ])
        ->callTableAction('unassign', $assignment);

    expect($assignment->fresh()->is_active)->toBeFalse();
    expect(RoutineAssignment::find($assignment->id))->not->toBeNull();
});

it('assignment operations never touch prescription rows', function () {
    // BR-007 (AC-17).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE, 'created_by' => $admin->id]);
    $day = RoutineDay::factory()->create(['routine_id' => $routine->id, 'day_number' => 1]);
    RoutineExercise::factory()->create(['routine_day_id' => $day->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
    $client = Client::factory()->create();

    app(AssignRoutine::class)->handle($routine, [$client]);
    $assignment = RoutineAssignment::where('client_id', $client->id)->firstOrFail();
    $assignment->deactivate();

    expect(RoutineDay::count())->toBe(1);
    expect(RoutineExercise::count())->toBe(1);
    expect(RoutineExercise::first()->target_reps)->toBe(10);

    // No prescription, client or exercise record was modified by the
    // assignment operations.
    expect(DB::table('routine_exercises')->count())->toBe(1);
    expect(DB::table('exercises')->count())->toBe(1);
    expect(DB::table('clients')->count())->toBe(1);
});
