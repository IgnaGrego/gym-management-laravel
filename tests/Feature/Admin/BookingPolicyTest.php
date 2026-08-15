<?php

use App\Models\Booking;
use App\Models\Role;

/*
 * BookingPolicy authorization tests (SPEC-007 BR-012, BR-011; ERR-010; AC-12,
 * AC-13). All assertions are server-side (AGENTS.md §17).
 */

it('allows ADMIN and TRAINER to view, create and update (cancel) bookings', function () {
    // AC-12 (BR-012, BK-02): ADMIN and TRAINER receive the full booking set.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $booking = Booking::factory()->create();

    expect($admin->can('viewAny', Booking::class))->toBeTrue();
    expect($admin->can('view', $booking))->toBeTrue();
    expect($admin->can('create', Booking::class))->toBeTrue();
    expect($admin->can('update', $booking))->toBeTrue();

    expect($trainer->can('viewAny', Booking::class))->toBeTrue();
    expect($trainer->can('view', $booking))->toBeTrue();
    expect($trainer->can('create', Booking::class))->toBeTrue();
    expect($trainer->can('update', $booking))->toBeTrue();
});

it('denies CLIENT every booking ability', function () {
    // AC-12 (ERR-010, BR-012): CLIENT has no booking management here;
    // self-booking belongs to SPEC-013.
    $client = userWithRoles([Role::CLIENT]);
    $booking = Booking::factory()->create();

    expect($client->can('viewAny', Booking::class))->toBeFalse();
    expect($client->can('view', $booking))->toBeFalse();
    expect($client->can('create', Booking::class))->toBeFalse();
    expect($client->can('update', $booking))->toBeFalse();
});

it('grants multi-role ADMIN + CLIENT and TRAINER + CLIENT users booking management', function () {
    // SPEC-001 BR-002: a multi-role user receives the union of permissions; a
    // staff user who is also CLIENT can manage bookings in the admin panel.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $trainerClient = userWithRoles([Role::TRAINER, Role::CLIENT]);
    $booking = Booking::factory()->create();

    expect($adminClient->can('viewAny', Booking::class))->toBeTrue();
    expect($adminClient->can('view', $booking))->toBeTrue();
    expect($adminClient->can('create', Booking::class))->toBeTrue();
    expect($adminClient->can('update', $booking))->toBeTrue();

    expect($trainerClient->can('viewAny', Booking::class))->toBeTrue();
    expect($trainerClient->can('view', $booking))->toBeTrue();
    expect($trainerClient->can('create', Booking::class))->toBeTrue();
    expect($trainerClient->can('update', $booking))->toBeTrue();
});

it('never allows hard deletion of booking records', function () {
    // AC-13 (BR-011): no delete policy is registered; deletion is denied for
    // everyone, including ADMIN and TRAINER.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $booking = Booking::factory()->create();

    expect($admin->can('delete', $booking))->toBeFalse();
    expect($trainer->can('delete', $booking))->toBeFalse();
    expect($client->can('delete', $booking))->toBeFalse();
});

it('redirects guests to the login page for the booking pages', function () {
    // ERR-010 (BR-012): anonymous visitors never reach booking data.
    $this->get('/admin/bookings')->assertRedirect('/login');
});

it('allows ADMIN and TRAINER and denies CLIENT on the booking pages', function () {
    // AC-12 (ERR-010, BR-012): 200 for staff, 403 for CLIENT.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $booking = Booking::factory()->create();

    $this->actingAs($admin)->get('/admin/bookings')->assertOk();
    $this->actingAs($admin)->get("/admin/bookings/{$booking->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/bookings')->assertOk();
    $this->actingAs($trainer)->get("/admin/bookings/{$booking->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/bookings')->assertForbidden();
    $this->actingAs($client)->get("/admin/bookings/{$booking->getRouteKey()}")->assertForbidden();
});
