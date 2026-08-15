<?php

use App\Models\Role;

/*
 * Logout feature tests (SPEC-001 FR-002; AC-3).
 */

it('terminates the session and protects pages after logout', function () {
    $user = userWithRoles([Role::CLIENT]);

    $this->actingAs($user);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    $this->get('/portal')->assertRedirect('/login');
    $this->get('/admin')->assertRedirect('/login');
});

it('does not allow guests to log out', function () {
    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
