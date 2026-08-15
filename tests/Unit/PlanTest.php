<?php

use App\Models\Plan;
use Illuminate\Database\QueryException;

/*
 * Plan model unit tests (SPEC-003 FR-001, FR-005; BR-002, BR-003, BR-005;
 * AP-02; ADR-003; AC-1, AC-8, AC-10).
 */

it('defaults a factory plan to active with no optional fields', function () {
    // FR-001, AP-02: new plans are created active by default; the description
    // and the one-time enrollment fee are optional and absent by default.
    $plan = Plan::factory()->create();

    expect($plan->is_active)->toBeTrue();
    expect($plan->description)->toBeNull();
    expect($plan->enrollment_fee)->toBeNull();
});

it('casts monetary amounts as decimal:2 and status as boolean', function () {
    // ADR-003: decimal(10,2) columns cast to two-decimal strings; the
    // lifecycle status is a boolean (BR-005).
    $plan = Plan::factory()->create([
        'price' => '99.90',
        'enrollment_fee' => '50.00',
        'is_active' => false,
    ]);

    expect($plan->price)->toBe('99.90');
    expect($plan->enrollment_fee)->toBe('50.00');
    expect($plan->is_active)->toBeFalse();

    $active = Plan::factory()->create(['is_active' => true]);

    expect($active->is_active)->toBeTrue();
});

it('rejects a duplicate name via the unique database constraint', function () {
    // BR-003, ERR-002: the unique index on plans.name enforces uniqueness at
    // the database level.
    Plan::factory()->create(['name' => 'Mensual']);

    expect(fn () => Plan::factory()->create(['name' => 'Mensual']))
        ->toThrow(QueryException::class);
});
