<?php

use App\Models\Exercise;
use App\Models\Role;

/*
 * ExercisePolicy authorization tests (SPEC-009 BR-009, BR-008; ERR-006,
 * ERR-007; AC-11, AC-12, AC-14). All assertions are server-side
 * (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view, create and update exercises', function () {
    // AC-11 (BR-009, EX-08; D-20 option 2): the full management set for both
    // staff roles.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $exercise = Exercise::factory()->create();

    expect($admin->can('viewAny', Exercise::class))->toBeTrue();
    expect($admin->can('view', $exercise))->toBeTrue();
    expect($admin->can('create', Exercise::class))->toBeTrue();
    expect($admin->can('update', $exercise))->toBeTrue();

    expect($trainer->can('viewAny', Exercise::class))->toBeTrue();
    expect($trainer->can('view', $exercise))->toBeTrue();
    expect($trainer->can('create', Exercise::class))->toBeTrue();
    expect($trainer->can('update', $exercise))->toBeTrue();
});

it('denies CLIENT every exercise ability', function () {
    // AC-11 (ERR-006, BR-009): CLIENT has no catalogue access at this stage
    // (SPEC-009 EX-08); client visibility of exercise names is deferred to
    // SPEC-010/011/013.
    $client = userWithRoles([Role::CLIENT]);
    $exercise = Exercise::factory()->create();

    expect($client->can('viewAny', Exercise::class))->toBeFalse();
    expect($client->can('view', $exercise))->toBeFalse();
    expect($client->can('create', Exercise::class))->toBeFalse();
    expect($client->can('update', $exercise))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users exercise management', function () {
    // AC-14 (SPEC-001 BR-002): a multi-role user receives the union of
    // permissions; a staff user who is also CLIENT can manage exercises in
    // the admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $exercise = Exercise::factory()->create();

    expect($adminClient->can('viewAny', Exercise::class))->toBeTrue();
    expect($adminClient->can('view', $exercise))->toBeTrue();
    expect($adminClient->can('create', Exercise::class))->toBeTrue();
    expect($adminClient->can('update', $exercise))->toBeTrue();

    expect($trainerClient->can('viewAny', Exercise::class))->toBeTrue();
    expect($trainerClient->can('view', $exercise))->toBeTrue();
    expect($trainerClient->can('create', Exercise::class))->toBeTrue();
    expect($trainerClient->can('update', $exercise))->toBeTrue();
});

it('never allows hard deletion of exercise records', function () {
    // AC-12 (ERR-007, BR-008): no delete policy is registered; deletion is
    // denied for everyone, including ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $exercise = Exercise::factory()->create();

    expect($admin->can('delete', $exercise))->toBeFalse();
    expect($trainer->can('delete', $exercise))->toBeFalse();
});

it('redirects guests to the login page for the exercise pages', function () {
    // ERR-006 (BR-009): anonymous visitors never reach catalogue data.
    $this->get('/admin/exercises')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the exercise pages', function () {
    // AC-11 (ERR-006, BR-009): 200 for staff, 403 for CLIENT.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $exercise = Exercise::factory()->create();

    $this->actingAs($admin)->get('/admin/exercises')->assertOk();
    $this->actingAs($admin)->get("/admin/exercises/{$exercise->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/exercises')->assertOk();
    $this->actingAs($trainer)->get("/admin/exercises/{$exercise->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/exercises')->assertForbidden();
    $this->actingAs($client)->get("/admin/exercises/{$exercise->getRouteKey()}")->assertForbidden();
});
