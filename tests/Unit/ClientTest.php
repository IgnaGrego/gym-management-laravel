<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
 * Client model unit tests (SPEC-002 BR-003, BR-005, FR-006; AC-11).
 */

it('navigates the client-to-user and user-to-client relationships', function () {
    // BR-003: the optional 1:1 link is navigable from both sides.
    $client = Client::factory()->create();
    $user = User::factory()->create(['name' => $client->full_name]);

    $client->user()->associate($user);
    $client->save();

    expect($client->fresh()->user->is($user))->toBeTrue();
    expect($user->fresh()->client->is($client))->toBeTrue();
});

it('reports hasLinkedUser for linked and unlinked clients', function () {
    // FR-006: the display logic distinguishes linked vs unlinked records.
    $unlinked = Client::factory()->create();

    expect($unlinked->hasLinkedUser())->toBeFalse();

    $linked = Client::factory()->create();
    $linked->user()->associate(User::factory()->create());
    $linked->save();

    expect($linked->fresh()->hasLinkedUser())->toBeTrue();
});

it('rejects a duplicate dni via the unique database constraint', function () {
    // BR-005, ERR-001.
    Client::factory()->create(['dni' => '12345678']);

    expect(fn () => Client::factory()->create(['dni' => '12345678']))
        ->toThrow(QueryException::class);
});

it('rejects a second client linked to the same user via the unique user_id constraint', function () {
    // BR-003: the unique clients.user_id enforces the 1:1 link in both
    // directions; a linked account belongs to exactly one client.
    $user = User::factory()->create();

    $first = Client::factory()->create();
    $first->user()->associate($user);
    $first->save();

    $second = Client::factory()->create();
    $second->user()->associate($user);

    expect(fn () => $second->save())->toThrow(QueryException::class);
});

it('allows two unlinked clients to coexist without a user_id', function () {
    // BR-001: standalone records are the default; multiple clients without a
    // linked account are allowed (nullable unique column).
    $first = Client::factory()->create();
    $second = Client::factory()->create();

    expect($first->user_id)->toBeNull();
    expect($second->user_id)->toBeNull();
});
