<?php

use App\Models\Turno;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Turno model unit tests (SPEC-006 BR-002, BR-003, BR-004, BR-005, BR-007,
 * BR-009; FR-001, FR-005, FR-006, FR-007, FR-008; ERR-006; AC-1, AC-10,
 * AC-13).
 */

it('exposes exactly the three status constants', function () {
    // BR-002, AS-07: active / inactive / cancelled; no other state exists in
    // the MVP.
    expect(Turno::STATUS_ACTIVE)->toBe('active');
    expect(Turno::STATUS_INACTIVE)->toBe('inactive');
    expect(Turno::STATUS_CANCELLED)->toBe('cancelled');
});

it('creates a turno as active by default', function () {
    // FR-001, BR-002: a new turno is never created in any other state.
    $turno = Turno::factory()->create();

    expect($turno->status)->toBe(Turno::STATUS_ACTIVE);
    expect($turno->isActive())->toBeTrue();
});

it('casts date to Carbon, capacity to integer and keeps times as plain strings', function () {
    // BR-005, BR-007: date is a Carbon date, capacity_limit is an integer and
    // start_time / end_time stay plain time strings.
    $turno = Turno::factory()->create([
        'date' => '2026-08-15',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'capacity_limit' => 10,
    ]);

    expect($turno->date)->toBeInstanceOf(Carbon\Carbon::class);
    expect($turno->date->toDateString())->toBe('2026-08-15');
    expect($turno->capacity_limit)->toBeInt();
    expect($turno->capacity_limit)->toBe(10);
    expect($turno->start_time)->toBeString();
    expect($turno->start_time)->toBe('08:00');
    expect($turno->end_time)->toBe('10:00');
});

it('deactivates an active turno into inactive', function () {
    // FR-005, BR-003, AC-8: active -> inactive.
    $turno = Turno::factory()->create();

    $turno->deactivate();

    expect($turno->status)->toBe(Turno::STATUS_INACTIVE);
    expect($turno->isActive())->toBeFalse();
    expect($turno->isInactive())->toBeTrue();
});

it('reactivates an inactive turno into active', function () {
    // FR-006, AF-001, BR-003, AC-9: inactive -> active.
    $turno = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);

    $turno->reactivate();

    expect($turno->status)->toBe(Turno::STATUS_ACTIVE);
    expect($turno->isActive())->toBeTrue();
    expect($turno->isInactive())->toBeFalse();
});

it('cancels an active or inactive turno and marks it cancelled', function () {
    // FR-007, AF-002, BR-003, AC-10: active/inactive -> cancelled.
    $active = Turno::factory()->create();
    $inactive = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);

    $active->cancel();
    $inactive->cancel();

    expect($active->status)->toBe(Turno::STATUS_CANCELLED);
    expect($inactive->status)->toBe(Turno::STATUS_CANCELLED);
    expect($active->isCancelled())->toBeTrue();
    expect($inactive->isCancelled())->toBeTrue();
});

it('rejects deactivating a turno that is not active', function () {
    // BR-003: only an active turno can be deactivated.
    $inactive = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    expect(fn () => $inactive->deactivate())->toThrow(DomainException::class);
    expect(fn () => $cancelled->deactivate())->toThrow(DomainException::class);

    expect($inactive->fresh()->status)->toBe(Turno::STATUS_INACTIVE);
    expect($cancelled->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});

it('rejects reactivating a turno that is not inactive, including cancelled', function () {
    // BR-003, ERR-006: only an inactive turno can be reactivated; a cancelled
    // turno is terminal (BR-004).
    $active = Turno::factory()->create();
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    expect(fn () => $active->reactivate())->toThrow(DomainException::class);
    expect(fn () => $cancelled->reactivate())->toThrow(DomainException::class);

    expect($active->fresh()->status)->toBe(Turno::STATUS_ACTIVE);
    expect($cancelled->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});

it('rejects cancelling a cancelled turno', function () {
    // BR-004, ERR-006, AC-10: cancelled is terminal; it cannot be cancelled
    // again.
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    expect(fn () => $cancelled->cancel())->toThrow(DomainException::class);

    expect($cancelled->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});

it('reports the isActive, isInactive and isCancelled helpers', function () {
    // FR-008 display helpers.
    $active = Turno::factory()->create();
    $inactive = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    expect($active->isActive())->toBeTrue();
    expect($active->isInactive())->toBeFalse();
    expect($active->isCancelled())->toBeFalse();

    expect($inactive->isInactive())->toBeTrue();
    expect($inactive->isActive())->toBeFalse();
    expect($inactive->isCancelled())->toBeFalse();

    expect($cancelled->isCancelled())->toBeTrue();
    expect($cancelled->isActive())->toBeFalse();
    expect($cancelled->isInactive())->toBeFalse();
});

it('scopes active turnos only', function () {
    // FR-008: scopeActive returns only the currently bookable turnos.
    $active = Turno::factory()->create();
    Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);
    Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    expect(Turno::active()->pluck('id'))->toHaveCount(1);
    expect(Turno::active()->pluck('id'))->toContain($active->id);
});

it('defaults the status column to active and creates the expected columns and date index', function () {
    // FR-001, BR-002: the DB default on status is 'active' even for a raw
    // write path; the date column is indexed and the expected columns exist
    // (FR-002, BR-009).
    DB::table('turnos')->insert([
        'date' => '2026-08-15',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'capacity_limit' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('turnos')->first()->status)->toBe('active');

    expect(Schema::hasColumns('turnos', [
        'id',
        'date',
        'start_time',
        'end_time',
        'capacity_limit',
        'status',
        'label',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasIndex('turnos', 'turnos_date_index'))->toBeTrue();
});
