<?php

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\WorkoutLog;

/*
 * Portal policy self-access tests (SPEC-013 §9, BR-002, BR-003, BR-007;
 * AC-17, AC-19; ADR-007). The CLIENT-own instance view/update abilities are
 * ownership-scoped; viewAny/create stay staff-only and no staff ability is
 * weakened.
 */

beforeEach(function () {
    $this->withoutVite();
});

it('lets a CLIENT view and update their own records only', function () {
    $own = clientWithUser();
    $foreign = clientWithUser();
    $ownUser = $own->user;

    // Client (view/update own; deny other).
    expect($ownUser->can('view', $own))->toBeTrue();
    expect($ownUser->can('update', $own))->toBeTrue();
    expect($ownUser->can('view', $foreign))->toBeFalse();
    expect($ownUser->can('update', $foreign))->toBeFalse();

    // Membership (view own; deny other).
    $ownMembership = Membership::factory()->create(['client_id' => $own->id]);
    $foreignMembership = Membership::factory()->create(['client_id' => $foreign->id]);
    expect($ownUser->can('view', $ownMembership))->toBeTrue();
    expect($ownUser->can('view', $foreignMembership))->toBeFalse();

    // Cuota (view own via its membership; deny other).
    $ownCuota = $ownMembership->cuota;
    $foreignCuota = $foreignMembership->cuota;
    expect($ownUser->can('view', $ownCuota))->toBeTrue();
    expect($ownUser->can('view', $foreignCuota))->toBeFalse();

    // Payment (view own via cuota -> membership; deny other).
    $ownPayment = Payment::factory()->create(['cuota_id' => $ownCuota->id]);
    $foreignPayment = Payment::factory()->create(['cuota_id' => $foreignCuota->id]);
    expect($ownUser->can('view', $ownPayment))->toBeTrue();
    expect($ownUser->can('view', $foreignPayment))->toBeFalse();

    // Attendance (view own; deny other).
    $ownAttendance = Attendance::factory()->create(['client_id' => $own->id]);
    $foreignAttendance = Attendance::factory()->create(['client_id' => $foreign->id]);
    expect($ownUser->can('view', $ownAttendance))->toBeTrue();
    expect($ownUser->can('view', $foreignAttendance))->toBeFalse();

    // Booking (view/update own; deny other).
    $ownBooking = Booking::factory()->create(['client_id' => $own->id]);
    $foreignBooking = Booking::factory()->create(['client_id' => $foreign->id]);
    expect($ownUser->can('view', $ownBooking))->toBeTrue();
    expect($ownUser->can('update', $ownBooking))->toBeTrue();
    expect($ownUser->can('view', $foreignBooking))->toBeFalse();
    expect($ownUser->can('update', $foreignBooking))->toBeFalse();

    // WorkoutLog (view own; deny other).
    $ownLog = WorkoutLog::factory()->create(['client_id' => $own->id]);
    $foreignLog = WorkoutLog::factory()->create(['client_id' => $foreign->id]);
    expect($ownUser->can('view', $ownLog))->toBeTrue();
    expect($ownUser->can('view', $foreignLog))->toBeFalse();

    // Routine (view only the current active assignment).
    $ownRoutine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    RoutineAssignment::factory()->create(['client_id' => $own->id, 'routine_id' => $ownRoutine->id, 'is_active' => true]);
    $foreignRoutine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    RoutineAssignment::factory()->create(['client_id' => $foreign->id, 'routine_id' => $foreignRoutine->id, 'is_active' => true]);
    expect($ownUser->can('view', $ownRoutine))->toBeTrue();
    expect($ownUser->can('view', $foreignRoutine))->toBeFalse();
});

it('denies a CLIENT viewing a routine that is not their current active assignment', function () {
    // BR-007: archived/historical versions are not client-visible.
    $own = clientWithUser();
    $ownUser = $own->user;

    $routine = Routine::factory()->create(['status' => Routine::STATUS_ACTIVE]);
    RoutineAssignment::factory()->create(['client_id' => $own->id, 'routine_id' => $routine->id, 'is_active' => false]);

    expect($ownUser->can('view', $routine))->toBeFalse();
});

it('keeps viewAny and create staff-only and delete/management denied for CLIENT', function () {
    // AC-17 (BR-003): no management ability in the portal.
    $own = clientWithUser();
    $user = $own->user;

    foreach ([Client::class, Membership::class, Cuota::class, Payment::class, Attendance::class, Booking::class, WorkoutLog::class, Routine::class] as $model) {
        expect($user->can('viewAny', $model))->toBeFalse();
    }

    foreach ([Client::class, Membership::class, Payment::class, Attendance::class, Booking::class, WorkoutLog::class, Routine::class] as $model) {
        expect($user->can('create', $model))->toBeFalse();
    }

    $membership = Membership::factory()->create();
    $cuota = $membership->cuota;

    expect($user->can('update', $membership))->toBeFalse();
    expect($user->can('update', $cuota))->toBeFalse();
    expect($user->can('delete', $membership))->toBeFalse();
});

it('preserves staff abilities for a multi-role ADMIN + CLIENT user', function () {
    // AC-19 (AF-005, SPEC-001 BR-002): staff clauses are unchanged.
    $adminClient = userWithRoles([Role::ADMIN, Role::CLIENT]);
    $foreignClient = clientWithUser();
    $membership = Membership::factory()->create(['client_id' => $foreignClient->id]);
    $booking = Booking::factory()->create(['client_id' => $foreignClient->id]);

    expect($adminClient->can('viewAny', Membership::class))->toBeTrue();
    expect($adminClient->can('view', $membership))->toBeTrue();
    expect($adminClient->can('create', Booking::class))->toBeTrue();
    expect($adminClient->can('update', $booking))->toBeTrue();
});
