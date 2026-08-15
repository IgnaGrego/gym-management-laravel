<?php

use App\Models\Attendance;
use App\Models\Role;

/*
 * AttendancePolicy authorization tests (SPEC-008 BR-009, BR-008; ERR-007,
 * ERR-008; AC-9, AC-10). All assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view and create attendance records', function () {
    // AC-1/AC-9 (BR-009, AT-01): ADMIN and TRAINER receive the full
    // attendance set (list, view detail, record check-in).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $attendance = Attendance::factory()->create();

    expect($admin->can('viewAny', Attendance::class))->toBeTrue();
    expect($admin->can('view', $attendance))->toBeTrue();
    expect($admin->can('create', Attendance::class))->toBeTrue();

    expect($trainer->can('viewAny', Attendance::class))->toBeTrue();
    expect($trainer->can('view', $attendance))->toBeTrue();
    expect($trainer->can('create', Attendance::class))->toBeTrue();
});

it('denies CLIENT every attendance ability', function () {
    // AC-9 (ERR-007, BR-009): CLIENT has no attendance access at this stage;
    // client self-view belongs to SPEC-013.
    $client = userWithRoles([Role::CLIENT]);
    $attendance = Attendance::factory()->create();

    expect($client->can('viewAny', Attendance::class))->toBeFalse();
    expect($client->can('view', $attendance))->toBeFalse();
    expect($client->can('create', Attendance::class))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users attendance management', function () {
    // AC-10 (SPEC-001 BR-002, AF-005): a staff user who is also CLIENT
    // receives the union of permissions and can manage attendance in the
    // admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $attendance = Attendance::factory()->create();

    expect($adminClient->can('viewAny', Attendance::class))->toBeTrue();
    expect($adminClient->can('view', $attendance))->toBeTrue();
    expect($adminClient->can('create', Attendance::class))->toBeTrue();

    expect($trainerClient->can('viewAny', Attendance::class))->toBeTrue();
    expect($trainerClient->can('view', $attendance))->toBeTrue();
    expect($trainerClient->can('create', Attendance::class))->toBeTrue();
});

it('never allows editing or deleting attendance records', function () {
    // AC-8, ERR-008 (BR-008): no update and no delete policy ability is
    // registered; editing or deleting is denied for everyone, including
    // ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $attendance = Attendance::factory()->create();

    expect($admin->can('update', $attendance))->toBeFalse();
    expect($admin->can('delete', $attendance))->toBeFalse();
    expect($trainer->can('update', $attendance))->toBeFalse();
    expect($trainer->can('delete', $attendance))->toBeFalse();
    expect($client->can('update', $attendance))->toBeFalse();
    expect($client->can('delete', $attendance))->toBeFalse();
});

it('redirects guests to the login page for the attendance pages', function () {
    // ERR-007: anonymous visitors never reach attendance data.
    $this->get('/admin/attendances')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the attendance pages', function () {
    // AC-9 (ERR-007, BR-009): 200 for staff, 403 for CLIENT.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $attendance = Attendance::factory()->create();

    $this->actingAs($admin)->get('/admin/attendances')->assertOk();
    $this->actingAs($admin)->get("/admin/attendances/{$attendance->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/attendances')->assertOk();
    $this->actingAs($trainer)->get("/admin/attendances/{$attendance->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/attendances')->assertForbidden();
    $this->actingAs($client)->get("/admin/attendances/{$attendance->getRouteKey()}")->assertForbidden();
});
