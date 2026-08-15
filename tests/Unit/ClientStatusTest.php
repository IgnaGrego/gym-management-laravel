<?php

use App\Models\Client;
use App\Models\Role;

it('defines the three client statuses and defaults new clients to active', function () {
    expect([Client::STATUS_PENDING, Client::STATUS_ACTIVE, Client::STATUS_REJECTED])
        ->toBe(['pending', 'active', 'rejected']);
    expect(Client::factory()->create()->status)->toBe(Client::STATUS_ACTIVE);
});

it('approves pending clients and activates their linked user', function () {
    $user = userWithRoles([Role::CLIENT], ['is_active' => false]);
    $client = Client::factory()->pending()->create();
    $client->user()->associate($user);
    $client->save();

    $client->approve();

    expect($client->fresh()->isActive())->toBeTrue()
        ->and($user->fresh()->is_active)->toBeTrue();
});

it('rejects pending clients without activating their user and guards terminal transitions', function () {
    $user = userWithRoles([Role::CLIENT], ['is_active' => false]);
    $client = Client::factory()->pending()->create();
    $client->user()->associate($user);
    $client->save();
    $client->reject();

    expect($client->fresh()->isRejected())->toBeTrue()
        ->and($user->fresh()->is_active)->toBeFalse();
    expect(fn () => $client->approve())->toThrow(DomainException::class);
    expect(fn () => $client->reject())->toThrow(DomainException::class);
});

it('filters pending clients', function () {
    Client::factory()->pending()->create();
    Client::factory()->create();

    expect(Client::pending()->count())->toBe(1);
});
