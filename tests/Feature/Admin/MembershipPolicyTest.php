<?php

use App\Models\Membership;
use App\Models\Role;

/*
 * MembershipPolicy authorization tests (SPEC-004 BR-015, BR-014; ERR-006;
 * AC-16, AC-17). All assertions are server-side (AGENTS.md §17).
 */

it('allows only ADMIN to view, create and update memberships', function () {
    // AC-16 (ERR-006, BR-015): TRAINER and CLIENT cannot manage memberships.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $membership = Membership::factory()->create();

    expect($admin->can('viewAny', Membership::class))->toBeTrue();
    expect($admin->can('view', $membership))->toBeTrue();
    expect($admin->can('create', Membership::class))->toBeTrue();
    expect($admin->can('update', $membership))->toBeTrue();

    expect($trainer->can('viewAny', Membership::class))->toBeFalse();
    expect($trainer->can('view', $membership))->toBeFalse();
    expect($trainer->can('create', Membership::class))->toBeFalse();
    expect($trainer->can('update', $membership))->toBeFalse();

    expect($client->can('viewAny', Membership::class))->toBeFalse();
    expect($client->can('view', $membership))->toBeFalse();
    expect($client->can('create', Membership::class))->toBeFalse();
    expect($client->can('update', $membership))->toBeFalse();
});

it('grants a multi-role ADMIN + CLIENT user membership management', function () {
    // SPEC-001 BR-002: a multi-role user receives the union of permissions;
    // an ADMIN who is also CLIENT can manage memberships in the admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $membership = Membership::factory()->create();

    expect($adminClient->can('viewAny', Membership::class))->toBeTrue();
    expect($adminClient->can('view', $membership))->toBeTrue();
    expect($adminClient->can('create', Membership::class))->toBeTrue();
    expect($adminClient->can('update', $membership))->toBeTrue();
});

it('never allows hard deletion of membership records', function () {
    // AC-17 (BR-014): no delete policy is registered; deletion is denied for
    // everyone, including ADMIN.
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create();

    expect($admin->can('delete', $membership))->toBeFalse();
});

it('denies TRAINER and CLIENT access to the membership pages with 403', function () {
    // AC-16 (ERR-006, BR-015).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $membership = Membership::factory()->create();

    $this->actingAs($admin)->get('/admin/memberships')->assertOk();
    $this->actingAs($admin)->get("/admin/memberships/{$membership->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/memberships')->assertForbidden();
    $this->actingAs($trainer)->get("/admin/memberships/{$membership->getRouteKey()}")->assertForbidden();

    $this->actingAs($client)->get('/admin/memberships')->assertForbidden();
    $this->actingAs($client)->get("/admin/memberships/{$membership->getRouteKey()}")->assertForbidden();
});
