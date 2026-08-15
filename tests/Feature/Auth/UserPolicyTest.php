<?php

use App\Models\Role;
use App\Models\User;

/*
 * UserPolicy authorization tests (SPEC-001 FR-007, BR-006, BR-007; AC-6, AC-7).
 */

it('allows only ADMIN to create and update users', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $target = User::factory()->create();

    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $target))->toBeTrue();

    expect($trainer->can('create', User::class))->toBeFalse();
    expect($trainer->can('update', $target))->toBeFalse();

    expect($client->can('create', User::class))->toBeFalse();
    expect($client->can('update', $target))->toBeFalse();
});

it('allows only ADMIN to view user management', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $target = User::factory()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('view', $target))->toBeTrue();

    expect($trainer->can('viewAny', User::class))->toBeFalse();
    expect($trainer->can('view', $target))->toBeFalse();

    expect($client->can('viewAny', User::class))->toBeFalse();
    expect($client->can('view', $target))->toBeFalse();
});

it('never allows hard deletion of user records', function () {
    // BR-007: no delete policy is registered; deletion is denied for everyone.
    $admin = userWithRoles([Role::ADMIN]);
    $target = User::factory()->create();

    expect($admin->can('delete', $target))->toBeFalse();
});
