<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\RoutineAssignment;
use App\Models\User;

class RoutineAssignmentPolicy
{
    /**
     * Only ADMIN or TRAINER may list assignments (SPEC-010 FR-011, BR-009;
     * the relation managers on RoutineResource and ClientResource render the
     * assignment history).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may view an assignment record (SPEC-010 FR-011,
     * BR-009).
     */
    public function view(User $user, RoutineAssignment $assignment): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may update an assignment; this covers the
     * Unassign row action on the assigned-clients relation manager, which
     * calls RoutineAssignment::deactivate() (SPEC-010 FR-010, BR-009).
     */
    public function update(User $user, RoutineAssignment $assignment): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No create policy is registered on purpose: assignments are created only
     * through App\Actions\AssignRoutine (authorized via RoutinePolicy::update
     * on the routine), never through a relation-manager create action.
     */

    /**
     * No delete policy is registered on purpose: assignment history is never
     * hard-deleted; deactivation is used instead (SPEC-010 BR-008, ERR-009).
     */
}
