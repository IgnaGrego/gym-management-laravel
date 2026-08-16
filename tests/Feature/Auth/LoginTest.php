<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/*
 * Login feature tests (SPEC-001 FR-001, FR-006; AC-1, AC-2, AC-8, AC-10;
 * ERR-001, ERR-002; AF-001, AF-002, AF-003; A-05).
 */

it('shows the login form to guests', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertSee('Iniciar sesión');
});

it('logs in an ADMIN user and redirects to the admin panel', function () {
    $user = userWithRoles([Role::ADMIN]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
});

it('logs in a TRAINER and redirects to the admin panel', function () {
    $user = userWithRoles([Role::TRAINER]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('logs in a CLIENT-only user and redirects to the client portal', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/portal');

    $this->assertAuthenticatedAs($user);
});

it('redirects a multi-role user (staff + CLIENT) to the admin panel', function () {
    // OQ-04 default: a user holding a staff role lands on the admin panel.
    $user = userWithRoles([Role::TRAINER, Role::CLIENT]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('logs in a user with no roles and redirects to the landing page with a notice', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    $response->assertSessionHas('status');
});

it('rejects an unknown email with a generic error message', function () {
    $response = $this->post('/login', [
        'email' => 'nobody@gym.test',
        'password' => 'secret',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects a wrong password with a generic error message', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('uses the identical message whether the email exists or not', function () {
    // A-05: no account enumeration.
    $this->post('/login', ['email' => 'nobody@gym.test', 'password' => 'secret']);
    $unknownMessage = session('errors')->get('email')[0];

    $user = userWithRoles([Role::CLIENT]);
    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $wrongPasswordMessage = session('errors')->get('email')[0];

    expect($unknownMessage)->toBe($wrongPasswordMessage);
    expect($unknownMessage)->toBe('Estas credenciales no coinciden con nuestros registros.');
});

it('rejects a deactivated user with the same generic message', function () {
    $user = userWithRoles([Role::CLIENT], ['is_active' => false]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires email and password', function () {
    $this->post('/login', [])
        ->assertSessionHasErrors(['email', 'password']);
});

it('redirects authenticated users away from the login page', function () {
    $user = userWithRoles([Role::ADMIN]);

    $this->actingAs($user)->get('/login')->assertRedirect('/');
});

it('stores passwords hashed and never in plaintext', function () {
    $user = User::factory()->create(['password' => 'plaintext-secret']);

    $fresh = User::findOrFail($user->id);

    expect($fresh->password)->not->toBe('plaintext-secret');
    expect(Str::contains($fresh->password, 'plaintext-secret'))->toBeFalse();
    expect(Hash::check('plaintext-secret', $fresh->password))->toBeTrue();
});
