<?php

use App\Models\Cuota;
use App\Models\Payment;
use App\Models\User;

/*
 * Payment model unit tests (SPEC-005 BR-005, BR-006, BR-007; PY-05, PY-06;
 * AC-10, AC-11).
 */

it('exposes the D-16 status constants and the two method constants', function () {
    // BR-006: pending/confirmed/failed; BR-004: cash/transfer.
    expect(Payment::STATUS_PENDING)->toBe('pending');
    expect(Payment::STATUS_CONFIRMED)->toBe('confirmed');
    expect(Payment::STATUS_FAILED)->toBe('failed');
    expect(Payment::METHOD_CASH)->toBe('cash');
    expect(Payment::METHOD_TRANSFER)->toBe('transfer');
});

it('creates a payment as confirmed by default', function () {
    // BR-005, AC-10: a manually registered payment is persisted confirmed.
    $payment = Payment::factory()->create();

    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
    expect($payment->isConfirmed())->toBeTrue();
});

it('casts the amount as a two-decimal string and payment_date as a date', function () {
    // ADR-003, BR-007.
    $payment = Payment::factory()->create([
        'amount' => '99.90',
        'payment_date' => '2026-08-15',
    ]);

    expect($payment->amount)->toBe('99.90');
    expect($payment->payment_date->toDateString())->toBe('2026-08-15');
});

it('navigates the cuota and recordedBy relationships', function () {
    // BR-007, C-06: a payment references a cuota (membership via the cuota)
    // and the staff User who recorded it (PY-06).
    $user = User::factory()->create();
    $cuota = Cuota::factory()->create();

    $payment = Payment::factory()->create([
        'cuota_id' => $cuota->id,
        'recorded_by' => $user->id,
    ]);

    expect($payment->cuota->is($cuota))->toBeTrue();
    expect($payment->recordedBy->is($user))->toBeTrue();
    expect($payment->cuota->membership->is($cuota->membership))->toBeTrue();
});

it('keeps status and recorded_by out of the fillable attributes and exposes no transition method', function () {
    // BR-006, PY-05, PY-06: a confirmed payment is immutable by convention —
    // status is not mass-assignable, recorded_by is written by the Action, and
    // no status-transition method exists.
    $payment = Payment::factory()->create();

    expect(in_array('status', $payment->getFillable(), true))->toBeFalse();
    expect(in_array('recorded_by', $payment->getFillable(), true))->toBeFalse();

    expect(method_exists($payment, 'markPaid'))->toBeFalse();
    expect(method_exists($payment, 'markPending'))->toBeFalse();
    expect(method_exists($payment, 'markFailed'))->toBeFalse();
    expect(method_exists($payment, 'cancel'))->toBeFalse();
});
