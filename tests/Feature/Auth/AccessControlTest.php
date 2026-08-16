<?php

use App\Models\Role;
use App\Models\User;

/*
 * Context access control feature tests (SPEC-001 FR-006, BR-003, BR-004;
 * AC-4, AC-5, AC-6, AC-9; ERR-003, ERR-004).
 */

it('redirects guests to the login page for the admin panel', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('redirects guests to the login page for the client portal', function () {
    $this->get('/portal')->assertRedirect('/login');
});

it('blocks a CLIENT-only user from the admin panel', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('allows a TRAINER to access the admin panel', function () {
    $user = userWithRoles([Role::TRAINER]);

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('allows an ADMIN to access the admin panel', function () {
    $user = userWithRoles([Role::ADMIN]);

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('allows a CLIENT to access the client portal', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->withoutVite();

    $this->actingAs($user)
        ->get('/portal')
        ->assertOk()
        ->assertSee('Portal del cliente');
});

it('blocks a staff-only user from the client portal', function () {
    $user = userWithRoles([Role::ADMIN]);

    $this->actingAs($user)->get('/portal')->assertForbidden();
});

it('blocks a user with no roles from both contexts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/portal')->assertForbidden();
});
