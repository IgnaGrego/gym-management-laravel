<?php

use App\Models\Client;

/*
 * Portal profile-edit tests (SPEC-013 FR-011, BR-008; AC-15, AC-20; NC-01,
 * NC-02; ERR-011).
 */

beforeEach(function () {
    $this->withoutVite();
});

it('lets a CLIENT edit their own email, phone and emergency contact', function () {
    // AC-15 (FR-011, NC-01, CP-01).
    $client = clientWithUser([
        'email' => 'old@example.com',
        'phone' => 'old-phone',
        'emergency_contact' => 'Old contact',
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.profile.update'), [
            'email' => 'new@example.com',
            'phone' => 'new-phone',
            'emergency_contact' => 'New contact',
        ])
        ->assertRedirect(route('portal.profile'));

    $client->refresh();

    expect($client->email)->toBe('new@example.com')
        ->and($client->phone)->toBe('new-phone')
        ->and($client->emergency_contact)->toBe('New contact');
});

it('rejects an invalid email', function () {
    // AC-15 (ERR-011).
    $client = clientWithUser(['email' => 'old@example.com']);

    $this->actingAs($client->user)
        ->post(route('portal.profile.update'), [
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');

    expect($client->fresh()->email)->toBe('old@example.com');
});

it('never edits full_name, dni, status or the health notes', function () {
    // AC-15 (FR-011, BR-008, NC-01, ERR-011): the three whitelisted fields are
    // the only editable set; identity/lifecycle/health fields are ignored.
    $client = clientWithUser([
        'full_name' => 'Original Name',
        'dni' => '12345678',
        'status' => Client::STATUS_ACTIVE,
        'injuries_notes' => 'Original injury',
        'medical_conditions_notes' => 'Original condition',
    ]);

    $this->actingAs($client->user)
        ->post(route('portal.profile.update'), [
            'email' => 'changed@example.com',
            'full_name' => 'Hacked Name',
            'dni' => '99999999',
            'status' => Client::STATUS_REJECTED,
            'injuries_notes' => 'Hacked injury',
            'medical_conditions_notes' => 'Hacked condition',
        ])
        ->assertRedirect(route('portal.profile'));

    $client->refresh();

    expect($client->email)->toBe('changed@example.com')
        ->and($client->full_name)->toBe('Original Name')
        ->and($client->dni)->toBe('12345678')
        ->and($client->status)->toBe(Client::STATUS_ACTIVE)
        ->and($client->injuries_notes)->toBe('Original injury')
        ->and($client->medical_conditions_notes)->toBe('Original condition');
});

it('shows the client their own health notes read-only', function () {
    // AC-20 (FR-011, NC-02).
    $client = clientWithUser([
        'injuries_notes' => 'Knee injury',
        'medical_conditions_notes' => 'Asthma',
    ]);

    $this->actingAs($client->user)
        ->get('/portal/profile')
        ->assertOk()
        ->assertSee('Knee injury')
        ->assertSee('Asthma')
        ->assertSee('Notas de salud');
});

it('never exposes another client health notes', function () {
    // AC-20 (BR-002, C-13, NC-02).
    $alice = clientWithUser(['injuries_notes' => 'Alice injury']);
    $bob = clientWithUser(['injuries_notes' => 'Bob injury']);

    $this->actingAs($alice->user)
        ->get('/portal/profile')
        ->assertOk()
        ->assertSee('Alice injury')
        ->assertDontSee('Bob injury');
});
