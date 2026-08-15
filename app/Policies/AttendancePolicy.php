<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\Role;
use App\Models\User;

class AttendancePolicy
{
    /**
     * Only ADMIN or TRAINER may list / filter attendance records (SPEC-008
     * BR-009, FR-002, AT-01; D-19 option 1: front-desk tasks assigned to
     * TRAINER — the same role set as TurnoPolicy).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN or TRAINER may view an attendance record's detail (SPEC-008
     * BR-009, FR-003, AT-01). A CLIENT may view only their OWN attendance
     * record (SPEC-013 BR-002, C-13; ADR-007).
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT) && $attendance->client_id === $user->clientId());
    }

    /**
     * Only ADMIN or TRAINER may record a check-in (create attendance)
     * (SPEC-008 BR-009, FR-001, AT-01; D-09 option 3: staff manual).
     *
     * The access gate (BR-003) is NOT an authorization rule: it is a business
     * validation enforced by the create form's closure rule on client_id
     * (SPEC-008 §9).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No update and no delete policy is registered on purpose: attendance
     * records are an immutable event log — no field of a record is ever
     * modified or deleted (SPEC-008 BR-008, ERR-008, AT-07).
     */
}
