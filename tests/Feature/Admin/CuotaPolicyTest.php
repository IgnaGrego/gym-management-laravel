<?php

use App\Models\Cuota;
use App\Models\Role;

/*
 * CuotaPolicy authorization tests (SPEC-005 BR-011, BR-009; PY-01; ERR-008;
 * AC-12, AC-13). All assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view cuotas and edit pending cuota amounts', function () {
    // AC-12 (BR-011, PY-01): ADMIN and TRAINER manage cuotas; CLIENT cannot.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $cuota = Cuota::factory()->create();

    expect($admin->can('viewAny', Cuota::class))->toBeTrue();
    expect($admin->can('view', $cuota))->toBeTrue();
    expect($admin->can('update', $cuota))->toBeTrue();

    expect($trainer->can('viewAny', Cuota::class))->toBeTrue();
    expect($trainer->can('view', $cuota))->toBeTrue();
    expect($trainer->can('update', $cuota))->toBeTrue();

    expect($client->can('viewAny', Cuota::class))->toBeFalse();
    expect($client->can('view', $cuota))->toBeFalse();
    expect($client->can('update', $cuota))->toBeFalse();
});

it('never allows creating or deleting cuotas', function () {
    // FR-001, BR-009: cuotas are auto-generated (no create ability) and never
    // hard-deleted (no delete ability).
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Cuota::factory()->create();

    expect($admin->can('create', Cuota::class))->toBeFalse();
    expect($admin->can('delete', $cuota))->toBeFalse();
});

it('denies CLIENT access to the cuota pages with 403', function () {
    // AC-12, ERR-008, BR-011.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $cuota = Cuota::factory()->create();

    $this->actingAs($admin)->get('/admin/cuotas')->assertOk();
    $this->actingAs($admin)->get("/admin/cuotas/{$cuota->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/cuotas')->assertOk();
    $this->actingAs($trainer)->get("/admin/cuotas/{$cuota->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/cuotas')->assertForbidden();
    $this->actingAs($client)->get("/admin/cuotas/{$cuota->getRouteKey()}")->assertForbidden();
});
