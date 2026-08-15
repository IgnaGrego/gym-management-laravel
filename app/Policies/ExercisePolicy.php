<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\Role;
use App\Models\User;

class ExercisePolicy
{
    /**
     * Only ADMIN or TRAINER may list / search / filter exercises (SPEC-009
     * BR-009, FR-002; D-20 option 2).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may view an exercise's detail (SPEC-009 BR-009,
     * FR-003).
     */
    public function view(User $user, Exercise $exercise): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may create exercises (SPEC-009 BR-009, FR-001;
     * D-20 option 2).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may update exercises; this covers field edits
     * (FR-004) AND the activate / deactivate lifecycle transitions (FR-005),
     * the same way PlanPolicy::update covers activate/deactivate (SPEC-009
     * BR-009; the Plan precedent, SPEC-003 FR-005).
     */
    public function update(User $user, Exercise $exercise): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No delete policy is registered on purpose: exercise records are never
     * hard-deleted in the MVP; deactivation is used instead and no delete
     * operation exists (SPEC-009 BR-008, ERR-007).
     */
}
