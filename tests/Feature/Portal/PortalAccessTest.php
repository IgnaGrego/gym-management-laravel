<?php

use App\Models\Role;

/*
 * Portal access tests (SPEC-013 BR-001, BR-010; AC-1, AC-18; ERR-001, ERR-002;
 * SPEC-015). Navigation is asserted here per the SPEC-015 test adjustment
 * (SPEC-013 FR-001 adds portal navigation to /portal).
 */

beforeEach(function () {
    $this->withoutVite();
});

it('shows the authenticated CLIENT their portal with navigation', function () {
    $client = clientWithUser();

    $this->actingAs($client->user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Portal del cliente')
        ->assertSee('El Area Gym')
        ->assertSee('Membresías')
        ->assertSee('Pagos')
        ->assertSee('Asistencia')
        ->assertSee('Reservas')
        ->assertSee('Rutina')
        ->assertSee('Entrenamientos')
        ->assertSee('Perfil');
});

it('redirects anonymous visitors to the login page', function () {
    $this->get('/portal')->assertRedirect('/login');
    $this->get('/portal/memberships')->assertRedirect('/login');
    $this->get('/portal/workouts')->assertRedirect('/login');
});

it('denies non-CLIENT staff with 403', function () {
    $trainer = userWithRoles([Role::TRAINER]);
    $admin = userWithRoles([Role::ADMIN]);

    $this->actingAs($trainer)->get('/portal')->assertForbidden();
    $this->actingAs($admin)->get('/portal')->assertForbidden();
    $this->actingAs($trainer)->get('/portal/memberships')->assertForbidden();
});

it('shows the ERR-005 notice and no business content on every section when unlinked', function () {
    $user = userWithRoles([Role::CLIENT]);

    foreach ([
        '/portal',
        '/portal/memberships',
        '/portal/payments',
        '/portal/attendance',
        '/portal/bookings',
        '/portal/turnos',
        '/portal/routine',
        '/portal/workouts',
        '/portal/profile',
    ] as $path) {
        $this->actingAs($user)
            ->get($path)
            ->assertOk()
            ->assertSee('Perfil no disponible. Contactá a recepción.')
            ->assertDontSee('No informado');
    }
});
