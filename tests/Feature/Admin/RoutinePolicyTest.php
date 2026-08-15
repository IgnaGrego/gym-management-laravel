<?php

use App\Models\Client;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;

/*
 * Routine / RoutineAssignment policy authorization tests (SPEC-010 BR-009,
 * BR-008, BR-011; ERR-007, ERR-009; AC-15, AC-16, AC-19; AR-08). All
 * assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view, create and update routines and assignments', function () {
    // AC-15 (BR-009, AR-08): the full management set for both staff roles.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $routine = Routine::factory()->create(['created_by' => $admin->id]);
    $assignment = RoutineAssignment::factory()->create(['routine_id' => $routine->id]);

    expect($admin->can('viewAny', Routine::class))->toBeTrue();
    expect($admin->can('view', $routine))->toBeTrue();
    expect($admin->can('create', Routine::class))->toBeTrue();
    expect($admin->can('update', $routine))->toBeTrue();

    expect($admin->can('viewAny', RoutineAssignment::class))->toBeTrue();
    expect($admin->can('view', $assignment))->toBeTrue();
    expect($admin->can('update', $assignment))->toBeTrue();

    expect($trainer->can('viewAny', Routine::class))->toBeTrue();
    expect($trainer->can('view', $routine))->toBeTrue();
    expect($trainer->can('create', Routine::class))->toBeTrue();
    expect($trainer->can('update', $routine))->toBeTrue();

    expect($trainer->can('viewAny', RoutineAssignment::class))->toBeTrue();
    expect($trainer->can('view', $assignment))->toBeTrue();
    expect($trainer->can('update', $assignment))->toBeTrue();
});

it('denies CLIENT every routine ability', function () {
    // AC-15 (ERR-007, BR-009, AR-08): CLIENT has no routine access in this
    // Specification; client visibility is deferred to SPEC-011 / SPEC-013.
    $client = userWithRoles([Role::CLIENT]);
    $routine = Routine::factory()->create();
    $assignment = RoutineAssignment::factory()->create(['routine_id' => $routine->id]);

    expect($client->can('viewAny', Routine::class))->toBeFalse();
    expect($client->can('view', $routine))->toBeFalse();
    expect($client->can('create', Routine::class))->toBeFalse();
    expect($client->can('update', $routine))->toBeFalse();

    expect($client->can('viewAny', RoutineAssignment::class))->toBeFalse();
    expect($client->can('view', $assignment))->toBeFalse();
    expect($client->can('update', $assignment))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users routine management', function () {
    // AC-19 (SPEC-001 BR-002): a multi-role user receives the union of
    // permissions; a staff user who is also CLIENT can manage routines in the
    // admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $routine = Routine::factory()->create(['created_by' => $adminClient->id]);

    expect($adminClient->can('viewAny', Routine::class))->toBeTrue();
    expect($adminClient->can('view', $routine))->toBeTrue();
    expect($adminClient->can('create', Routine::class))->toBeTrue();
    expect($adminClient->can('update', $routine))->toBeTrue();

    expect($trainerClient->can('viewAny', Routine::class))->toBeTrue();
    expect($trainerClient->can('view', $routine))->toBeTrue();
    expect($trainerClient->can('create', Routine::class))->toBeTrue();
    expect($trainerClient->can('update', $routine))->toBeTrue();
});

it('never allows hard deletion of routines or assignments', function () {
    // AC-16 (ERR-009, BR-008): no delete policy is registered for either
    // model; deletion is denied for everyone, including ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $routine = Routine::factory()->create(['created_by' => $admin->id]);
    $assignment = RoutineAssignment::factory()->create(['routine_id' => $routine->id]);

    expect($admin->can('delete', $routine))->toBeFalse();
    expect($trainer->can('delete', $routine))->toBeFalse();
    expect($admin->can('delete', $assignment))->toBeFalse();
    expect($trainer->can('delete', $assignment))->toBeFalse();
});

it('redirects guests to the login page for the routine pages', function () {
    // ERR-007 (BR-009): anonymous visitors never reach routine data.
    $this->get('/admin/routines')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the routine pages', function () {
    // AC-15 (ERR-007, BR-009): 200 for staff, 403 for CLIENT.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $routine = Routine::factory()->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->get('/admin/routines')->assertOk();
    $this->actingAs($admin)->get("/admin/routines/{$routine->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/routines')->assertOk();
    $this->actingAs($trainer)->get("/admin/routines/{$routine->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/routines')->assertForbidden();
    $this->actingAs($client)->get("/admin/routines/{$routine->getRouteKey()}")->assertForbidden();
});
