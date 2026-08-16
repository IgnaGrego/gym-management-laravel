<?php

use App\Actions\ProvisionClientUser;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Client account provisioning feature tests (SPEC-002 FR-005, FR-006;
 * BR-002, BR-003, BR-008, BR-009; ERR-003, ERR-004; AC-7, AC-8, AC-9,
 * AC-13, AC-14; AF-004).
 */

it('provisions a linked CLIENT user account for a client', function () {
    // AC-7 (FR-005, BR-003): the User receives the CLIENT role, is active,
    // and is linked 1:1 to the client.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Provisioned Client']);

    $user = app(ProvisionClientUser::class)->handle($client, 'client@gym.test', 'password');

    expect($client->fresh()->user_id)->toBe($user->id);
    expect($user->name)->toBe('Provisioned Client'); // snapshot of full_name
    expect($user->email)->toBe('client@gym.test');
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole(Role::CLIENT))->toBeTrue();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

it('lets the provisioned account log in and reach the client portal', function () {
    // AC-7 (FR-005): after provisioning, the CLIENT user can authenticate and
    // is redirected to the client portal context (SPEC-001 role redirect).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();

    app(ProvisionClientUser::class)->handle($client, 'portal.ready@gym.test', 'password');

    // End the admin session so the /login route (guest middleware) is not
    // skipped for the provisioned client.
    auth()->logout();

    $this->post('/login', [
        'email' => 'portal.ready@gym.test',
        'password' => 'password',
    ])->assertRedirect('/portal');

    $this->assertAuthenticated();
});

it('does not create an account when provisioning is rejected for a duplicate login email', function () {
    // AC-8 (ERR-003, BR-009): the login email must be unique among users; the
    // client's contact email is irrelevant (OQ-07 default: independent).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['email' => 'client.contact@gym.test']);
    User::factory()->create(['email' => 'taken@gym.test']);

    expect(fn () => app(ProvisionClientUser::class)->handle($client, 'taken@gym.test', 'password'))
        ->toThrow(ValidationException::class);

    expect($client->fresh()->user_id)->toBeNull();
    expect(User::where('email', 'taken@gym.test')->count())->toBe(1);
});

it('rejects a second provisioning for the same client', function () {
    // AC-9 (ERR-004, BR-003): a client may have at most one linked account.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();

    app(ProvisionClientUser::class)->handle($client, 'first@gym.test', 'password');

    expect(fn () => app(ProvisionClientUser::class)->handle($client, 'second@gym.test', 'password'))
        ->toThrow(ValidationException::class);

    expect(User::where('email', 'second@gym.test')->exists())->toBeFalse();
    expect($client->fresh()->user_id)->not->toBeNull();
});

it('rejects a password that does not satisfy the framework default policy', function () {
    // SPEC-001 A-05: framework default password policy (min length 8).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();

    expect(fn () => app(ProvisionClientUser::class)->handle($client, 'short-pass@gym.test', 'short'))
        ->toThrow(ValidationException::class);

    expect($client->fresh()->user_id)->toBeNull();
});

it('provisions an account from the client detail page header action', function () {
    // FR-005 UI glue: the detail-page modal action invokes the Action.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $client->getRouteKey()])
        ->callAction('provision', [
            'login_email' => 'via-ui@gym.test',
            'password' => 'password',
        ])
        ->assertHasNoActionErrors();

    $client->refresh();

    expect($client->user_id)->not->toBeNull();
    expect($client->user->email)->toBe('via-ui@gym.test');
    expect($client->user->hasRole(Role::CLIENT))->toBeTrue();
});

it('rejects a duplicate login email through the provisioning modal', function () {
    // AC-8 via the UI: the modal action surfaces the validation error thrown
    // by the Action (the modal itself validates format/password; uniqueness
    // is enforced by the Action and propagated as a validation error).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    User::factory()->create(['email' => 'taken-in-ui@gym.test']);

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $client->getRouteKey()])
        ->callAction('provision', [
            'login_email' => 'taken-in-ui@gym.test',
            'password' => 'password',
        ])
        ->assertHasErrors(['login_email' => 'unique']);

    expect($client->fresh()->user_id)->toBeNull();
});

it('hides the provision action when the client already has a linked account', function () {
    // ERR-004 UX: the action is not shown for an already-linked client; the
    // rule is still enforced server-side (see the direct Action test above).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    app(ProvisionClientUser::class)->handle($client, 'already@gym.test', 'password');

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $client->getRouteKey()])
        ->assertActionHidden('provision');
});

it('shows the linked account and its active status in the detail view', function () {
    // AC-14 (FR-006): the detail view shows whether a linked account exists
    // and its active state.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $unlinked = Client::factory()->create();
    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $unlinked->getRouteKey()])
        ->assertSee('Sin cuenta');

    $linked = Client::factory()->create();
    app(ProvisionClientUser::class)->handle($linked, 'linked@gym.test', 'password');

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $linked->getRouteKey()])
        ->assertSee('linked@gym.test')
        ->assertSee('Activo');

    $linked->user->update(['is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ViewClient::class, ['record' => $linked->getRouteKey()])
        ->assertSee('linked@gym.test')
        ->assertSee('Inactivo');
});

it('leaves the client record intact when the linked user is deactivated', function () {
    // AC-13 (BR-008, AF-004): deactivating the linked account via the SPEC-001
    // user-management flow does not modify the Client record, and the
    // deactivated user cannot log in.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $user = app(ProvisionClientUser::class)->handle($client, 'deactivate@gym.test', 'password');

    $user->update(['is_active' => false]);

    $client->refresh();

    expect($client->user_id)->toBe($user->id);
    expect($client->full_name)->not->toBeNull();
    expect($client->dni)->not->toBeNull();

    // End the admin session so the deactivated client reaches the /login
    // route instead of being redirected away as an authenticated guest.
    auth()->logout();

    $this->post('/login', [
        'email' => 'deactivate@gym.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not modify the client record when the linked user is edited', function () {
    // AC-13 (BR-008): editing the linked User (e.g. name change) never
    // touches the Client record.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create(['full_name' => 'Client Full Name']);
    $user = app(ProvisionClientUser::class)->handle($client, 'edit.link@gym.test', 'password');

    $user->update(['name' => 'A Different Name']);

    $client->refresh();

    expect($client->full_name)->toBe('Client Full Name');
    expect($client->user_id)->toBe($user->id);
});
