<?php

use App\Models\Payment;
use App\Models\Role;

/*
 * PaymentPolicy authorization tests (SPEC-005 BR-011, BR-006, BR-009; PY-01,
 * PY-05; ERR-008; AC-12, AC-13). All assertions are server-side (AGENTS.md
 * §17).
 */

it('allows ADMIN and TRAINER to view and create payments', function () {
    // AC-12 (BR-011, PY-01, D-15 op1).
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $payment = Payment::factory()->create();

    expect($admin->can('viewAny', Payment::class))->toBeTrue();
    expect($admin->can('view', $payment))->toBeTrue();
    expect($admin->can('create', Payment::class))->toBeTrue();

    expect($trainer->can('viewAny', Payment::class))->toBeTrue();
    expect($trainer->can('view', $payment))->toBeTrue();
    expect($trainer->can('create', Payment::class))->toBeTrue();

    expect($client->can('viewAny', Payment::class))->toBeFalse();
    expect($client->can('view', $payment))->toBeFalse();
    expect($client->can('create', Payment::class))->toBeFalse();
});

it('never allows updating or deleting payments', function () {
    // BR-006, BR-009, PY-05: a confirmed payment is immutable and never
    // hard-deleted.
    $admin = userWithRoles([Role::ADMIN]);
    $payment = Payment::factory()->create();

    expect($admin->can('update', $payment))->toBeFalse();
    expect($admin->can('delete', $payment))->toBeFalse();
});

it('denies CLIENT access to the payment pages with 403', function () {
    // AC-13, ERR-008, BR-011.
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);
    $payment = Payment::factory()->create();

    $this->actingAs($admin)->get('/admin/payments')->assertOk();
    $this->actingAs($admin)->get("/admin/payments/{$payment->getRouteKey()}")->assertOk();

    $this->actingAs($trainer)->get('/admin/payments')->assertOk();
    $this->actingAs($trainer)->get("/admin/payments/{$payment->getRouteKey()}")->assertOk();

    $this->actingAs($client)->get('/admin/payments')->assertForbidden();
    $this->actingAs($client)->get("/admin/payments/{$payment->getRouteKey()}")->assertForbidden();
});
