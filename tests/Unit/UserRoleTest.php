<?php

use App\Models\Role;
use App\Models\User;

/*
 * Unit tests for the role helpers on User (SPEC-001 AC-9, BR-002; ADR-001).
 */

it('reports true for a role the user holds', function () {
    $user = userWithRoles([Role::ADMIN]);

    expect($user->hasRole(Role::ADMIN))->toBeTrue();
    expect($user->hasRole(Role::TRAINER))->toBeFalse();
});

it('reports true when the user holds at least one of the given roles', function () {
    $user = userWithRoles([Role::CLIENT]);

    expect($user->hasAnyRole([Role::ADMIN, Role::TRAINER]))->toBeFalse();
    expect($user->hasAnyRole([Role::TRAINER, Role::CLIENT]))->toBeTrue();
});

it('supports multiple roles simultaneously (union of roles)', function () {
    $user = userWithRoles([Role::TRAINER, Role::CLIENT]);

    expect($user->roles->pluck('name')->sort()->values()->all())
        ->toBe([Role::CLIENT, Role::TRAINER]);
    expect($user->hasRole(Role::TRAINER))->toBeTrue();
    expect($user->hasRole(Role::CLIENT))->toBeTrue();
    expect($user->hasAnyRole([Role::ADMIN, Role::TRAINER]))->toBeTrue();
});

it('returns false for a user with no roles', function () {
    $user = User::factory()->create();

    expect($user->hasRole(Role::ADMIN))->toBeFalse();
    expect($user->hasAnyRole([Role::ADMIN, Role::TRAINER, Role::CLIENT]))->toBeFalse();
});

it('exposes the fixed role catalog constants', function () {
    expect(Role::ADMIN)->toBe('ADMIN');
    expect(Role::TRAINER)->toBe('TRAINER');
    expect(Role::CLIENT)->toBe('CLIENT');
});
