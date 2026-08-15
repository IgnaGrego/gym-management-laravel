<?php

use App\Models\Client;
use App\Models\Role;

/*
 * Client portal presentation tests (SPEC-015 FR-006, FR-007, BR-002, BR-003;
 * AC-7, AC-8, AC-9, AC-12, AC-15; AF-002; ERR-005).
 */

beforeEach(function () {
    $this->withoutVite();
});

it('shows the authenticated client their own profile data and status', function () {
    $client = clientWithUser([
        'full_name' => 'Juan Pérez',
        'dni' => '12345678',
        'email' => 'juan@example.com',
        'phone' => '+54 9 11 5555 1234',
        'status' => Client::STATUS_ACTIVE,
    ]);

    $this->actingAs($client->user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Client portal')
        ->assertSee('El Area Gym')
        ->assertSee('Juan Pérez')
        ->assertSee('12345678')
        ->assertSee('juan@example.com')
        ->assertSee('+54 9 11 5555 1234')
        ->assertSee(Client::STATUS_ACTIVE);
});

it('does not leak another client profile to the authenticated client', function () {
    $alice = clientWithUser([
        'full_name' => 'Alice Doe',
        'dni' => '11111111',
        'email' => 'alice@example.com',
        'phone' => '1111',
    ]);

    $bob = clientWithUser([
        'full_name' => 'Bob Smith',
        'dni' => '22222222',
        'email' => 'bob@example.com',
        'phone' => '2222',
    ]);

    $this->actingAs($alice->user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Alice Doe')
        ->assertSee('11111111')
        ->assertSee('alice@example.com')
        ->assertDontSee('Bob Smith')
        ->assertDontSee('bob@example.com')
        ->assertDontSee('22222222');
});

it('renders a neutral placeholder when optional contact fields are null', function () {
    $client = clientWithUser([
        'full_name' => 'Carol Null',
        'dni' => '33333333',
        'email' => null,
        'phone' => null,
    ]);

    $this->actingAs($client->user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Carol Null')
        ->assertSee('33333333')
        ->assertSee('Not provided');
});

it('shows the ERR-005 notice when a CLIENT has no linked Client record', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->actingAs($user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Client portal')
        ->assertSee('Perfil no disponible. Contactá a recepción.')
        ->assertDontSee('Not provided');
});

it('keeps the portal logout as a POST form with CSRF', function () {
    $client = clientWithUser();

    $this->actingAs($client->user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee(route('logout'), false)
        ->assertSee('name="_token"', false);
});
