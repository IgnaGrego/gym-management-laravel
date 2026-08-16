<?php

use App\Filament\Resources\MembershipResource\Pages\ListMemberships;
use App\Models\Membership;
use App\Models\Role;
use Livewire\Livewire;

/*
 * Activation transition contract tests (SPEC-004 FR-008, BR-006, AC-15,
 * AM-05): Membership::activate() is the pending -> active transition invoked
 * by SPEC-005 when the first cuota is confirmed paid. It is enforced on the
 * model, never exposed as an ADMIN UI action or a policy ability.
 */

it('activates a pending membership within its period', function () {
    // FR-008, BR-006: the transition runs from `pending` while within the
    // validity period.
    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'start_date' => now()->subDays(5)->toDateString(),
        'duration_days' => 30,
    ]);

    $membership->activate();

    expect($membership->fresh()->status)->toBe(Membership::STATUS_ACTIVE);
});

it('rejects activation from any non-pending state', function () {
    // AC-15: the transition can run only from `pending`.
    foreach ([Membership::STATUS_ACTIVE, Membership::STATUS_EXPIRED, Membership::STATUS_CANCELLED] as $status) {
        $membership = Membership::factory()->create([
            'status' => $status,
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        expect(fn () => $membership->activate())
            ->toThrow(DomainException::class, 'Solo una membresía pendiente puede activarse.');

        expect($membership->fresh()->status)->toBe($status);
    }
});

it('rejects activation after the end date has passed', function () {
    // AC-15: only while within the validity period; a late payment cannot
    // activate an expired membership (AM-10, OQ-03 default).
    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'end_date' => now()->subDay()->toDateString(),
    ]);

    expect(fn () => $membership->activate())->toThrow(DomainException::class);

    expect($membership->fresh()->status)->toBe(Membership::STATUS_PENDING);
});

it('allows activation on the end date itself', function () {
    // BR-003/AM-07 day-granularity: a membership whose end date is today is
    // still within the validity period until the day ends.
    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'end_date' => now()->toDateString(),
    ]);

    $membership->activate();

    expect($membership->fresh()->status)->toBe(Membership::STATUS_ACTIVE);
});

it('does not expose an activate action or ability in the admin UI', function () {
    // AC-15: the transition is not callable as an ADMIN UI action (FR-008,
    // BR-006) and the policy registers no activate ability.
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);

    expect($admin->can('activate', $membership))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->assertTableActionDoesNotExist('activate');
});
