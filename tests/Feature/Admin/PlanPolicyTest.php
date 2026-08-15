<?php

use App\Models\Plan;
use App\Models\Role;

/*
 * PlanPolicy authorization tests (SPEC-003 BR-006, BR-004; ERR-004; AC-9,
 * AC-10). All assertions are server-side (AGENTS.md §17).
 */

it('allows only ADMIN to view, create and update plans', function () {
    // AC-9 (ERR-004, BR-006): TRAINER and CLIENT cannot manage plans.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $plan = Plan::factory()->create();

    expect($admin->can('viewAny', Plan::class))->toBeTrue();
    expect($admin->can('view', $plan))->toBeTrue();
    expect($admin->can('create', Plan::class))->toBeTrue();
    expect($admin->can('update', $plan))->toBeTrue();

    expect($trainer->can('viewAny', Plan::class))->toBeFalse();
    expect($trainer->can('view', $plan))->toBeFalse();
    expect($trainer->can('create', Plan::class))->toBeFalse();
    expect($trainer->can('update', $plan))->toBeFalse();

    expect($client->can('viewAny', Plan::class))->toBeFalse();
    expect($client->can('view', $plan))->toBeFalse();
    expect($client->can('create', Plan::class))->toBeFalse();
    expect($client->can('update', $plan))->toBeFalse();
});

it('grants a multi-role ADMIN + CLIENT user plan management', function () {
    // SPEC-001 BR-002: a multi-role user receives the union of permissions.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $plan = Plan::factory()->create();

    expect($adminClient->can('viewAny', Plan::class))->toBeTrue();
    expect($adminClient->can('view', $plan))->toBeTrue();
    expect($adminClient->can('create', Plan::class))->toBeTrue();
    expect($adminClient->can('update', $plan))->toBeTrue();
});

it('never allows hard deletion of plan records', function () {
    // AC-10 (BR-004): no delete policy is registered; deletion is denied for
    // everyone, including ADMIN.
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create();

    expect($admin->can('delete', $plan))->toBeFalse();
});

it('denies TRAINER and CLIENT access to the plan pages with 403', function () {
    // AC-9 (ERR-004, BR-006).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $plan = Plan::factory()->create();

    $this->actingAs($admin)->get('/admin/plans')->assertOk();
    $this->actingAs($admin)->get("/admin/plans/{$plan->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/plans')->assertForbidden();
    $this->actingAs($trainer)->get("/admin/plans/{$plan->getRouteKey()}")->assertForbidden();

    $this->actingAs($client)->get('/admin/plans')->assertForbidden();
    $this->actingAs($client)->get("/admin/plans/{$plan->getRouteKey()}")->assertForbidden();
});
