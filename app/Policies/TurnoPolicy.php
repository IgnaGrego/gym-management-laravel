<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Turno;
use App\Models\User;

class TurnoPolicy
{
    /**
     * Only ADMIN or TRAINER may list / filter turnos (SPEC-006 BR-012,
     * FR-002, AS-01).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may view a turno's detail (SPEC-006 BR-012,
     * FR-003, AS-01).
     */
    public function view(User $user, Turno $turno): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may create turnos (SPEC-006 BR-012, FR-001,
     * AS-01).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may update turnos; this covers field edits
     * (FR-004) AND the deactivate / reactivate / cancel transitions
     * (FR-005..FR-007), the same way PlanPolicy::update covers
     * activate/deactivate and MembershipPolicy::update covers cancel
     * (SPEC-006 BR-012, AS-01).
     *
     * The state rules (BR-003, BR-004 — e.g. a `cancelled` turno cannot be
     * edited or reactivated) are NOT authorization rules: they are enforced
     * by the model lifecycle methods and by TurnoResource::canEdit.
     */
    public function update(User $user, Turno $turno): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No delete policy is registered on purpose: turno records are never
     * hard-deleted in the MVP; deactivation and cancellation are used instead
     * and no delete operation exists (SPEC-006 BR-009, AC-13).
     */
}
