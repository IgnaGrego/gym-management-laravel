<?php

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Client CRUD feature tests (SPEC-002 FR-001..FR-004, FR-007; BR-001,
 * BR-002, BR-005, BR-006; ERR-001, ERR-002, ERR-006; AC-1..AC-6, AC-11,
 * AC-12). Authorization is enforced server-side.
 */

it('allows ADMIN to create a client with required and optional fields', function () {
    // AC-1 (FR-001): name + DNI are required; contact and health fields are
    // optional (AD-01) and persisted.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Jane Doe',
            'dni' => '12345678',
            'email' => 'jane@gym.test',
            'phone' => '+54 11 5555 1234',
            'emergency_contact' => 'John Doe - +54 9 11 5555 5678',
            'injuries_notes' => 'Old knee injury.',
            'medical_conditions_notes' => 'Asthma.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = Client::where('dni', '12345678')->firstOrFail();

    expect($client->full_name)->toBe('Jane Doe');
    expect($client->email)->toBe('jane@gym.test');
    expect($client->phone)->toBe('+54 11 5555 1234');
    expect($client->emergency_contact)->toBe('John Doe - +54 9 11 5555 5678');
    expect($client->injuries_notes)->toBe('Old knee injury.');
    expect($client->medical_conditions_notes)->toBe('Asthma.');
    expect($client->user_id)->toBeNull();
});

it('allows ADMIN to create a client with only the required fields', function () {
    // AC-1 (BR-001, AD-01): a valid standalone record needs only name + DNI.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Minimal Client',
            'dni' => '87654321',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = Client::where('dni', '87654321')->firstOrFail();

    expect($client->full_name)->toBe('Minimal Client');
    expect($client->email)->toBeNull();
    expect($client->phone)->toBeNull();
    expect($client->injuries_notes)->toBeNull();
    expect($client->medical_conditions_notes)->toBeNull();
});

it('does not create a user account or role assignment when creating a client', function () {
    // AC-6 (BR-001, BR-002): creating a client never creates a User or a role
    // assignment; the account is optional and provisioned explicitly later.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Standalone Client',
            'dni' => '11223344',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role

    $client = Client::where('dni', '11223344')->firstOrFail();
    expect($client->hasLinkedUser())->toBeFalse();
});

it('rejects creating a client with a duplicate dni', function () {
    // AC-2 (ERR-001, BR-005).
    Client::factory()->create(['dni' => '11111111']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Another Client',
            'dni' => '11111111',
        ])
        ->call('create')
        ->assertHasFormErrors(['dni' => 'unique']);

    expect(Client::where('full_name', 'Another Client')->exists())->toBeFalse();
});

it('rejects editing a client onto another client dni', function () {
    // AC-2 (ERR-001, BR-005) on update: the current record's own dni is
    // ignored, but another client's dni collides.
    $other = Client::factory()->create(['dni' => '77776666']);
    $client = Client::factory()->create(['dni' => '55554444']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(EditClient::class, ['record' => $client->getRouteKey()])
        ->fillForm(['dni' => '77776666'])
        ->call('save')
        ->assertHasFormErrors(['dni' => 'unique']);

    expect($client->fresh()->dni)->toBe('55554444');
    expect($other->fresh()->dni)->toBe('77776666');
});

it('rejects creating a client without the required fields', function () {
    // ERR-002 (FR-001): full name and DNI are required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->call('create')
        ->assertHasFormErrors(['full_name' => 'required', 'dni' => 'required']);

    expect(Client::count())->toBe(0);
});

it('lets ADMIN search clients by name, dni and email', function () {
    // AC-3 (FR-002): search is by full_name, dni and email only.
    $admin = userWithRoles([Role::ADMIN]);

    $byName = Client::factory()->create(['full_name' => 'Searchable Client']);
    $byDni = Client::factory()->create(['dni' => '99998888']);
    $byEmail = Client::factory()->create(['email' => 'searchme@gym.test']);
    $other = Client::factory()->create(['full_name' => 'Another Person']);

    Livewire::actingAs($admin)
        ->test(ListClients::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$byName])
        ->assertCanNotSeeTableRecords([$byDni, $byEmail, $other]);

    Livewire::actingAs($admin)
        ->test(ListClients::class)
        ->searchTable('99998888')
        ->assertCanSeeTableRecords([$byDni])
        ->assertCanNotSeeTableRecords([$byName]);

    Livewire::actingAs($admin)
        ->test(ListClients::class)
        ->searchTable('searchme@gym.test')
        ->assertCanSeeTableRecords([$byEmail])
        ->assertCanNotSeeTableRecords([$byName, $byDni]);
});

