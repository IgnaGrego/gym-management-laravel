<?php

use App\Models\Client;
use App\Models\Role;
use Livewire\Livewire;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use Illuminate\Auth\Access\AuthorizationException;

it('allows ADMIN to approve a pending registration and rejects non-admin policy access', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = Client::factory()->pending()->create();

    expect($admin->can('approve', $client))->toBeTrue()
        ->and($trainer->can('approve', $client))->toBeFalse();

    Livewire::actingAs($admin)->test(ListClients::class)
        ->callTableAction('approve', $client);

    expect($client->fresh()->isActive())->toBeTrue();
});

it('allows ADMIN to reject pending registrations and guards non-pending transitions', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->pending()->create();
    $client->reject();

    expect($admin->can('reject', $client))->toBeTrue();
    expect(fn () => $client->fresh()->reject())->toThrow(DomainException::class);
});
