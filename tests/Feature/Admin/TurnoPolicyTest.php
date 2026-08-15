<?php

use App\Models\Role;
use App\Models\Turno;

/*
 * TurnoPolicy authorization tests (SPEC-006 BR-012, BR-009; ERR-005; AC-11,
 * AC-13). All assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view, create and update turnos', function () {
    // AC-11 (BR-012, AS-01): the first module Policy granting management to
    // TRAINER as well as ADMIN.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $turno = Turno::factory()->create();

    expect($admin->can('viewAny', Turno::class))->toBeTrue();
    expect($admin->can('view', $turno))->toBeTrue();
    expect($admin->can('create', Turno::class))->toBeTrue();
    expect($admin->can('update', $turno))->toBeTrue();

    expect($trainer->can('viewAny', Turno::class))->toBeTrue();
    expect($trainer->can('view', $turno))->toBeTrue();
    expect($trainer->can('create', Turno::class))->toBeTrue();
    expect($trainer->can('update', $turno))->toBeTrue();
});

it('denies CLIENT every turno ability', function () {
    // AC-11 (ERR-005, BR-012): CLIENT has no scheduling access at this stage.
    $client = userWithRoles([Role::CLIENT]);
    $turno = Turno::factory()->create();

    expect($client->can('viewAny', Turno::class))->toBeFalse();
    expect($client->can('view', $turno))->toBeFalse();
    expect($client->can('create', Turno::class))->toBeFalse();
    expect($client->can('update', $turno))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users turno management', function () {
    // SPEC-001 BR-002: a multi-role user receives the union of permissions; a
    // staff user who is also CLIENT can manage turnos in the admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $turno = Turno::factory()->create();

    expect($adminClient->can('viewAny', Turno::class))->toBeTrue();
    expect($adminClient->can('view', $turno))->toBeTrue();
    expect($adminClient->can('create', Turno::class))->toBeTrue();
    expect($adminClient->can('update', $turno))->toBeTrue();

    expect($trainerClient->can('viewAny', Turno::class))->toBeTrue();
    expect($trainerClient->can('view', $turno))->toBeTrue();
    expect($trainerClient->can('create', Turno::class))->toBeTrue();
    expect($trainerClient->can('update', $turno))->toBeTrue();
});

it('never allows hard deletion of turno records', function () {
    // AC-13 (BR-009): no delete policy is registered; deletion is denied for
    // everyone, including ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $turno = Turno::factory()->create();

    expect($admin->can('delete', $turno))->toBeFalse();
    expect($trainer->can('delete', $turno))->toBeFalse();
});

it('redirects guests to the login page for the turno pages', function () {
    // ERR-005 (BR-012): anonymous visitors never reach scheduling data.
    $this->get('/admin/turnos')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the turno pages', function () {
    // AC-11 (ERR-005, BR-012): 200 for staff, 403 for CLIENT.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $turno = Turno::factory()->create();

    $this->actingAs($admin)->get('/admin/turnos')->assertOk();
    $this->actingAs($admin)->get("/admin/turnos/{$turno->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/turnos')->assertOk();
    $this->actingAs($trainer)->get("/admin/turnos/{$turno->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/turnos')->assertForbidden();
    $this->actingAs($client)->get("/admin/turnos/{$turno->getRouteKey()}")->assertForbidden();
});
