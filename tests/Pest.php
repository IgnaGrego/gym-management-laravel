<?php

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class)->in('Feature');
uses(\Tests\TestCase::class, RefreshDatabase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared helpers (SPEC-001 tests)
|--------------------------------------------------------------------------
*/

/**
 * Find or create a role by name.
 */
function role(string $name): Role
{
    return Role::firstOrCreate(['name' => $name]);
}

/**
 * Create a user with the given role names (factory password: 'password').
 *
 * @param  array<int, string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function userWithRoles(array $roles, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    foreach ($roles as $roleName) {
        $user->roles()->attach(role($roleName));
    }

    return $user;
}

/**
 * Create a Client linked to a freshly created CLIENT-role User (SPEC-015).
 *
 * The link is written through user()->associate() — the same provisioning
 * path used by the application (SPEC-002 BR-003). The Client record is
 * returned so tests can assert on its profile fields and authenticate via
 * `$client->user`.
 *
 * @param  array<string, mixed>  $clientAttributes
 * @param  array<string, mixed>  $userAttributes
 */
function clientWithUser(array $clientAttributes = [], array $userAttributes = []): Client
{
    $user = userWithRoles([Role::CLIENT], $userAttributes);

    $client = Client::factory()->create($clientAttributes);
    $client->user()->associate($user);
    $client->save();

    return $client;
}
