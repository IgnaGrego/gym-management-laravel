<?php

use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Plan;
use Illuminate\Database\QueryException;

/*
 * Cuota model unit tests (SPEC-005 BR-001, BR-002, BR-003, BR-012, BR-014,
 * BR-015; NC-02, NC-03; AC-1, AC-3, AC-4, AC-17, AC-18).
 */

it('exposes exactly the three status constants', function () {
    // BR-003: pending / paid / cancelled; no other state exists in the MVP
    // (no overdue/late state).
    expect(Cuota::STATUS_PENDING)->toBe('pending');
    expect(Cuota::STATUS_PAID)->toBe('paid');
    expect(Cuota::STATUS_CANCELLED)->toBe('cancelled');
});

it('auto-generates one pending cuota with the plan price when a membership is created', function () {
    // FR-001, BR-001, BR-002, BR-003, AC-1, AC-18, NC-03: exactly one cuota,
    // amount = plan price (NOT price + enrollment_fee) and status pending.
    $plan = Plan::factory()->create(['price' => '12000.00', 'enrollment_fee' => '3000.00']);
    $membership = Membership::factory()->create(['plan_id' => $plan->id]);

    expect(Cuota::count())->toBe(1);

    $cuota = $membership->cuota;

    expect($cuota)->not->toBeNull();
    expect($cuota->amount)->toBe('12000.00');
    expect($cuota->status)->toBe(Cuota::STATUS_PENDING);
    expect($cuota->membership_id)->toBe($membership->id);
});

it('casts the amount as a two-decimal string', function () {
    // ADR-003: decimal(10,2) columns cast to two-decimal strings.
    $cuota = Cuota::factory()->create(['amount' => '99.90']);

    expect($cuota->amount)->toBe('99.90');
});

it('navigates the membership relationship', function () {
    // BR-001: a cuota belongs to exactly one membership.
    $membership = Membership::factory()->create();

    expect($membership->cuota->membership->is($membership))->toBeTrue();
});

it('marks a pending cuota as paid and stamps paid_at', function () {
    // FR-006, BR-003: pending -> paid, paid_at set.
    $cuota = Membership::factory()->create()->cuota;

    $cuota->markPaid();

    expect($cuota->status)->toBe(Cuota::STATUS_PAID);
    expect($cuota->isPaid())->toBeTrue();
    expect($cuota->paid_at)->not->toBeNull();
});

it('rejects marking a non-pending cuota as paid', function () {
    // BR-014, ERR-011: only a pending cuota can become paid.
    $paid = Membership::factory()->create()->cuota;
    $paid->markPaid();
    $cancelled = Membership::factory()->create()->cuota;
    $cancelled->cancel();

    expect(fn () => $paid->markPaid())->toThrow(DomainException::class);
    expect(fn () => $cancelled->markPaid())->toThrow(DomainException::class);
});

it('cancels a pending cuota', function () {
    // BR-003, BR-015: pending -> cancelled (uncollectible).
    $cuota = Membership::factory()->create()->cuota;

    $cuota->cancel();

    expect($cuota->status)->toBe(Cuota::STATUS_CANCELLED);
    expect($cuota->isCancelled())->toBeTrue();
});

it('rejects cancelling a non-pending cuota', function () {
    // BR-003, BR-015: only pending cuotas are cancellable.
    $paid = Membership::factory()->create()->cuota;
    $paid->markPaid();

    expect(fn () => $paid->cancel())->toThrow(DomainException::class);
});

it('edits the amount of a pending cuota only', function () {
    // FR-002, BR-012: amount edit is pending-only (ERR-007).
    $cuota = Membership::factory()->create()->cuota;

    $cuota->updateAmount('99.99');
    expect($cuota->amount)->toBe('99.99');

    $cuota->markPaid();

    expect(fn () => $cuota->updateAmount('1.00'))->toThrow(DomainException::class);
});

it('enforces one cuota per membership via the unique database constraint', function () {
    // NC-02, AC-17: the membership_id UNIQUE constraint is the DB backstop.
    $membership = Membership::factory()->create();

    expect(fn () => Cuota::create([
        'membership_id' => $membership->id,
        'amount' => '10.00',
        'status' => Cuota::STATUS_PENDING,
    ]))->toThrow(QueryException::class);
});
