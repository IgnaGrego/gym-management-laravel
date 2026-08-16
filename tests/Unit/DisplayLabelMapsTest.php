<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Exercise;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Routine;
use App\Models\Turno;
use App\Models\WorkoutLog;

/*
 * Display-only label-map unit tests (SPEC-016 FR-006, BR-003, ADR-009).
 *
 * Stored identifiers stay byte-for-byte unchanged; only the display label is
 * Spanish.
 */

it('maps each entity status to a Spanish display label keyed by the stored identifier', function () {
    expect(Client::statusLabels())->toBe([
        'pending' => 'Pendiente', 'active' => 'Activo', 'rejected' => 'Rechazado',
    ]);

    expect(Membership::statusLabels())->toBe([
        'pending' => 'Pendiente', 'active' => 'Activo', 'expired' => 'Vencida', 'cancelled' => 'Cancelada',
    ]);

    expect(Cuota::statusLabels())->toBe([
        'pending' => 'Pendiente', 'paid' => 'Pagada', 'cancelled' => 'Cancelada',
    ]);

    expect(Payment::statusLabels())->toBe([
        'pending' => 'Pendiente', 'confirmed' => 'Confirmado', 'failed' => 'Fallido',
    ]);

    expect(Booking::statusLabels())->toBe([
        'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada',
    ]);

    expect(Turno::statusLabels())->toBe([
        'active' => 'Activo', 'inactive' => 'Inactivo', 'cancelled' => 'Cancelado',
    ]);

    expect(Routine::statusLabels())->toBe([
        'draft' => 'Borrador', 'active' => 'Activo', 'archived' => 'Archivado',
    ]);
});

it('maps payment methods and workout reference types to Spanish labels', function () {
    expect(Payment::methodLabels())->toBe([
        'cash' => 'Efectivo', 'transfer' => 'Transferencia bancaria',
    ]);

    expect(WorkoutLog::referenceTypeLabels())->toBe([
        'routine' => 'Desde rutina asignada', 'free' => 'Ejercicio libre',
    ]);
});

it('translates the Exercise muscle-group and difficulty labels to Spanish', function () {
    expect(Exercise::muscleGroupLabels())->toHaveCount(12)
        ->and(Exercise::muscleGroupLabels()[Exercise::MUSCLE_GROUP_CHEST])->toBe('Pecho')
        ->and(Exercise::muscleGroupLabels()[Exercise::MUSCLE_GROUP_SHOULDERS])->toBe('Hombros')
        ->and(Exercise::muscleGroupLabels()[Exercise::MUSCLE_GROUP_FULL_BODY])->toBe('Cuerpo completo');

    expect(Exercise::difficultyLabels())->toHaveCount(3)
        ->and(Exercise::difficultyLabels()[Exercise::DIFFICULTY_BEGINNER])->toBe('Principiante')
        ->and(Exercise::difficultyLabels()[Exercise::DIFFICULTY_INTERMEDIATE])->toBe('Intermedio')
        ->and(Exercise::difficultyLabels()[Exercise::DIFFICULTY_ADVANCED])->toBe('Avanzado');
});

it('keeps stored identifiers byte-for-byte unchanged while only the display label is Spanish', function () {
    $membership = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);

    expect($membership->status)->toBe('active')
        ->and($membership->fresh()->status)->toBe('active')
        ->and(Membership::statusLabels()[$membership->status])->toBe('Activo');

    $payment = Payment::factory()->create(['method' => Payment::METHOD_CASH]);

    expect($payment->method)->toBe('cash')
        ->and($payment->fresh()->method)->toBe('cash')
        ->and(Payment::methodLabels()[$payment->method])->toBe('Efectivo');
});
