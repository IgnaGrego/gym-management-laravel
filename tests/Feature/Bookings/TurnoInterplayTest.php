<?php

use App\Filament\Resources\TurnoResource\Pages\EditTurno;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Turno;
use Livewire\Livewire;

/*
 * Turno status-change interplay / NC-01 tests (SPEC-007 FR-007, BR-014;
 * ERR-012; AC-16, AC-17; AF-007).
 */

it('auto-cancels every confirmed booking and frees their spots when a turno is cancelled', function () {
    // AC-16 (FR-007, BR-014, AF-007, NC-01): cancelling a turno cancels every
    // confirmed booking; spots are freed; other turnos' bookings are untouched.
    $turno = Turno::factory()->create();
    $otherTurno = Turno::factory()->create();

    $a = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    $b = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    $cancelled = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);
    $other = Booking::factory()->create(['turno_id' => $otherTurno->id, 'status' => Booking::STATUS_CONFIRMED]);

    $turno->cancel();

    expect($turno->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
    expect($a->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($b->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($cancelled->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect($other->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);

    expect(Booking::confirmedCountForTurno($turno->id))->toBe(0);
});

it('auto-cancels every confirmed booking when a turno is deactivated', function () {
    // AC-16 (BR-014, NC-01): deactivation also auto-cancels confirmed bookings.
    $turno = Turno::factory()->create();
    $booking = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);

    $turno->deactivate();

    expect($turno->fresh()->status)->toBe(Turno::STATUS_INACTIVE);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect(Booking::confirmedCountForTurno($turno->id))->toBe(0);
});

it('does not restore auto-cancelled bookings when an inactive turno is reactivated', function () {
    // AF-007 (BR-004): reactivating an inactive turno does NOT restore the
    // auto-cancelled bookings; clients must create new bookings.
    $turno = Turno::factory()->create();
    $booking = Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);

    $turno->deactivate();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);

    $turno->reactivate();

    expect($turno->fresh()->status)->toBe(Turno::STATUS_ACTIVE);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('rejects lowering a turno capacity_limit below its confirmed bookings', function () {
    // AC-17 (ERR-012, BR-014, NC-01): the edit is rejected until the excess
    // bookings are cancelled.
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create(['capacity_limit' => 5]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);

    Livewire::actingAs($admin)
        ->test(EditTurno::class, ['record' => $turno->getRouteKey()])
        ->fillForm(['capacity_limit' => '2'])
        ->call('save')
        ->assertHasFormErrors(['capacity_limit']);

    expect($turno->fresh()->capacity_limit)->toBe(5);

    // Once the excess bookings are cancelled, the edit is accepted.
    Booking::query()->where('turno_id', $turno->id)->confirmed()->update(['status' => Booking::STATUS_CANCELLED]);

    Livewire::actingAs($admin)
        ->test(EditTurno::class, ['record' => $turno->getRouteKey()])
        ->fillForm(['capacity_limit' => '2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($turno->fresh()->capacity_limit)->toBe(2);
});

it('rejects lowering capacity_limit below the confirmed count via the model guard', function () {
    // ERR-012 (BR-014): the business rule lives on the Turno model and throws
    // a DomainException when the new limit is below the confirmed count.
    $turno = Turno::factory()->create(['capacity_limit' => 5]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);

    expect(fn () => $turno->assertCapacityLimitNotBelowConfirmed(0))->toThrow(DomainException::class);
});
