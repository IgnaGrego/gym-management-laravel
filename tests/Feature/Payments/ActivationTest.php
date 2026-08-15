<?php

use App\Actions\RegisterPayment;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

/*
 * Activation and cancellation-cascade tests (SPEC-005 FR-006, BR-008, BR-010,
 * BR-013, BR-015; ERR-009, ERR-011; NC-04; AC-8, AC-9, AC-15, AC-19).
 */

it('activates a pending membership when its first cuota is paid within its period', function () {
    // AC-8 (FR-006, BR-008): paying the first cuota invokes
    // Membership::activate() and the membership becomes active.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'start_date' => now()->subDays(5)->toDateString(),
        'duration_days' => 30,
    ]);
    $cuota = $membership->cuota;

    $payment = app(RegisterPayment::class)->handle(
        $cuota->id,
        $cuota->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    );

    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_PAID);
    expect($membership->fresh()->status)->toBe(Membership::STATUS_ACTIVE);
});

it('records a payment for an expired membership cuota but never reactivates it', function () {
    // AC-9, AM-10, NC-04: `expired` is terminal; the payment stands, the
    // cuota is paid, but the membership is never reactivated.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-30',
    ]);
    $cuota = $membership->cuota;

    $payment = app(RegisterPayment::class)->handle(
        $cuota->id,
        $cuota->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    );

    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_PAID);
    expect($membership->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
});

it('records a payment for a pending membership whose end date has passed without activating it', function () {
    // AC-9, ERR-009: the pre-expiry-command window — activate() throws and the
    // DomainException is swallowed; the payment and paid cuota stand.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'end_date' => now()->subDay()->toDateString(),
    ]);
    $cuota = $membership->cuota;

    $payment = app(RegisterPayment::class)->handle(
        $cuota->id,
        $cuota->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    );

    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_PAID);
    expect($membership->fresh()->status)->toBe(Membership::STATUS_PENDING);
});

it('cancels the pending cuota when a membership is cancelled and blocks further payment', function () {
    // AC-19 (BR-015, NC-04): cancelling a membership cascades pending ->
    // cancelled; the cancelled cuota is not payable.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $membership = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);
    $cuota = $membership->cuota;

    $membership->cancel();

    expect($membership->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_CANCELLED);

    expect(fn () => app(RegisterPayment::class)->handle(
        $cuota->id,
        $cuota->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    ))->toThrow(ValidationException::class);

    expect(Payment::count())->toBe(0);
});

it('does not modify the plan, client, user or membership except the pending-to-active transition', function () {
    // AC-15 (BR-010, BR-013, C-07): the only membership change is the
    // activation; plan/client/user records are untouched.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['price' => '8000.00']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_PENDING,
    ]);
    $cuota = $membership->cuota;

    $planBefore = $plan->fresh()->toArray();
    $clientBefore = $client->fresh()->toArray();

    app(RegisterPayment::class)->handle(
        $cuota->id,
        $cuota->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    );

    expect($plan->fresh()->toArray())->toBe($planBefore);
    expect($client->fresh()->toArray())->toBe($clientBefore);

    $fresh = $membership->fresh();
    expect($fresh->status)->toBe(Membership::STATUS_ACTIVE);
    expect($fresh->client_id)->toBe($client->id);
    expect($fresh->plan_id)->toBe($plan->id);
    expect($fresh->start_date->toDateString())->toBe($membership->start_date->toDateString());
    expect($fresh->end_date->toDateString())->toBe($membership->end_date->toDateString());
    expect($fresh->duration_days)->toBe($membership->duration_days);
});
