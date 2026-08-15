<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutLog;

class WorkoutLogPolicy
{
    /**
     * Only ADMIN or TRAINER may list / filter workout logs (SPEC-011 BR-007,
     * FR-003, FR-005, WL-03, WL-09; C-03 "a Trainer may review workout
     * progress" — the same role set as TurnoPolicy / ExercisePolicy /
     * AttendancePolicy).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN or TRAINER may view a workout log's detail (SPEC-011 BR-007,
     * FR-005, WL-03, WL-09). A CLIENT may view only their OWN log
     * (SPEC-013 BR-002, C-13; ADR-007).
     */
    public function view(User $user, WorkoutLog $workoutLog): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT) && $workoutLog->client_id === $user->clientId());
    }

    /**
     * Only ADMIN or TRAINER may record a workout log (SPEC-011 BR-007,
     * FR-001, FR-002, WL-03, WL-09).
     *
     * Logging and review are role-based, not ownership-based (BR-009): any
     * ADMIN or TRAINER may log for any client regardless of recorded_by.
     * The state/validation rules (ERR-001..ERR-005) are NOT authorization
     * rules: they are enforced by the create form's validation, so an
     * authorized user still cannot record an invalid log (SPEC-011 §9).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No update and no delete policy is registered on purpose: workout logs
     * are immutable event-log entries — no field of a record is ever modified
     * or deleted (SPEC-011 BR-006, ERR-007, WL-04 — the AttendancePolicy
     * stance).
     */
}