it('lets ADMIN view the full client detail including health notes', function () {
    // AC-4 (FR-003, FR-007): health notes are visible in the detail view.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create([
        'full_name' => 'Detail Client',
        'dni' => '44445555',
        'emergency_contact' => 'Emergency Contact Person',
        'injuries_notes' => 'Sensitive knee injury',
        'medical_conditions_notes' => 'Sensitive condition',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $client->getRouteKey()])
        ->assertSee('Detail Client')
        ->assertSee('44445555')
        ->assertSee('Emergency Contact Person')
        ->assertSee('Sensitive knee injury')
        ->assertSee('Sensitive condition');
});

it('lets ADMIN edit a client and persist the changes', function () {
    // AC-5 (FR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create([
        'full_name' => 'Original Name',
        'email' => 'original@gym.test',
        'injuries_notes' => 'Original injury',
    ]);

    Livewire::actingAs($admin)
        ->test(EditClient::class, ['record' => $client->getRouteKey()])
        ->fillForm([
            'full_name' => 'Updated Name',
            'dni' => $client->dni,
            'email' => 'updated@gym.test',
            'phone' => '1234567890',
            'emergency_contact' => 'New Emergency',
            'injuries_notes' => 'Updated injury notes',
            'medical_conditions_notes' => 'Updated conditions',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $client->refresh();

    expect($client->full_name)->toBe('Updated Name');
    expect($client->email)->toBe('updated@gym.test');
    expect($client->phone)->toBe('1234567890');
    expect($client->emergency_contact)->toBe('New Emergency');
    expect($client->injuries_notes)->toBe('Updated injury notes');
    expect($client->medical_conditions_notes)->toBe('Updated conditions');
});

it('rejects a malformed email on client create', function () {
    // ERR-006 (FR-001): malformed email is rejected.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Bad Email Client',
            'dni' => '33334444',
            'email' => 'not-an-email',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email']);
});

it('rejects an over-long phone on client create', function () {
    // ERR-006 (FR-001): phone is validated by length; no phone format regex
    // is imposed (the architecture defines no phone format rule).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateClient::class)
        ->fillForm([
            'full_name' => 'Long Phone Client',
            'dni' => '22223333',
            'phone' => str_repeat('1', 256),
        ])
        ->call('create')
        ->assertHasFormErrors(['phone' => 'max']);
});

it('does not expose a delete operation for client records', function () {
    // AC-11 (BR-006): no delete policy is registered, so deletion is denied
    // for everyone, and no delete route/action is reachable.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();

    expect($admin->can('delete', $client))->toBeFalse();

    $this->actingAs($admin)->get('/admin/clients')->assertOk();

    expect(Client::find($client->id))->not->toBeNull();
});

it('never exposes health fields in the client list', function () {
    // AC-12 partial (FR-007): health notes never appear in list output.
    $admin = userWithRoles([Role::ADMIN]);
    Client::factory()->create([
        'full_name' => 'Confidential Client',
        'injuries_notes' => 'Confidential injury note',
        'medical_conditions_notes' => 'Confidential condition note',
    ]);

    $this->actingAs($admin)
        ->get('/admin/clients')
        ->assertOk()
        ->assertSee('Confidential Client')
        ->assertDontSee('Confidential injury note')
        ->assertDontSee('Confidential condition note');
});

it('does not allow searching clients by health fields', function () {
    // FR-007: health fields are never searchable; a search for a health value
    // returns no records.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create([
        'full_name' => 'Health Search Client',
        'injuries_notes' => 'UniqueSecretInjury',
    ]);

    Livewire::actingAs($admin)
        ->test(ListClients::class)
        ->searchTable('UniqueSecretInjury')
        ->assertCanNotSeeTableRecords([$client]);
});
