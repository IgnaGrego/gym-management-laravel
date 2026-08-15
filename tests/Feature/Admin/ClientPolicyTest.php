<?php

use App\Actions\ProvisionClientUser;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/*
 * ClientPolicy authorization tests (SPEC-002 BR-004, BR-007; ERR-005; AC-10,
 * AC-11, AC-12). All assertions are server-side (AGENTS.md §17).
 */

it('allows only ADMIN to view, create and update clients', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $target = Client::factory()->create();

    expect($admin->can('viewAny', Client::class))->toBeTrue();
    expect($admin->can('view', $target))->toBeTrue();
    expect($admin->can('create', Client::class))->toBeTrue();
    expect($admin->can('update', $target))->toBeTrue();

    expect($trainer->can('viewAny', Client::class))->toBeFalse();
    expect($trainer->can('view', $target))->toBeFalse();
    expect($trainer->can('create', Client::class))->toBeFalse();
    expect($trainer->can('update', $target))->toBeFalse();

    expect($client->can('viewAny', Client::class))->toBeFalse();
    expect($client->can('view', $target))->toBeFalse();
    expect($client->can('create', Client::class))->toBeFalse();
    expect($client->can('update', $target))->toBeFalse();
});

it('grants a multi-role ADMIN + CLIENT user client management', function () {
    // SPEC-001 BR-002: a multi-role user receives the union of permissions.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $target = Client::factory()->create();

    expect($adminClient->can('viewAny', Client::class))->toBeTrue();
    expect($adminClient->can('view', $target))->toBeTrue();
    expect($adminClient->can('create', Client::class))->toBeTrue();
    expect($adminClient->can('update', $target))->toBeTrue();
});

it('never allows hard deletion of client records', function () {
    // AC-11 (BR-006): no delete policy is registered; deletion is denied for
    // everyone, including ADMIN.
    $admin = userWithRoles([Role::ADMIN]);
    $target = Client::factory()->create();

    expect($admin->can('delete', $target))->toBeFalse();
});

it('denies TRAINER and CLIENT access to the client pages with 403', function () {
    // AC-10 (ERR-005, BR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $target = Client::factory()->create();

    $this->actingAs($admin)->get('/admin/clients')->assertOk();
    $this->actingAs($admin)->get("/admin/clients/{$target->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/clients')->assertForbidden();
    $this->actingAs($trainer)->get("/admin/clients/{$target->getRouteKey()}")->assertForbidden();

    $this->actingAs($client)->get('/admin/clients')->assertForbidden();
    $this->actingAs($client)->get("/admin/clients/{$target->getRouteKey()}")->assertForbidden();
});

it('blocks non-ADMIN users inside the provisioning action', function () {
    // BR-004, AD-04: provisioning is ADMIN-only, enforced server-side inside
    // the Action (defense in depth beyond the Filament page gate).
    $trainer = userWithRoles([Role::TRAINER]);
    $client = Client::factory()->create();

    $this->actingAs($trainer);

    expect(fn () => app(ProvisionClientUser::class)->handle($client, 'trainer@gym.test', 'password'))
        ->toThrow(AuthorizationException::class);

    expect($client->fresh()->user_id)->toBeNull();
    expect(User::where('email', 'trainer@gym.test')->exists())->toBeFalse();
});
