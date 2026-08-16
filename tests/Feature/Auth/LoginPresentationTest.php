<?php

/*
 * Login page presentation tests (SPEC-015 FR-005, FR-008; AC-3, AC-6, AC-11;
 * ERR-003). The SPEC-001 login contract (action, method, CSRF, field names,
 * labels, redirects, generic error) is preserved.
 */

beforeEach(function () {
    $this->withoutVite();
});

it('renders the login form preserving the SPEC-001 contract', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Iniciar sesión')
        ->assertSee('Vital Gym')
        ->assertSee('method="POST"', false)
        ->assertSee(route('login'), false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee('for="email"', false)
        ->assertSee('for="password"', false)
        ->assertSee('<header', false)
        ->assertSee('<main', false)
        ->assertSee('<footer', false);
});

it('renders the generic validation error perceivably after an invalid login', function () {
    $this->from('/login')
        ->followingRedirects()
        ->post('/login', [
            'email' => 'nobody@gym.test',
            'password' => 'wrong-password',
        ])
        ->assertOk()
        ->assertSee('Estas credenciales no coinciden con nuestros registros.');

    $this->assertGuest();
});
