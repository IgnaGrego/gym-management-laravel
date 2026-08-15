<?php

use App\Models\Client;
use App\Models\Role;
use App\Models\WorkoutLog;

/*
 * WorkoutLogPolicy authorization tests (SPEC-011 BR-007, BR-009; ERR-006,
 * ERR-007; AC-12, AC-13, AC-14). The TRAINER reachability of the progress
 * surface (ClientProgress) is asserted server-side on the standalone
 * WorkoutLogResource routes — the UI-placement constraint of SPEC-011 §2/§9
 * (the progress view must not live only inside the ADMIN-only ClientResource).
 * All assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view and create workout logs', function () {
    // AC-13 (BR-007, WL-03, WL-09): the full workout-log set for both staff
    // roles — list, view detail and record (assigned-routine or free). Logging
    // and review are role-based, not ownership-based: any staff member may
    // operate on any log regardless of recorded_by (BR-009).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $log = WorkoutLog::factory()->create();

    expect($admin->can('viewAny', WorkoutLog::class))->toBeTrue();
    expect($admin->can('view', $log))->toBeTrue();
    expect($admin->can('create', WorkoutLog::class))->toBeTrue();

    expect($trainer->can('viewAny', WorkoutLog::class))->toBeTrue();
    expect($trainer->can('view', $log))->toBeTrue();
    expect($trainer->can('create', WorkoutLog::class))->toBeTrue();
});

it('denies CLIENT every workout-log ability', function () {
    // AC-13 (ERR-006, BR-007, C-13): CLIENT has no log access at this stage;
    // a client's visibility of their OWN logs is deferred to SPEC-013.
    $client = userWithRoles([Role::CLIENT]);
    $log = WorkoutLog::factory()->create();

    expect($client->can('viewAny', WorkoutLog::class))->toBeFalse();
    expect($client->can('view', $log))->toBeFalse();
    expect($client->can('create', WorkoutLog::class))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users workout-log management', function () {
    // AC-14 (SPEC-001 BR-002): a multi-role user receives the union of
    // permissions; a staff user who is also CLIENT can log and review in the
    // admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $log = WorkoutLog::factory()->create();

    expect($adminClient->can('viewAny', WorkoutLog::class))->toBeTrue();
    expect($adminClient->can('view', $log))->toBeTrue();
    expect($adminClient->can('create', WorkoutLog::class))->toBeTrue();

    expect($trainerClient->can('viewAny', WorkoutLog::class))->toBeTrue();
    expect($trainerClient->can('view', $log))->toBeTrue();
    expect($trainerClient->can('create', WorkoutLog::class))->toBeTrue();
});

it('never allows editing or deleting workout logs', function () {
    // AC-12, ERR-007 (BR-006): no update and no delete policy ability is
    // registered — logs are immutable event-log entries, so editing or
    // deleting is denied for everyone, including ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $log = WorkoutLog::factory()->create();

    expect($admin->can('update', $log))->toBeFalse();
    expect($admin->can('delete', $log))->toBeFalse();
    expect($trainer->can('update', $log))->toBeFalse();
    expect($trainer->can('delete', $log))->toBeFalse();
    expect($client->can('update', $log))->toBeFalse();
    expect($client->can('delete', $log))->toBeFalse();
});

it('redirects guests to the login page for the workout-log pages', function () {
    // ERR-006 (BR-007): anonymous visitors never reach workout-log data — the
    // list, the create page and the per-client progress page.
    $this->get('/admin/workout-logs')->assertRedirect('/login');
    $this->get('/admin/workout-logs/create')->assertRedirect('/login');
    $this->get('/admin/workout-logs/progress/1')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the workout-log pages', function () {
    // AC-13 (ERR-006, BR-007): 200 for staff, 403 for CLIENT on the list,
    // create and detail routes (the panel access gate, SPEC-001 FR-008).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $log = WorkoutLog::factory()->create();

    $this->actingAs($admin)->get('/admin/workout-logs')->assertOk();
    $this->actingAs($admin)->get('/admin/workout-logs/create')->assertOk();
    $this->actingAs($admin)->get("/admin/workout-logs/{$log->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/workout-logs')->assertOk();
    $this->actingAs($trainer)->get('/admin/workout-logs/create')->assertOk();
    $this->actingAs($trainer)->get("/admin/workout-logs/{$log->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/workout-logs')->assertForbidden();
    $this->actingAs($client)->get('/admin/workout-logs/create')->assertForbidden();
    $this->actingAs($client)->get("/admin/workout-logs/{$log->getRouteKey()}")->assertForbidden();
});

it('lets TRAINER reach the client progress page and denies CLIENT', function () {
    // UI-placement constraint (SPEC-011 §2/§9, AC-8/AC-9, C-03): the progress
    // surface lives on the standalone ADMIN|TRAINER WorkoutLogResource (never
    // inside the ADMIN-only ClientResource), so TRAINER can reach a client's
    // history and prescription-vs-actual comparison. CLIENT is denied (C-13);
    // the guest redirect is asserted in the dedicated guest test above
    // (ERR-006).
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $clientRecord = Client::factory()->create();

    $this->actingAs($trainer)
        ->get("/admin/workout-logs/progress/{$clientRecord->id}")
        ->assertOk();

    $this->actingAs($client)
        ->get("/admin/workout-logs/progress/{$clientRecord->id}")
        ->assertForbidden();
});
